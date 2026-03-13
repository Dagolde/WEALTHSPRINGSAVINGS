"""
Celery Application Configuration
Task queue and scheduler setup
"""

from celery import Celery
from celery.schedules import crontab
from app.config import settings
import logging

logger = logging.getLogger(__name__)

# Create Celery application
celery_app = Celery(
    "rotational_contribution",
    broker=settings.celery_broker_url,
    backend=settings.celery_result_backend,
)

# Configure Celery
celery_app.conf.update(
    task_serializer=settings.celery_task_serializer,
    result_serializer=settings.celery_result_serializer,
    accept_content=[settings.celery_accept_content],
    timezone=settings.celery_timezone,
    enable_utc=settings.celery_enable_utc,
    task_track_started=True,
    task_time_limit=30 * 60,  # 30 minutes
    task_soft_time_limit=25 * 60,  # 25 minutes
    worker_prefetch_multiplier=4,
    worker_max_tasks_per_child=1000,
)

# Task routing configuration
celery_app.conf.task_routes = {
    'app.services.scheduler.tasks.process_daily_payouts': {'queue': 'high'},
    'app.services.scheduler.tasks.send_contribution_reminders': {'queue': 'default'},
    'app.services.scheduler.tasks.detect_missed_contributions': {'queue': 'default'},
    'app.services.scheduler.tasks.check_group_completion': {'queue': 'default'},
    'app.services.notification.tasks.*': {'queue': 'default'},
    'app.services.payment.tasks.*': {'queue': 'high'},
}

# Celery Beat schedule for periodic tasks
celery_app.conf.beat_schedule = {
    'process-daily-payouts': {
        'task': 'app.services.scheduler.tasks.process_daily_payouts',
        'schedule': crontab(
            hour=settings.payout_processing_time.split(':')[0],
            minute=settings.payout_processing_time.split(':')[1]
        ),
    },
    'send-contribution-reminders': {
        'task': 'app.services.scheduler.tasks.send_contribution_reminders',
        'schedule': crontab(
            hour=settings.contribution_reminder_time.split(':')[0],
            minute=settings.contribution_reminder_time.split(':')[1]
        ),
    },
    'detect-missed-contributions': {
        'task': 'app.services.scheduler.tasks.detect_missed_contributions',
        'schedule': crontab(
            hour=settings.missed_contribution_check_time.split(':')[0],
            minute=settings.missed_contribution_check_time.split(':')[1]
        ),
    },
    'check-group-completion': {
        'task': 'app.services.scheduler.tasks.check_group_completion',
        'schedule': crontab(
            hour=settings.group_completion_check_time.split(':')[0],
            minute=settings.group_completion_check_time.split(':')[1]
        ),
    },
}

# Auto-discover tasks from services
celery_app.autodiscover_tasks([
    'app.services.payment',
    'app.services.scheduler',
    'app.services.notification',
    'app.services.fraud',
])

logger.info("Celery application configured")
