"""
Redis Client Configuration
Manages Redis connections for caching and Celery
"""

import redis.asyncio as aioredis
from redis import Redis
from app.config import settings
import logging

logger = logging.getLogger(__name__)

# Async Redis client for caching
async_redis_client: aioredis.Redis = None

# Sync Redis client for Celery
sync_redis_client: Redis = None


async def get_redis() -> aioredis.Redis:
    """Get async Redis client instance"""
    global async_redis_client
    if async_redis_client is None:
        async_redis_client = await aioredis.from_url(
            settings.redis_cache_url,
            encoding="utf-8",
            decode_responses=True
        )
        logger.info("Async Redis client initialized")
    return async_redis_client


def get_sync_redis() -> Redis:
    """Get sync Redis client instance for Celery"""
    global sync_redis_client
    if sync_redis_client is None:
        sync_redis_client = Redis.from_url(
            settings.redis_url,
            encoding="utf-8",
            decode_responses=True
        )
        logger.info("Sync Redis client initialized")
    return sync_redis_client


async def close_redis():
    """Close Redis connections"""
    global async_redis_client, sync_redis_client
    
    if async_redis_client:
        await async_redis_client.close()
        logger.info("Async Redis client closed")
    
    if sync_redis_client:
        sync_redis_client.close()
        logger.info("Sync Redis client closed")


async def check_redis_connection() -> bool:
    """
    Check if Redis connection is healthy
    Returns True if connection is successful, False otherwise
    """
    try:
        client = await get_redis()
        await client.ping()
        return True
    except Exception as e:
        logger.error(f"Redis connection check failed: {e}")
        return False


class RedisCache:
    """Redis caching utility"""
    
    def __init__(self):
        self.client = None
    
    async def get_client(self):
        """Get Redis client"""
        if self.client is None:
            self.client = await get_redis()
        return self.client
    
    async def get(self, key: str):
        """Get value from cache"""
        client = await self.get_client()
        return await client.get(key)
    
    async def set(self, key: str, value: str, expire: int = 300):
        """Set value in cache with expiration (default 5 minutes)"""
        client = await self.get_client()
        return await client.setex(key, expire, value)
    
    async def delete(self, key: str):
        """Delete key from cache"""
        client = await self.get_client()
        return await client.delete(key)
    
    async def exists(self, key: str) -> bool:
        """Check if key exists"""
        client = await self.get_client()
        return await client.exists(key) > 0


# Global cache instance
cache = RedisCache()
