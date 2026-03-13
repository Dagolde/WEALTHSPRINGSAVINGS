# Scheduler Service

The Scheduler Service is a Celery-based task scheduler that automates critical operations for the Rotational Contribution App. It handles daily payout processing, contribution reminders, missed contribution detection, and group cycle completion.

## Overview

The scheduler uses Celery Beat to run periodic tasks at configured times. All tasks are executed asynchronously and include comprehensive error handling and logging.

## Architecture

```
┌─────────────────────────────────────────┐
│         Celery Beat Scheduler           │
│  (Triggers tasks at scheduled times)    │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│         Redis Message Broker            │
│  (Queues: high, default, low)           │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│         Celery Workers                  │
│  (Execute scheduled tasks)              │
└──────────────┬──────────────────────────┘
               │
               ├─► PostgreSQL Database
               ├─► Laravel Backend API
               └─► Notification Service
```

## Scheduled Tasks

### 1. Daily Payout Processing
**Task:** `process_daily_payouts`  
**Schedule:** Daily at 00:00 (configurable via `PAYOUT_PROCESSING_TIME`)  
**Queue:** high  
**Purpose:** Process payouts for groups where all members have contributed

**Workflow:**
1. Query all active groups with payouts due today
2. Verify all contributions received for each group
3. Calculate payout amount (contribution_amount × total_members)
4. Call Laravel backend API to process payout
5. Send notification to payout recipient
6. Log results and handle failures

**Validates:** Property 9 (Payout Eligibility)

**Example:**
```python
# Manual trigger (for testing)
from app.services.scheduler.tasks import process_daily_payouts
result = process_daily_payouts.delay()
```

### 2. Contribution Reminders
**Task:** `send_contribution_reminders`  
**Schedule:** Daily at 09:00 (configurable via `CONTRIBUTION_REMINDER_TIME`)  
**Queue:** default  
**Purpose:** Remind members who haven't contributed today

**Workflow:**
1. Query all active groups
2. Identify members without successful contributions today
3. Send multi-channel reminders (push, SMS)
4. Log reminder delivery status

**Validates:** Property 15 (Notification Delivery)

**Example:**
```python
from app.services.scheduler.tasks import send_contribution_reminders
result = send_contribution_reminders.delay()
```

### 3. Missed Contribution Detection
**Task:** `detect_missed_contributions`  
**Schedule:** Daily at 23:00 (configurable via `MISSED_CONTRIBUTION_CHECK_TIME`)  
**Queue:** default  
**Purpose:** Detect and alert on missed contributions at end of day

**Workflow:**
1. Query all active groups
2. Identify members who missed their contribution
3. Send missed contribution alerts (push, SMS, email)
4. Log missed contributions in audit_logs table
5. Track for reporting and analytics

**Example:**
```python
from app.services.scheduler.tasks import detect_missed_contributions
result = detect_missed_contributions.delay()
```

### 4. Group Cycle Completion
**Task:** `check_group_completion`  
**Schedule:** Daily at 01:00 (configurable via `GROUP_COMPLETION_CHECK_TIME`)  
**Queue:** default  
**Purpose:** Mark groups as completed when all payouts are done

**Workflow:**
1. Query all active groups
2. Check if all members have received payouts
3. Update group status to 'completed'
4. Send completion notifications to all members
5. Celebrate successful cycle completion!

**Validates:** Property 18 (Group Cycle Completion Invariant)

**Example:**
```python
from app.services.scheduler.tasks import check_group_completion
result = check_group_completion.delay()
```

## Configuration

All scheduler settings are configured via environment variables in `.env`:

```env
# Scheduler Configuration
PAYOUT_PROCESSING_TIME=00:00          # Daily payout processing time
CONTRIBUTION_REMINDER_TIME=09:00      # Morning reminder time
MISSED_CONTRIBUTION_CHECK_TIME=23:00  # End of day check time
GROUP_COMPLETION_CHECK_TIME=01:00     # Group completion check time

# Celery Configuration
CELERY_BROKER_URL=redis://localhost:6379/2
CELERY_RESULT_BACKEND=redis://localhost:6379/3
CELERY_TIMEZONE=Africa/Lagos
```

## Task Queues

The scheduler uses three priority queues:

- **high**: Critical tasks (payout processing)
- **default**: Standard tasks (reminders, detection)
- **low**: Background tasks (analytics, cleanup)

## Running the Scheduler

### Start Celery Worker
```bash
celery -A app.celery_app worker --loglevel=info --queues=high,default,low
```

### Start Celery Beat (Scheduler)
```bash
celery -A app.celery_app beat --loglevel=info
```

