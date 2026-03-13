# Fraud Detection Service

The Fraud Detection Service is a FastAPI microservice that analyzes user behavior and transactions to identify and prevent fraudulent activities in the Rotational Contribution App.

## Features

### Detection Rules

The service implements 5 core fraud detection rules:

1. **Multiple Failed Payments** - Flags users with >3 failed payment attempts in 1 hour
2. **Rapid Account Creation** - Detects multiple accounts created from the same device/IP in 24 hours
3. **Unusual Withdrawal Patterns** - Identifies withdrawals that are 3x the user's average and >₦10,000
4. **Duplicate Bank Accounts** - Flags bank accounts used by multiple users
5. **Contribution Anomalies** - Detects users joining >5 groups in 24 hours

### Risk Scoring

Each triggered rule contributes to a cumulative risk score:
- **0-49**: Low risk → Approve
- **50-79**: Medium risk → Flag for review
- **80+**: High risk → Suspend account

### API Endpoints

#### 1. Analyze User Behavior
```http
POST /api/v1/fraud/analyze-user
Content-Type: application/json

{
  "user_id": 123
}
```

Returns comprehensive fraud analysis including risk score, triggered rules, and recommendation.

#### 2. Analyze Payment
```http
POST /api/v1/fraud/analyze-payment
Content-Type: application/json

{
  "user_id": 123,
  "amount": 5000.00,
  "payment_method": "wallet",
  "metadata": {}
}
```

Checks payment for fraud indicators including rapid payments and unusual amounts.

#### 3. Check Duplicate Accounts
```http
POST /api/v1/fraud/check-duplicate-accounts
Content-Type: application/json

{
  "email": "user@example.com",
  "phone": "+2348012345678",
  "device_id": "device123",
  "ip_address": "192.168.1.1"
}
```

Detects potential duplicate accounts based on device ID and IP address.

#### 4. Analyze Withdrawal
```http
POST /api/v1/fraud/analyze-withdrawal
Content-Type: application/json

{
  "user_id": 123,
  "amount": 50000.00,
  "bank_account": "1234567890"
}
```

Checks withdrawal for fraud indicators including new accounts, large amounts, and shared accounts.

#### 5. Flag Activity
```http
POST /api/v1/fraud/flag-activity
Content-Type: application/json

{
  "user_id": 123,
  "activity_type": "suspicious_payment",
  "details": {
    "reason": "Multiple failed attempts",
    "count": 5
  }
}
```

Manually flag suspicious activity for admin review.

## Laravel Integration

The fraud detection service is integrated into the Laravel backend at key points:

### 1. User Registration (AuthController)
- Checks for duplicate accounts during registration
- Analyzes user behavior after account creation
- Automatically suspends high-risk accounts

### 2. Payment Processing (ContributionController)
- Analyzes payment fraud indicators before processing
- Blocks high-risk payments
- Flags medium-risk payments for review

### 3. Withdrawals (WalletController)
- Checks withdrawal fraud indicators
- Blocks high-risk withdrawals
- Flags medium-risk withdrawals for review

## Configuration

Add to `backend/config/services.php`:

```php
'fraud_detection' => [
    'url' => env('FRAUD_DETECTION_SERVICE_URL', 'http://localhost:8000'),
    'timeout' => env('FRAUD_DETECTION_TIMEOUT', 10),
],
```

Add to `.env`:

```env
FRAUD_DETECTION_SERVICE_URL=http://localhost:8000
FRAUD_DETECTION_TIMEOUT=10
```

## Database Models

### FraudFlag
Stores fraud flag records for admin review:
- `user_id`: User being flagged
- `rule_type`: Type of fraud rule triggered
- `risk_score`: Calculated risk score
- `status`: pending, under_review, confirmed, false_positive, resolved
- `activity_type`: Type of suspicious activity
- `activity_data`: JSON data about the activity
- `triggered_rules`: List of rules that triggered

### FraudPattern
Tracks fraud patterns for user behavior analysis:
- `user_id`: User ID
- `pattern_type`: Type of pattern detected
- `occurrence_count`: Number of occurrences
- `risk_level`: low, medium, high, critical

### FraudRule
Configurable fraud detection rules:
- `rule_type`: Type of fraud rule
- `is_enabled`: Whether rule is active
- `risk_score_weight`: Points to add to risk score
- `threshold_config`: Rule-specific thresholds
- `auto_flag`: Automatically flag suspicious activity
- `auto_suspend`: Automatically suspend high-risk accounts

## Caching

The service uses Redis for caching:
- Failed payment counts (5 minutes TTL)
- Rapid payment detection (5 minutes TTL)
- Fraud detection results (5 minutes TTL)

## Testing

### Property-Based Tests
```bash
cd microservices
python -m pytest tests/test_fraud_properties.py -v
```

Tests Property 17: Fraud Detection Flagging across 100+ randomized inputs.

### Unit Tests
```bash
cd microservices
python -m pytest tests/test_fraud_service.py -v
```

Tests specific fraud detection rules and scenarios.

## Monitoring

All fraud detection events are logged:
- User behavior analysis
- Payment fraud checks
- Withdrawal fraud checks
- Automatic suspensions
- Manual reviews

Check logs for:
```
Fraud detection result
User suspended due to fraud detection
User flagged for review
Suspicious activity flagged
```

## Error Handling

The service gracefully handles failures:
- Returns default "approve" response if service is unavailable
- Logs all errors for investigation
- Does not block legitimate transactions due to service failures

## Performance

- Average response time: <100ms
- Caching reduces database queries
- Async database operations
- Connection pooling for high throughput

## Security

- All endpoints require authentication
- Webhook signature verification
- Rate limiting on API endpoints
- Encrypted sensitive data
- Audit trail for all fraud flags
