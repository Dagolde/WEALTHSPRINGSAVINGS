# Rotational Contribution App - FastAPI Microservices

This directory contains the FastAPI microservices for the Rotational Contribution App (Ajo Platform). These services handle high-performance payment processing, scheduled tasks, fraud detection, and notification dispatching.

## Architecture

The microservices layer consists of four main services:

1. **Payment Service** - Payment gateway integration (Paystack/Flutterwave)
2. **Scheduler Service** - Celery-based scheduled tasks (payouts, reminders)
3. **Notification Service** - Multi-channel notifications (Push, SMS, Email)
4. **Fraud Detection Service** - Real-time fraud detection and prevention

## Technology Stack

- **FastAPI** - Modern, fast web framework for building APIs
- **Python 3.9+** - Programming language
- **Celery** - Distributed task queue for background jobs
- **Redis** - Message broker and caching
- **SQLAlchemy** - Database ORM
- **Pydantic** - Data validation and settings management

## Project Structure

```
microservices/
├── app/
│   ├── __init__.py
│   ├── main.py                 # FastAPI application entry point
│   ├── config.py               # Configuration and settings
│   ├── database.py             # Database configuration
│   ├── redis_client.py         # Redis client setup
│   ├── celery_app.py           # Celery configuration
│   └── services/
│       ├── payment/            # Payment service
│       │   ├── __init__.py
│       │   ├── service.py      # Payment gateway integration
│       │   └── routes.py       # API endpoints
│       ├── scheduler/          # Scheduler service
│       │   ├── __init__.py
│       │   └── tasks.py        # Celery tasks
│       ├── notification/       # Notification service
│       │   ├── __init__.py
│       │   ├── service.py      # Notification dispatcher
│       │   └── routes.py       # API endpoints
│       └── fraud/              # Fraud detection service
│           ├── __init__.py
│           ├── service.py      # Fraud detection logic
│           └── routes.py       # API endpoints
├── requirements.txt            # Python dependencies
├── .env.example                # Environment variables template
├── .gitignore                  # Git ignore rules
└── README.md                   # This file
```

## Setup Instructions

### 1. Prerequisites

- Python 3.9 or higher
- PostgreSQL database
- Redis server
- Virtual environment tool (venv or virtualenv)

### 2. Create Virtual Environment

```bash
cd microservices
python -m venv venv

# Activate virtual environment
# On Windows:
venv\Scripts\activate
# On macOS/Linux:
source venv/bin/activate
```

### 3. Install Dependencies

```bash
pip install -r requirements.txt
```

### 4. Configure Environment Variables

```bash
cp .env.example .env
# Edit .env file with your configuration
```

Required environment variables:
- `DATABASE_URL` - PostgreSQL connection string
- `REDIS_URL` - Redis connection string
- `PAYSTACK_SECRET_KEY` - Paystack API secret key
- `PAYSTACK_WEBHOOK_SECRET` - Paystack webhook secret
- `FCM_SERVER_KEY` - Firebase Cloud Messaging key
- `TERMII_API_KEY` - Termii SMS gateway API key
- `SENDGRID_API_KEY` - SendGrid email API key
- `LARAVEL_API_KEY` - Laravel backend API key

### 5. Run Database Migrations

```bash
# TODO: Add migration commands when implemented
```

## Running the Services

### Development Mode

#### Start FastAPI Server

```bash
# Run with auto-reload
uvicorn app.main:app --reload --port 8001
```

The API will be available at `http://localhost:8001`

API documentation (Swagger UI): `http://localhost:8001/docs`

#### Start Celery Worker

```bash
# In a separate terminal
celery -A app.celery_app worker --loglevel=info
```

#### Start Celery Beat (Scheduler)

```bash
# In another terminal
celery -A app.celery_app beat --loglevel=info
```

### Production Mode

```bash
# Start FastAPI with Gunicorn
gunicorn app.main:app -w 4 -k uvicorn.workers.UvicornWorker --bind 0.0.0.0:8001

# Start Celery worker
celery -A app.celery_app worker --loglevel=info --concurrency=4

# Start Celery beat
celery -A app.celery_app beat --loglevel=info
```