### Start Both (Development)
```bash
celery -A app.celery_app worker --beat --loglevel=info
```

### Using Docker Compose
```bash
docker-compose up celery-worker celery-beat
```

## Monitoring

### View Task Status
```python
from celery.result import AsyncResult

result = AsyncResult('task-id', app=celery_app)
print(result.state)  # PENDING, STARTED, SUCCESS, FAILURE
print(result.result)  # Task return value
```

### View Scheduled Tasks
```bash
celery -A app.celery_app inspect scheduled
```

### View Active Tasks
```bash
celery -A app.celery_app inspect active
```

### View Registered Tasks
```bash
celery -A app.celery_app inspect registered
```

## Error Handling

All tasks include comprehensive error handling:

1. **Database Errors**: Automatic rollback and retry
2. **API Errors**: Logged with full context, task marked as failed
3. **Network Errors**: Retry with exponential backoff
4. **Notification Errors**: Logged but don't fail the task

**Example Error Log:**
```json
{
  "timestamp": "2024-01-15T10:30:00Z",
  "level": "error",
  "task": "process_daily_payouts",
  "error": "Failed to process payout for group 123",
  "context": {
    "group_id": 123,
    "user_id": 456,
    "amount": 10000.00
  }
}
```

## Testing

### Unit Tests
```bash
pytest tests/test_scheduler_tasks.py -v
```

### Property-Based Tests
```bash
pytest tests/test_scheduler_properties.py -v
```

### Manual Task Execution
```python
# Execute task synchronously (for testing)
from app.services.scheduler.tasks import process_daily_payouts
result = process_daily_payouts()
print(result)
```

## Database Queries

The scheduler executes optimized SQL queries:

### Payout Eligibility Check
```sql
SELECT COUNT(*) as contribution_count
FROM contributions
WHERE group_id = :group_id
AND contribution_date = :today
AND payment_status = 'successful'
```

### Group Completion Check
```sql
SELECT g.id, g.name, g.total_members,
       COUNT(gm.id) as total_group_members,
       SUM(CASE WHEN gm.has_received_payout = TRUE THEN 1 ELSE 0 END) as members_paid
FROM groups g
INNER JOIN group_members gm ON g.id = gm.group_id
WHERE g.status = 'active'
AND gm.status = 'active'
GROUP BY g.id, g.name, g.total_members
HAVING COUNT(gm.id) = g.total_members
AND SUM(CASE WHEN gm.has_received_payout = TRUE THEN 1 ELSE 0 END) = g.total_members
```

## Integration with Laravel Backend

The scheduler calls Laravel API endpoints:

### Process Payout
```http
POST /api/v1/payouts/process
Authorization: Bearer {LARAVEL_API_KEY}
Content-Type: application/json

{
  "group_id": 123,
  "user_id": 456,
  "amount": 10000.00,
  "payout_day": 5,
  "payout_date": "2024-01-15"
}
```

## Performance Considerations

- **Database Connection Pooling**: Reuses connections for efficiency
- **Async Operations**: All I/O operations are asynchronous
- **Batch Processing**: Processes multiple groups in single task execution
- **Query Optimization**: Uses indexed columns and efficient JOINs
- **Task Timeouts**: 30-minute hard limit, 25-minute soft limit

## Troubleshooting

### Task Not Running
1. Check Celery Beat is running: `ps aux | grep celery`
2. Verify schedule configuration in `celery_app.py`
3. Check Redis connection: `redis-cli ping`

### Task Failing
1. Check logs: `tail -f logs/celery.log`
2. Verify database connection
3. Check Laravel API availability
4. Verify environment variables

### Notifications Not Sent
1. Check notification service configuration
2. Verify API keys (FCM, Termii, SendGrid)
3. Check user notification preferences
4. Review notification service logs

## Best Practices

1. **Always use async functions** for I/O operations
2. **Log all critical operations** with context
3. **Handle errors gracefully** - don't let one failure stop the entire task
4. **Use database transactions** for data consistency
5. **Monitor task execution** regularly
6. **Test tasks thoroughly** before deploying to production

## Future Enhancements

- [ ] Add task retry logic with exponential backoff
- [ ] Implement task result caching
- [ ] Add real-time task monitoring dashboard
- [ ] Implement task priority adjustment based on load
- [ ] Add support for custom task schedules per group
- [ ] Implement task result webhooks for external systems

## Support

For issues or questions:
- Check logs in `logs/celery.log`
- Review task status in Celery Flower: `http://localhost:5555`
- Contact backend team for API integration issues
- Review database schema for data structure questions
