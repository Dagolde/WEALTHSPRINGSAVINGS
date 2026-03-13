# Payment Gateway Service

FastAPI microservice for payment processing with Paystack/Flutterwave integration.

## Features

- **Payment Initialization**: Initialize payment transactions with Paystack
- **Payment Verification**: Verify payment status and transaction details
- **Webhook Processing**: Handle payment gateway webhooks with idempotency (Property 8)
- **Payout Processing**: Initiate payouts to bank accounts
- **Bank Account Resolution**: Resolve and validate bank account details
- **Bank Listing**: Get list of supported banks

## API Endpoints

### Payment Initialization

```http
POST /api/v1/payments/initialize
Content-Type: application/json

{
  "amount": 1000.0,
  "email": "user@example.com",
  "metadata": {
    "user_id": 123,
    "group_id": 45
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "authorization_url": "https://checkout.paystack.com/...",
    "access_code": "...",
    "reference": "..."
  }
}
```

### Payment Verification

```http
GET /api/v1/payments/verify/{reference}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "reference": "...",
    "amount": 100000,
    "status": "success",
    "paid_at": "2024-01-15T10:30:00Z"
  }
}
```

### Webhook Handler

```http
POST /api/v1/payments/webhook
X-Paystack-Signature: <signature>
Content-Type: application/json

{
  "event": "charge.success",
  "data": {
    "reference": "...",
    "amount": 100000,
    "status": "success"
  }
}
```

**Features:**
- Signature verification for security
- Idempotent processing (Property 8)
- Automatic forwarding to Laravel backend
- 24-hour cache for duplicate detection

### Payout Initiation

```http
POST /api/v1/payments/payout
Content-Type: application/json

{
  "amount": 5000.0,
  "account_number": "0123456789",
  "bank_code": "058",
  "reason": "Payout for group contribution"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "reference": "TRF_...",
    "transfer_code": "...",
    "status": "pending"
  }
}
```

### Bank Account Resolution

```http
POST /api/v1/payments/resolve-account
Content-Type: application/json

{
  "account_number": "0123456789",
  "bank_code": "058"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "account_number": "0123456789",
    "account_name": "John Doe"
  }
}
```

### List Banks

```http
GET /api/v1/payments/banks
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "name": "GTBank",
      "code": "058"
    },
    {
      "name": "Access Bank",
      "code": "044"
    }
  ]
}
```

## Configuration

Environment variables required:

```env
# Paystack Configuration
PAYSTACK_SECRET_KEY=sk_test_xxxxxxxxxxxxxxxxxxxxx
PAYSTACK_PUBLIC_KEY=pk_test_xxxxxxxxxxxxxxxxxxxxx
PAYSTACK_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxxxxxxx
PAYSTACK_BASE_URL=https://api.paystack.co

# Laravel Backend Integration
LARAVEL_API_URL=http://localhost:8000/api/v1
LARAVEL_API_KEY=xxxxxxxxxxxxxxxxxxxxx

# Redis (for webhook idempotency)
REDIS_URL=redis://localhost:6379/0
REDIS_CACHE_DB=1
```

## Webhook Idempotency (Property 8)

The webhook handler implements idempotent processing to ensure that duplicate webhooks don't cause duplicate transactions:

1. **First Request**: Webhook is processed and result is cached in Redis for 24 hours
2. **Duplicate Requests**: Cached result is returned without reprocessing
3. **Idempotency Key**: Uses payment reference as the unique identifier

**Implementation:**
```python
async def process_webhook_idempotent(event, reference, payload):
    # Check Redis cache
    cached = await redis.get(f"webhook:processed:{reference}")
    if cached:
        return json.loads(cached)  # Return cached result
    
    # Process webhook
    result = await forward_to_laravel(event, reference, payload)
    
    # Cache result for 24 hours
    await redis.setex(f"webhook:processed:{reference}", 86400, json.dumps(result))
    
    return result
```

## Error Handling

All endpoints return consistent error responses:

```json
{
  "success": false,
  "detail": "Error message"
}
```

**HTTP Status Codes:**
- `200`: Success
- `400`: Bad request (invalid payload)
- `401`: Unauthorized (invalid signature)
- `422`: Validation error (invalid input)
- `500`: Internal server error

## Testing

Run tests:
```bash
pytest tests/test_payment_service.py -v
pytest tests/test_payment_routes.py -v
```

**Test Coverage:**
- Payment initialization
- Payment verification
- Payout processing
- Bank account resolution
- Webhook signature verification
- Webhook idempotency (Property 8)
- Laravel backend integration
- Error handling

## Security

- **Webhook Signature Verification**: All webhooks are verified using HMAC-SHA512
- **HTTPS Only**: All payment gateway communications use HTTPS
- **Amount Validation**: Amounts must be positive
- **Account Number Validation**: Must be 10 digits
- **Rate Limiting**: Recommended to implement rate limiting at API gateway level

## Integration with Laravel Backend

Webhooks are automatically forwarded to the Laravel backend:

```http
POST {LARAVEL_API_URL}/webhooks/payment
Authorization: Bearer {LARAVEL_API_KEY}
Content-Type: application/json

{
  "event": "charge.success",
  "reference": "...",
  "data": {...},
  "timestamp": "2024-01-15T10:30:00Z"
}
```

## Monitoring

Key metrics to monitor:
- Payment initialization success rate
- Payment verification latency
- Webhook processing time
- Payout success rate
- Redis cache hit rate (for webhook idempotency)
- Laravel backend forwarding success rate

## Troubleshooting

### Webhook Signature Verification Fails

Check that `PAYSTACK_WEBHOOK_SECRET` matches the secret in your Paystack dashboard.

### Payment Initialization Fails

Verify that `PAYSTACK_SECRET_KEY` is correct and has the necessary permissions.

### Payout Fails

Ensure your Paystack account has sufficient balance and the recipient bank account is valid.

### Duplicate Webhook Processing

The service automatically handles duplicates using Redis cache. Check Redis connectivity if issues persist.

## Development

Start the service:
```bash
uvicorn app.main:app --reload --port 8001
```

Access API documentation:
- Swagger UI: http://localhost:8001/docs
- ReDoc: http://localhost:8001/redoc
