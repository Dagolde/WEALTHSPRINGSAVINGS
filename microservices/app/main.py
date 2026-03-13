"""
Main FastAPI Application
Entry point for the microservices
"""

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from app.config import settings
import logging

# Configure logging
logging.basicConfig(
    level=getattr(logging, settings.log_level.upper()),
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)

logger = logging.getLogger(__name__)

# Create FastAPI application
app = FastAPI(
    title=settings.app_name,
    version=settings.app_version,
    debug=settings.debug,
    docs_url="/docs" if settings.debug else None,
    redoc_url="/redoc" if settings.debug else None,
)

# CORS Configuration
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"] if settings.is_development else ["https://yourdomain.com"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.get("/")
async def root():
    """Root endpoint - health check"""
    return {
        "service": settings.app_name,
        "version": settings.app_version,
        "status": "running",
        "environment": settings.environment
    }


@app.get("/health")
async def health_check():
    """Health check endpoint for monitoring"""
    from app.database import check_db_connection
    from app.redis_client import check_redis_connection
    
    # Check database connection
    db_healthy = await check_db_connection()
    
    # Check Redis connection
    redis_healthy = await check_redis_connection()
    
    # Overall health status
    overall_status = "healthy" if (db_healthy and redis_healthy) else "degraded"
    status_code = 200 if overall_status == "healthy" else 503
    
    return JSONResponse(
        status_code=status_code,
        content={
            "status": overall_status,
            "service": settings.app_name,
            "version": settings.app_version,
            "checks": {
                "database": "healthy" if db_healthy else "unhealthy",
                "redis": "healthy" if redis_healthy else "unhealthy"
            }
        }
    )


# Import and include routers
from app.services.payment.routes import router as payment_router
from app.services.notification.routes import router as notification_router
from app.services.fraud.routes import router as fraud_router

app.include_router(payment_router, prefix="/api/v1/payments", tags=["payments"])
app.include_router(notification_router, prefix="/api/v1/notifications", tags=["notifications"])
app.include_router(fraud_router, prefix="/api/v1/fraud", tags=["fraud"])


@app.on_event("startup")
async def startup_event():
    """Application startup event"""
    logger.info(f"Starting {settings.app_name} v{settings.app_version}")
    logger.info(f"Environment: {settings.environment}")
    
    # Initialize database connection
    from app.database import init_db, check_db_connection
    try:
        await init_db()
        db_healthy = await check_db_connection()
        if db_healthy:
            logger.info("Database connection established successfully")
        else:
            logger.warning("Database connection check failed")
    except Exception as e:
        logger.error(f"Failed to initialize database: {e}")
    
    # Check Redis connection
    from app.redis_client import check_redis_connection
    try:
        redis_healthy = await check_redis_connection()
        if redis_healthy:
            logger.info("Redis connection established successfully")
        else:
            logger.warning("Redis connection check failed")
    except Exception as e:
        logger.error(f"Failed to connect to Redis: {e}")


@app.on_event("shutdown")
async def shutdown_event():
    """Application shutdown event"""
    logger.info(f"Shutting down {settings.app_name}")
    
    # Close database connections
    from app.database import close_db
    try:
        await close_db()
        logger.info("Database connections closed")
    except Exception as e:
        logger.error(f"Error closing database connections: {e}")
    
    # Close Redis connections
    from app.redis_client import close_redis
    try:
        await close_redis()
        logger.info("Redis connections closed")
    except Exception as e:
        logger.error(f"Error closing Redis connections: {e}")


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "app.main:app",
        host="0.0.0.0",
        port=8001,
        reload=settings.debug
    )
