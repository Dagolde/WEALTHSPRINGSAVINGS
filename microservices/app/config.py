"""
Application Configuration
Manages environment variables and settings using Pydantic Settings
"""

from pydantic_settings import BaseSettings, SettingsConfigDict
from typing import Optional


class Settings(BaseSettings):
    """Application settings loaded from environment variables"""
    
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
        extra="ignore"
    )
    
    # Application
    environment: str = "development"
    app_name: str = "Rotational Contribution Microservices"
    app_version: str = "1.0.0"
    debug: bool = True
    
    # Database
    database_url: str
    database_pool_size: int = 20
    database_max_overflow: int = 10
    
    # Redis
    redis_url: str = "redis://localhost:6379/0"
    redis_cache_db: int = 1
    redis_celery_broker_db: int = 2
    redis_celery_backend_db: int = 3
    
    # Celery
    celery_broker_url: str = "redis://localhost:6379/2"
    celery_result_backend: str = "redis://localhost:6379/3"
    celery_task_serializer: str = "json"
    celery_result_serializer: str = "json"
    celery_accept_content: str = "json"
    celery_timezone: str = "Africa/Lagos"
    celery_enable_utc: bool = True
    
    # Payment Gateway - Paystack
    paystack_secret_key: str
    paystack_public_key: str
    paystack_webhook_secret: str
    paystack_base_url: str = "https://api.paystack.co"
    
    # Payment Gateway - Flutterwave
    flutterwave_secret_key: Optional[str] = None
    flutterwave_public_key: Optional[str] = None
    flutterwave_encryption_key: Optional[str] = None
    flutterwave_webhook_secret: Optional[str] = None
    flutterwave_base_url: str = "https://api.flutterwave.com/v3"
    
    # Notification Services
    fcm_server_key: str
    fcm_sender_id: str
    
    # SMS Gateway - Termii
    termii_api_key: str
    termii_sender_id: str = "RotationalApp"
    termii_base_url: str = "https://api.ng.termii.com/api"
    
    # Email Service - SendGrid
    sendgrid_api_key: str
    sendgrid_from_email: str = "noreply@rotationalapp.com"
    sendgrid_from_name: str = "Rotational Contribution App"
    
    # Laravel Backend Integration
    laravel_api_url: str = "http://localhost:8000/api/v1"
    laravel_api_key: str
    
    # Security
    secret_key: str
    algorithm: str = "HS256"
    access_token_expire_minutes: int = 60
    
    # Logging
    log_level: str = "INFO"
    log_format: str = "json"
    
    # Scheduler Configuration
    payout_processing_time: str = "00:00"
    contribution_reminder_time: str = "09:00"
    missed_contribution_check_time: str = "23:00"
    group_completion_check_time: str = "01:00"
    
    @property
    def redis_cache_url(self) -> str:
        """Get Redis URL for caching"""
        base_url = self.redis_url.rsplit('/', 1)[0]
        return f"{base_url}/{self.redis_cache_db}"
    
    @property
    def is_production(self) -> bool:
        """Check if running in production"""
        return self.environment.lower() == "production"
    
    @property
    def is_development(self) -> bool:
        """Check if running in development"""
        return self.environment.lower() == "development"


# Global settings instance
settings = Settings()
