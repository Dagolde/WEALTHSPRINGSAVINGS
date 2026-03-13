#!/usr/bin/env python3
"""
Database Infrastructure Check Script
Verifies PostgreSQL and Redis connectivity
"""

import asyncio
import sys
from app.config import settings
from app.database import check_db_connection, engine
from app.redis_client import check_redis_connection, get_redis
from sqlalchemy import text


async def check_postgresql():
    """Check PostgreSQL connection and details"""
    print("PostgreSQL Connection:")
    print("-" * 50)
    
    try:
        # Check basic connection
        is_healthy = await check_db_connection()
        if is_healthy:
            print("  ✓ Connection successful")
        else:
            print("  ✗ Connection failed")
            return False
        
        # Get database details
        async with engine.connect() as conn:
            # Get PostgreSQL version
            result = await conn.execute(text("SELECT version()"))
            version = result.scalar()
            print(f"  ✓ PostgreSQL version: {version[:50]}...")
            
            # Get current database
            result = await conn.execute(text("SELECT current_database()"))
            db_name = result.scalar()
            print(f"  ✓ Database: {db_name}")
            
            # Get active connections
            result = await conn.execute(text(
                "SELECT count(*) FROM pg_stat_activity WHERE datname = current_database()"
            ))
            conn_count = result.scalar()
            print(f"  ✓ Active connections: {conn_count}")
            
            # Get pool settings
            print(f"  ✓ Pool size: {settings.database_pool_size}")
            print(f"  ✓ Max overflow: {settings.database_max_overflow}")
        
        return True
        
    except Exception as e:
        print(f"  ✗ PostgreSQL check failed: {e}")
        return False


async def check_redis_details():
    """Check Redis connection and details"""
    print("\nRedis Connection:")
    print("-" * 50)
    
    try:
        # Check basic connection
        is_healthy = await check_redis_connection()
        if is_healthy:
            print("  ✓ Connection successful")
        else:
            print("  ✗ Connection failed")
            return False
        
        # Get Redis details
        client = await get_redis()
        
        # Get Redis info
        info = await client.info()
        print(f"  ✓ Redis version: {info.get('redis_version', 'Unknown')}")
        print(f"  ✓ Memory usage: {info.get('used_memory_human', 'Unknown')}")
        print(f"  ✓ Connected clients: {info.get('connected_clients', 'Unknown')}")
        print(f"  ✓ Total commands: {info.get('total_commands_processed', 'Unknown')}")
        
        # Test cache operations
        test_key = "infrastructure_test"
        await client.set(test_key, "test_value", ex=10)
        value = await client.get(test_key)
        await client.delete(test_key)
        
        if value == "test_value":
            print("  ✓ Cache operations working")
        else:
            print("  ✗ Cache operations failed")
            return False
        
        # Check database allocation
        print(f"\n  Database Allocation:")
        print(f"    - Default/General: DB {settings.redis_url.split('/')[-1]}")
        print(f"    - Cache: DB {settings.redis_cache_db}")
        print(f"    - Celery Broker: DB {settings.redis_celery_broker_db}")
        print(f"    - Celery Backend: DB {settings.redis_celery_backend_db}")
        
        return True
        
    except Exception as e:
        print(f"  ✗ Redis check failed: {e}")
        return False


async def main():
    """Main check function"""
    print("=" * 50)
    print("Database Infrastructure Check")
    print("=" * 50)
    print(f"\nEnvironment: {settings.environment}")
    print(f"Application: {settings.app_name}")
    print()
    
    # Check PostgreSQL
    pg_ok = await check_postgresql()
    
    # Check Redis
    redis_ok = await check_redis_details()
    
    # Summary
    print("\n" + "=" * 50)
    print("Summary:")
    print("=" * 50)
    print(f"PostgreSQL: {'✓ Healthy' if pg_ok else '✗ Unhealthy'}")
    print(f"Redis: {'✓ Healthy' if redis_ok else '✗ Unhealthy'}")
    
    if pg_ok and redis_ok:
        print("\n✓ All infrastructure checks passed!")
        return 0
    else:
        print("\n✗ Some infrastructure checks failed!")
        return 1


if __name__ == "__main__":
    exit_code = asyncio.run(main())
    sys.exit(exit_code)
