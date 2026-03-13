# Task 24: Quick Test Reference Guide

## Quick Start

### Run All Automated Tests

```bash
cd backend
php artisan test
```

### Run Specific Test Suites

```bash
# Integration tests only
php artisan test --testsuite=Integration

# Property tests only
php artisan test --testsuite=Property

# Security tests only
php artisan test --testsuite=Security

# Unit tests only
php artisan test --testsuite=Unit

# Feature tests only
php artisan test --testsuite=Feature
```

### Run Specific Test Files

```bash
# Complete user flow tests
php artisan test tests/Integration/CompleteUserFlowTest.php

# Multi-user scenario tests
php artisan test tests/Integration/MultiUserScenarioTest.php

# Contribution accounting property tests
php artisan test tests/Property/ContributionAccountingPropertyTest.php

# Data serialization property tests
php artisan test tests/Property/DataSerializationPropertyTest.php

# Security tests
php artisan test tests/Security/SecurityTestSuite.php
```

### Run Specific Test Methods

```bash
# Registration and login flow
php artisan test --filter=complete_registration_and_login_flow

# Group creation flow
php artisan test --filter=complete_group_creation_joining_and_starting_flow

# Contribution payment flow
php artisan test --filter=complete_contribution_payment_flow

# Payout processing flow
php artisan test --filter=complete_payout_processing_flow

# Wallet operations flow
php artisan test --filter=complete_wallet_funding_and_withdrawal_flow

# Multi-user concurrent contributions
php artisan test --filter=multiple_users_can_contribute_to_group_concurrently

# Authentication security
php artisan test --filter=authentication_fails_with_invalid_credentials
```

### Run with Coverage

```bash
# Generate coverage report
php artisan test --coverage

# Generate coverage with minimum threshold
php artisan test --coverage --min=80

# Generate HTML coverage report
php artisan test --coverage-html coverage-report
```

### Run with Verbose Output

```bash
# Verbose output
php artisan test -v

# Very verbose output
php artisan test -vv

# Debug output
php artisan test -vvv
```

## Test File Locations

```
backend/tests/
├── Integration/
│   ├── CompleteUserFlowTest.php          # End-to-end user flows
│   └── MultiUserScenarioTest.php         # Multi-user scenarios
├── Property/
│   ├── ContributionAccountingPropertyTest.php  # Property 19
│   └── DataSerializationPropertyTest.php       # Property 20
├── Security/
│   └── SecurityTestSuite.php             # Security tests
├── Unit/
│   └── [Existing unit tests]
└── Feature/
    └── [Existing feature tests]
```

## Test Categories

### Integration Tests (12 tests)

**Complete User Flows:**
1. ✅ Registration and login flow
2. ✅ Group creation, joining, and starting flow
3. ✅ Contribution payment flow
4. ✅ Payout processing flow
5. ✅ Wallet funding and withdrawal flow

**Multi-User Scenarios:**
6. ✅ Multiple users contributing concurrently
7. ✅ Concurrent contribution attempts prevention
8. ✅ Multiple groups running simultaneously
9. ✅ Payout processing for multiple groups
10. ✅ Complete group cycle simulation
11. ✅ Missed contribution handling
12. ✅ Wallet balance consistency

### Property Tests (9 tests)

**Contribution Accounting (Property 19):**
1. ✅ Total contributions equals sum of individual contributions
2. ✅ Contribution amounts sum correctly
3. ✅ Daily contribution count never exceeds member count

**Data Serialization (Property 20):**
4. ✅ User serialization round-trip
5. ✅ Group serialization round-trip
6. ✅ Contribution serialization round-trip
7. ✅ Payout serialization round-trip
8. ✅ Bank account serialization round-trip
9. ✅ Complex nested object serialization

### Security Tests (15 tests)

**Authentication & Authorization:**
1. ✅ Invalid credentials rejection
2. ✅ Token requirement enforcement
3. ✅ User data access control
4. ✅ Group member authorization
5. ✅ Admin role enforcement
6. ✅ Suspended user restrictions

**Rate Limiting:**
7. ✅ Login attempt rate limiting

**Input Validation:**
8. ✅ SQL injection prevention
9. ✅ XSS prevention
10. ✅ Amount field validation
11. ✅ Mass assignment protection

**Data Protection:**
12. ✅ Password hashing verification
13. ✅ Sensitive data exclusion
14. ✅ CSRF protection

**Webhook Security:**
15. ✅ Signature verification

## Common Test Commands

### Development Workflow

```bash
# Run tests before committing
php artisan test

# Run tests with coverage check
php artisan test --coverage --min=80

# Run only failing tests
php artisan test --filter=failing

# Run tests in parallel (faster)
php artisan test --parallel

# Stop on first failure
php artisan test --stop-on-failure
```