## API Endpoints

### Payment Service

- `POST /api/v1/payments/initialize` - Initialize payment
- `GET /api/v1/payments/verify/{reference}` - Verify payment
- `POST /api/v1/payments/payout` - Initiate payout
- `POST /api/v1/payments/resolve-account` - Resolve bank account
- `GET /api/v1/payments/banks` - List supported banks
- `POST /api/v1/payments/webhook` - Payment webhook handler

### Notification Service

- `POST /api/v1/notifications/push` - Send push notification
- `POST /api/v1/notifications/sms` - Send SMS
- `POST /api/v1/notifications/email` - Send email
- `POST /api/v1/notifications/send` - Send multi-channel notification

### Fraud Detection Service

- `POST /api/v1/fraud/analyze-user` - Analyze user behavior
- `POST /api/v1/fraud/analyze-payment` - Check payment fraud
- `POST /api/v1/fraud/check-duplicate-accounts` - Detect duplicates
- `POST /api/v1/fraud/flag-activity` - Flag suspicious activity
- `POST /api/v1/fraud/analyze-withdrawal` - Check withdrawal fraud

### Health Check

- `GET /` - Service status
- `GET /health` - Health check endpoint

## Scheduled Tasks

The following tasks run automatically via Celery Beat:

1. **Daily Payout Processing** - Runs at 00:00 (midnight)
   - Processes payouts for all active groups
   - Verifies contributions received
   - Credits member wallets

2. **Contribution Reminders** - Runs at 09:00 (9 AM)
   - Sends reminders to members who haven't contributed
   - Multi-channel notifications (push, SMS, email)

3. **Missed Contribution Detection** - Runs at 23:00 (11 PM)
   - Identifies missed contributions
   - Sends alerts to users and admins
   - Logs for reporting

4. **Group Completion Check** - Runs at 01:00 (1 AM)
   - Checks for completed group cycles
   - Updates group status
   - Sends completion notifications

## Testing

```bash
# Run all tests
pytest

# Run with coverage
pytest --cov=app --cov-report=html

# Run specific test file
pytest tests/test_payment_service.py

# Run property-based tests
pytest tests/property/
```

## Code Quality

```bash
# Format code with Black
black app/

# Lint with Flake8
flake8 app/

# Type checking with MyPy
mypy app/
```

## Monitoring and Logging

Logs are written to stdout in JSON format (configurable via `LOG_FORMAT` environment variable).

Log levels:
- `DEBUG` - Detailed information for debugging
- `INFO` - General informational messages
- `WARNING` - Warning messages
- `ERROR` - Error messages
- `CRITICAL` - Critical errors

## Integration with Laravel Backend

The microservices communicate with the Laravel backend via REST API:

- Authentication: API key in `X-API-Key` header
- Base URL: Configured via `LARAVEL_API_URL` environment variable
- Endpoints: `/api/v1/*`

## Security Considerations

1. **API Authentication** - All endpoints require authentication
2. **Webhook Signature Verification** - Payment webhooks are verified
3. **Environment Variables** - Sensitive data stored in environment variables
4. **HTTPS** - All external communications use HTTPS
5. **Rate Limiting** - Implement rate limiting for API endpoints
6. **Input Validation** - Pydantic models validate all inputs

## Troubleshooting

### Common Issues

1. **Redis Connection Error**
   - Ensure Redis server is running
   - Check `REDIS_URL` in `.env` file

2. **Database Connection Error**
   - Verify PostgreSQL is running
   - Check `DATABASE_URL` format

3. **Celery Tasks Not Running**
   - Ensure Celery worker is running
   - Check Celery Beat is running for scheduled tasks
   - Verify Redis broker is accessible

4. **Payment Gateway Errors**
   - Verify API keys are correct
   - Check if using test/sandbox mode
   - Review payment gateway documentation

## Contributing

1. Follow PEP 8 style guide
2. Write tests for new features
3. Update documentation
4. Use type hints
5. Format code with Black

## License

Proprietary - All rights reserved