### Debugging Tests

```bash
# Run single test with verbose output
php artisan test --filter=test_name -vvv

# Run with PHPUnit debug output
php artisan test --debug

# Run with testdox format (readable output)
php artisan test --testdox
```

### CI/CD Commands

```bash
# Run all tests (CI mode)
php artisan test --ci

# Run with JUnit XML output
php artisan test --log-junit junit.xml

# Run with coverage for CI
php artisan test --coverage --coverage-clover coverage.xml
```

## Expected Test Results

### All Tests Passing

```
   PASS  Tests\Integration\CompleteUserFlowTest
  ✓ complete registration and login flow
  ✓ complete group creation joining and starting flow
  ✓ complete contribution payment flow
  ✓ complete payout processing flow
  ✓ complete wallet funding and withdrawal flow

   PASS  Tests\Integration\MultiUserScenarioTest
  ✓ multiple users can contribute to group concurrently
  ✓ concurrent contribution attempts by same user should fail
  ✓ multiple groups can run concurrently
  ✓ payout processing handles multiple eligible groups
  ✓ complete group cycle with all members
  ✓ handling missed contributions in multi user scenario
  ✓ wallet balance consistency across concurrent operations

   PASS  Tests\Property\ContributionAccountingPropertyTest
  ✓ total contributions equals sum of individual contributions
  ✓ contribution amounts sum correctly across members
  ✓ contribution count per day never exceeds member count

   PASS  Tests\Property\DataSerializationPropertyTest
  ✓ user serialization round trip preserves data
  ✓ group serialization round trip preserves data
  ✓ contribution serialization round trip preserves data
  ✓ payout serialization round trip preserves data
  ✓ bank account serialization round trip preserves data
  ✓ complex nested object serialization preserves structure

   PASS  Tests\Security\SecurityTestSuite
  ✓ authentication fails with invalid credentials
  ✓ protected endpoints require valid token
  ✓ users can only access their own data
  ✓ only group members can view group details
  ✓ login endpoint has rate limiting
  ✓ input validation prevents sql injection
  ✓ xss prevention in user input
  ✓ csrf protection on state changing operations
  ✓ passwords are hashed not stored plaintext
  ✓ sensitive data not exposed in api responses
  ✓ admin endpoints require admin role
  ✓ suspended users cannot perform actions
  ✓ input validation on amount fields
  ✓ mass assignment protection
  ✓ webhook signature verification

  Tests:  36 passed
  Duration: ~2 minutes
```

## Troubleshooting

### Common Issues

**Issue: Tests fail with database errors**
```bash
# Solution: Clear and migrate database
php artisan migrate:fresh
php artisan test
```

**Issue: Tests fail with cache errors**
```bash
# Solution: Clear cache
php artisan cache:clear
php artisan config:clear
php artisan test
```

**Issue: Tests are slow**
```bash
# Solution: Use parallel testing
php artisan test --parallel

# Or use in-memory database (already configured)
# Check phpunit.xml for DB_DATABASE=:memory:
```

**Issue: Property tests fail randomly**
```bash
# Solution: Increase iterations or check generators
# Edit test file and adjust Generator parameters
```

## Performance Benchmarks

### Expected Test Execution Times

| Test Suite | Tests | Avg Time | Max Time |
|------------|-------|----------|----------|
| Integration | 12 | 15-20s | 45s |
| Property | 9 | 10-15s | 30s |
| Security | 15 | 5-10s | 20s |
| **Total** | **36** | **30-45s** | **2 min** |

### Optimization Tips

1. Use `--parallel` flag for faster execution
2. Use in-memory SQLite (already configured)
3. Disable unnecessary services in test environment
4. Use database transactions for cleanup (already implemented)
5. Mock external services (payment gateway, notifications)

## Next Steps

After running automated tests:

1. ✅ Verify all tests pass
2. ⏳ Execute manual end-to-end tests (see TASK_24_INTEGRATION_TESTING_GUIDE.md)
3. ⏳ Perform performance testing
4. ⏳ Conduct mobile app testing
5. ⏳ Document test results

## Additional Resources

- **Full Testing Guide:** `TASK_24_INTEGRATION_TESTING_GUIDE.md`
- **Completion Summary:** `TASK_24_COMPLETION_SUMMARY.md`
- **Design Document:** `.kiro/specs/rotational-contribution-app/design.md`
- **Requirements:** `.kiro/specs/rotational-contribution-app/requirements.md`

## Support

For issues or questions:
1. Check test output for specific error messages
2. Review test file comments for test purpose
3. Consult design document for expected behavior
4. Check existing feature tests for examples

---

**Quick Tip:** Run `php artisan test --help` for all available options.
