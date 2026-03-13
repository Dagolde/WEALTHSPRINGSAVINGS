# Implementation Plan: Rotational Contribution App

## Overview

This implementation plan breaks down the Rotational Contribution App (Ajo Platform) into sequential, testable tasks. The system consists of a Laravel backend API, FastAPI microservices for payment processing and scheduling, a Flutter mobile app, and PostgreSQL database with Redis caching.

The implementation follows a layered approach: infrastructure setup, database schema, core backend services, microservices, mobile app, and finally integration testing and deployment.

## Tasks

- [x] 1. Project setup and infrastructure
  - [x] 1.1 Initialize Laravel project with PHP 8.2+
    - Set up Laravel 10+ project structure
    - Configure environment files for development, staging, production
    - Install core dependencies (Sanctum, Queue, Events, Notifications)
    - Set up code style tools (PHP CS Fixer, Psalm)
    - _Requirements: Design - Architecture Layer_

  - [x] 1.2 Initialize FastAPI microservices project
    - Set up FastAPI project structure with Python 3.9+
    - Configure virtual environment and dependencies (Celery, Redis, SQLAlchemy, Pydantic)
    - Set up project structure for payment, scheduler, fraud detection, and notification services
    - Configure environment variables and settings management
    - _Requirements: Design - Microservices Layer_

  - [x] 1.3 Set up database infrastructure
    - Configure PostgreSQL database connection
    - Set up Redis for caching and queue management
    - Configure database connection pooling
    - Set up separate read replica configuration (for future scaling)
    - _Requirements: Design - Data Layer_

  - [x] 1.4 Configure development environment
    - Create Docker Compose configuration for local development
    - Set up database seeding and factory classes
    - Configure API documentation (Swagger/OpenAPI)
    - Set up Git hooks for code quality checks
    - _Requirements: Design - Deployment Architecture_


- [ ] 2. Database schema and migrations
  - [x] 2.1 Create users table migration
    - Implement users table with fields: id, name, email, phone, password, kyc_status, kyc_document_url, wallet_balance, status, timestamps
    - Add unique constraints on email and phone
    - Add indexes for email, phone, and kyc_status
    - _Requirements: Design - User Model, Property 1_

  - [ ]* 2.2 Write property test for user uniqueness
    - **Property 1: User Registration Uniqueness**
    - **Validates: Design Property 1**

  - [x] 2.3 Create bank_accounts table migration
    - Implement bank_accounts table with foreign key to users
    - Add fields: account_name, account_number, bank_name, bank_code, is_verified, is_primary
    - Add unique constraint on (user_id, account_number, bank_code)
    - _Requirements: Design - Bank Account Model_

  - [x] 2.4 Create groups table migration
    - Implement groups table with fields: name, description, group_code, contribution_amount, total_members, current_members, cycle_days, frequency, start_date, end_date, status, created_by
    - Add unique constraint on group_code
    - Add indexes for group_code and status
    - _Requirements: Design - Group Model, Property 4_

  - [x] 2.5 Create group_members table migration
    - Implement group_members table with foreign keys to groups and users
    - Add fields: position_number, payout_day, has_received_payout, payout_received_at, joined_at, status
    - Add unique constraints on (group_id, user_id) and (group_id, position_number)
    - _Requirements: Design - Group Member Model, Property 5, Property 6_

  - [x] 2.6 Create contributions table migration
    - Implement contributions table with foreign keys to groups and users
    - Add fields: amount, payment_method, payment_reference, payment_status, contribution_date, paid_at
    - Add unique constraints on payment_reference and (group_id, user_id, contribution_date)
    - Add indexes for payment_reference and contribution_date
    - _Requirements: Design - Contribution Model, Property 7, Property 8_

  - [x] 2.7 Create payouts table migration
    - Implement payouts table with foreign keys to groups and users
    - Add fields: amount, payout_day, payout_date, status, payout_method, payout_reference, failure_reason, processed_at
    - Add unique constraint on payout_reference
    - Add indexes for (group_id, payout_date) and status
    - _Requirements: Design - Payout Model, Property 9, Property 10_

  - [x] 2.8 Create wallet_transactions table migration
    - Implement wallet_transactions table with foreign key to users
    - Add fields: type, amount, balance_before, balance_after, purpose, reference, metadata, status
    - Add unique constraint on reference
    - Add composite index on (user_id, created_at)
    - _Requirements: Design - Wallet Transaction Model, Property 12, Property 13_

  - [x] 2.9 Create withdrawals table migration
    - Implement withdrawals table with foreign keys to users, bank_accounts, and admin users
    - Add fields: amount, status, admin_approval_status, approved_by, approved_at, rejection_reason, payment_reference, processed_at
    - Add indexes for status and admin_approval_status
    - _Requirements: Design - Withdrawal Model, Property 14_

  - [x] 2.10 Create notifications and audit_logs table migrations
    - Implement notifications table with user_id, type, title, message, data, channels, read_at, sent_at
    - Implement audit_logs table with user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent
    - Add appropriate indexes for query performance
    - _Requirements: Design - Notification Model, Audit Log Model_


- [ ] 3. User management backend (Laravel)
  - [x] 3.1 Implement User model and authentication
    - Create User model with relationships (bankAccounts, groups, contributions, payouts)
    - Implement password hashing and verification
    - Set up Laravel Sanctum for JWT token authentication
    - Create authentication middleware
    - _Requirements: Design - User Management Component, Property 2_

  - [ ]* 3.2 Write property test for authentication token validity
    - **Property 2: Authentication Token Validity**
    - **Validates: Design Property 2**

  - [x] 3.3 Implement user registration API endpoint
    - Create POST /api/v1/auth/register endpoint
    - Implement validation for name, email, phone, password
    - Generate and send OTP for email/phone verification
    - Return JWT token on successful registration
    - _Requirements: Design - User Management Component, Property 1_

  - [x] 3.4 Implement user login API endpoint
    - Create POST /api/v1/auth/login endpoint
    - Validate credentials and return JWT token
    - Implement rate limiting (5 attempts per 15 minutes)
    - Log authentication attempts for security monitoring
    - _Requirements: Design - User Management Component, Property 2_

  - [x] 3.5 Implement KYC submission and verification
    - Create POST /api/v1/user/kyc/submit endpoint for document upload
    - Implement file upload to secure storage (S3 or local encrypted storage)
    - Create GET /api/v1/user/kyc/status endpoint
    - Implement KYC status transitions (pending → verified/rejected)
    - _Requirements: Design - User Management Component, Property 3_

  - [ ]* 3.6 Write property test for KYC status transitions
    - **Property 3: KYC Status Transition**
    - **Validates: Design Property 3**

  - [x] 3.7 Implement bank account linking
    - Create POST /api/v1/user/bank-account endpoint
    - Integrate with payment gateway to resolve and verify account details
    - Implement account verification workflow
    - Create GET /api/v1/user/bank-accounts endpoint to list linked accounts
    - _Requirements: Design - User Management Component_

  - [x] 3.8 Implement user profile management
    - Create GET /api/v1/user/profile endpoint
    - Create PUT /api/v1/user/profile endpoint for updates
    - Implement validation and sanitization for profile data
    - Log profile changes in audit_logs table
    - _Requirements: Design - User Management Component_

  - [ ]* 3.9 Write unit tests for user management
    - Test user registration with valid and invalid data
    - Test duplicate email/phone rejection
    - Test password hashing
    - Test login with correct and incorrect credentials
    - Test KYC document upload and status updates
    - _Requirements: Design - Testing Strategy_


- [ ] 4. Group management backend (Laravel)
  - [x] 4.1 Implement Group model and relationships
    - Create Group model with relationships (creator, members, contributions, payouts)
    - Implement group status enum (pending, active, completed, cancelled)
    - Add model scopes for filtering by status
    - _Requirements: Design - Group Management Component_

  - [x] 4.2 Implement group creation API endpoint
    - Create POST /api/v1/groups endpoint
    - Generate unique group code (8 characters, alphanumeric)
    - Validate contribution_amount, total_members, cycle_days, frequency
    - Automatically add creator as first member
    - _Requirements: Design - Group Management Component, Property 4_

  - [ ]* 4.3 Write property test for group code uniqueness
    - **Property 4: Group Code Uniqueness**
    - **Validates: Design Property 4**

  - [x] 4.4 Implement group joining API endpoint
    - Create POST /api/v1/groups/{id}/join endpoint
    - Validate group exists and is in 'pending' status
    - Check group capacity before allowing join
    - Increment current_members count atomically
    - _Requirements: Design - Group Management Component, Property 5_

  - [ ]* 4.5 Write property test for group membership capacity
    - **Property 5: Group Membership Capacity**
    - **Validates: Design Property 5**

  - [x] 4.6 Implement position assignment and group start
    - Create POST /api/v1/groups/{id}/start endpoint
    - Verify group is full (current_members == total_members)
    - Randomly assign unique position numbers (1 to N) to all members
    - Calculate payout_day for each member (start_date + position - 1)
    - Update group status to 'active' and set start_date
    - _Requirements: Design - Group Management Component, Property 6_

  - [ ]* 4.7 Write property test for position assignment completeness
    - **Property 6: Position Assignment Completeness**
    - **Validates: Design Property 6**

  - [x] 4.8 Implement group listing and details endpoints
    - Create GET /api/v1/groups endpoint (list user's groups)
    - Create GET /api/v1/groups/{id} endpoint (group details)
    - Create GET /api/v1/groups/{id}/members endpoint
    - Create GET /api/v1/groups/{id}/schedule endpoint (payout schedule)
    - Implement pagination and filtering
    - _Requirements: Design - Group Management Component_

  - [ ]* 4.9 Write unit tests for group management
    - Test group creation with valid and invalid data
    - Test group code uniqueness
    - Test joining full vs non-full groups
    - Test position assignment algorithm
    - Test payout schedule calculation
    - _Requirements: Design - Testing Strategy_


- [ ] 5. Contribution management backend (Laravel)
  - [x] 5.1 Implement Contribution model and relationships
    - Create Contribution model with relationships to Group and User
    - Implement payment status enum (pending, successful, failed)
    - Add model scopes for filtering by date and status
    - _Requirements: Design - Contribution Component_

  - [x] 5.2 Implement contribution recording API endpoint
    - Create POST /api/v1/contributions endpoint
    - Validate user is member of the group and group is active
    - Check for duplicate contribution on same date
    - Deduct amount from user's wallet or initiate payment gateway transaction
    - Create contribution record with 'pending' status
    - _Requirements: Design - Contribution Component, Property 7_

  - [ ]* 5.3 Write property test for daily contribution uniqueness
    - **Property 7: Daily Contribution Uniqueness**
    - **Validates: Design Property 7**

  - [x] 5.4 Implement payment verification webhook handler
    - Create POST /api/v1/webhooks/paystack endpoint
    - Verify webhook signature for security
    - Implement idempotent webhook processing (check if already processed)
    - Update contribution status to 'successful' on payment confirmation
    - Credit wallet if payment method was card/bank transfer
    - _Requirements: Design - Payment Gateway Component, Property 8_

  - [ ]* 5.5 Write property test for payment webhook idempotency
    - **Property 8: Payment Webhook Idempotency**
    - **Validates: Design Property 8**

  - [x] 5.6 Implement contribution history and tracking endpoints
    - Create GET /api/v1/contributions endpoint (user's contribution history)
    - Create GET /api/v1/groups/{groupId}/contributions endpoint (group contributions)
    - Create GET /api/v1/contributions/missed endpoint (missed contributions)
    - Implement pagination and date filtering
    - _Requirements: Design - Contribution Component_

  - [x] 5.7 Implement contribution verification endpoint
    - Create POST /api/v1/contributions/verify endpoint
    - Verify payment reference with payment gateway
    - Update contribution status based on gateway response
    - Handle failed payments and retry logic
    - _Requirements: Design - Contribution Component_

  - [ ]* 5.8 Write unit tests for contribution management
    - Test contribution recording with sufficient wallet balance
    - Test contribution rejection for duplicate same-day contribution
    - Test webhook processing with valid and invalid signatures
    - Test idempotent webhook handling
    - Test missed contribution detection
    - _Requirements: Design - Testing Strategy_


- [ ] 6. Wallet management backend (Laravel)
  - [x] 6.1 Implement wallet service with transaction management
    - Create WalletService class with methods: fundWallet, debitWallet, creditWallet, getBalance
    - Implement database transactions with pessimistic locking for wallet operations
    - Record balance_before and balance_after for each transaction
    - Generate unique transaction references
    - _Requirements: Design - Wallet Component, Property 12, Property 13_

  - [ ]* 6.2 Write property test for wallet balance invariant
    - **Property 12: Wallet Balance Invariant**
    - **Validates: Design Property 12**

  - [ ]* 6.3 Write property test for wallet transaction audit trail
    - **Property 13: Wallet Transaction Audit Trail**
    - **Validates: Design Property 13**

  - [x] 6.4 Implement wallet funding API endpoint
    - Create POST /api/v1/wallet/fund endpoint
    - Integrate with payment gateway to initialize payment
    - Return payment authorization URL to client
    - Process webhook to credit wallet on successful payment
    - _Requirements: Design - Wallet Component_

  - [x] 6.5 Implement wallet withdrawal API endpoint
    - Create POST /api/v1/wallet/withdraw endpoint
    - Validate withdrawal amount against wallet balance
    - Create withdrawal record with 'pending' status
    - Implement admin approval workflow for withdrawals
    - _Requirements: Design - Wallet Component, Property 14_

  - [ ]* 6.6 Write property test for withdrawal validation
    - **Property 14: Withdrawal Validation**
    - **Validates: Design Property 14**

  - [x] 6.7 Implement wallet balance and transaction history endpoints
    - Create GET /api/v1/wallet/balance endpoint
    - Create GET /api/v1/wallet/transactions endpoint with pagination
    - Create GET /api/v1/wallet/transactions/{id} endpoint for transaction details
    - Implement caching for wallet balance (5-minute TTL)
    - _Requirements: Design - Wallet Component_

  - [ ]* 6.8 Write unit tests for wallet management
    - Test wallet funding with successful payment
    - Test wallet debit with sufficient and insufficient balance
    - Test transaction audit trail (balance_before and balance_after)
    - Test withdrawal validation
    - Test concurrent wallet operations with locking
    - _Requirements: Design - Testing Strategy_


- [ ] 7. Payout management backend (Laravel)
  - [x] 7.1 Implement Payout model and relationships
    - Create Payout model with relationships to Group and User
    - Implement payout status enum (pending, processing, successful, failed)
    - Add model scopes for filtering by status and date
    - _Requirements: Design - Payout Component_

  - [x] 7.2 Implement payout eligibility verification
    - Create PayoutService class with verifyPayoutEligibility method
    - Check that all group members have contributed for the payout day
    - Verify the designated member hasn't already received payout
    - Validate group is in 'active' status
    - _Requirements: Design - Payout Component, Property 9_

  - [ ]* 7.3 Write property test for payout eligibility
    - **Property 9: Payout Eligibility**
    - **Validates: Design Property 9**

  - [x] 7.4 Implement payout calculation and processing
    - Create calculateDailyPayout method to determine payout amount
    - Calculate amount as (contribution_amount × total_members)
    - Create processPayout method to credit user's wallet
    - Update payout status and mark member as has_received_payout
    - _Requirements: Design - Payout Component, Property 10_

  - [ ]* 7.5 Write property test for payout amount correctness
    - **Property 10: Payout Amount Correctness**
    - **Validates: Design Property 10**

  - [x] 7.6 Implement payout failure handling and retry
    - Handle payout processing failures gracefully
    - Mark payout as 'failed' with failure_reason
    - Implement retry mechanism for failed payouts
    - Create POST /api/v1/payouts/{id}/retry endpoint for manual retry
    - _Requirements: Design - Payout Component, Property 11_

  - [ ]* 7.7 Write property test for payout retry after failure
    - **Property 11: Payout Retry After Failure**
    - **Validates: Design Property 11**

  - [x] 7.8 Implement payout schedule and history endpoints
    - Create GET /api/v1/payouts/schedule/{groupId} endpoint
    - Create GET /api/v1/payouts/history endpoint (user's payout history)
    - Create GET /api/v1/payouts/{id} endpoint for payout details
    - Implement pagination and filtering
    - _Requirements: Design - Payout Component_

  - [ ]* 7.9 Write unit tests for payout management
    - Test payout eligibility verification with complete and incomplete contributions
    - Test payout amount calculation
    - Test payout processing and wallet crediting
    - Test payout failure handling
    - Test payout retry mechanism
    - _Requirements: Design - Testing Strategy_


- [x] 8. Checkpoint - Core backend functionality complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 9. Payment gateway integration (FastAPI microservice)
  - [x] 9.1 Set up FastAPI payment service structure
    - Create FastAPI application with payment service routes
    - Implement PaymentService class with Paystack/Flutterwave integration
    - Configure payment gateway credentials from environment variables
    - Set up error handling and logging
    - _Requirements: Design - Payment Gateway Component_

  - [x] 9.2 Implement payment initialization
    - Create POST /api/v1/payments/initialize endpoint
    - Integrate with Paystack/Flutterwave initialize payment API
    - Return authorization URL and payment reference
    - Store payment metadata for verification
    - _Requirements: Design - Payment Gateway Component_

  - [x] 9.3 Implement payment verification
    - Create GET /api/v1/payments/verify/{reference} endpoint
    - Integrate with payment gateway verify API
    - Return payment status and transaction details
    - Handle payment gateway errors gracefully
    - _Requirements: Design - Payment Gateway Component_

  - [x] 9.4 Implement webhook handler for payment gateway
    - Create POST /webhooks/payment endpoint
    - Verify webhook signature using payment gateway secret
    - Parse webhook payload and extract payment details
    - Forward verified payment data to Laravel backend
    - Implement idempotent webhook processing
    - _Requirements: Design - Payment Gateway Component, Property 8_

  - [x] 9.5 Implement payout initiation to bank accounts
    - Create POST /api/v1/payments/payout endpoint
    - Integrate with payment gateway transfer/payout API
    - Validate bank account details before initiating payout
    - Return payout reference and status
    - _Requirements: Design - Payment Gateway Component_

  - [x] 9.6 Implement bank account resolution
    - Create GET /api/v1/payments/banks endpoint to list supported banks
    - Create POST /api/v1/payments/resolve-account endpoint
    - Integrate with payment gateway account resolution API
    - Return account name for verification
    - _Requirements: Design - Payment Gateway Component_

  - [ ]* 9.7 Write unit tests for payment service
    - Test payment initialization with valid and invalid data
    - Test payment verification
    - Test webhook signature verification
    - Test idempotent webhook processing
    - Test payout initiation
    - Test bank account resolution
    - _Requirements: Design - Testing Strategy_


- [x] 10. Scheduler service (FastAPI with Celery)
  - [x] 10.1 Set up Celery with Redis broker
    - Configure Celery application with Redis as message broker
    - Set up Celery Beat for scheduled tasks
    - Configure task queues (high, default, low priority)
    - Set up task monitoring and logging
    - _Requirements: Design - Microservices Layer_

  - [x] 10.2 Implement daily payout processing task
    - Create process_daily_payouts Celery task
    - Schedule task to run daily at configured time (e.g., 12:00 AM)
    - Query all active groups with payouts due for current date
    - For each group, verify all contributions received
    - Call Laravel backend API to process eligible payouts
    - _Requirements: Design - Payout Component, Property 9_

  - [x] 10.3 Implement contribution reminder task
    - Create send_contribution_reminders Celery task
    - Schedule task to run daily at configured time (e.g., 9:00 AM)
    - Query all active groups and identify members who haven't contributed
    - Call notification service to send reminders
    - _Requirements: Design - Notification Component, Property 15_

  - [x] 10.4 Implement missed contribution detection task
    - Create detect_missed_contributions Celery task
    - Schedule task to run daily at end of day (e.g., 11:00 PM)
    - Identify members who missed their daily contribution
    - Send missed contribution alerts
    - Log missed contributions for reporting
    - _Requirements: Design - Notification Component, Property 15_

  - [x] 10.5 Implement group cycle completion task
    - Create check_group_completion Celery task
    - Schedule task to run daily
    - Identify groups that have completed their cycle
    - Verify all members received payouts
    - Update group status to 'completed'
    - Send completion notifications to all members
    - _Requirements: Design - Group Management Component, Property 18_

  - [ ]* 10.6 Write property test for group cycle completion invariant
    - **Property 18: Group Cycle Completion Invariant**
    - **Validates: Design Property 18**

  - [ ]* 10.7 Write unit tests for scheduler service
    - Test daily payout processing task
    - Test contribution reminder task
    - Test missed contribution detection
    - Test group cycle completion
    - Test task scheduling and execution
    - _Requirements: Design - Testing Strategy_


- [x] 11. Notification service (FastAPI)
  - [x] 11.1 Set up notification service infrastructure
    - Create FastAPI notification service application
    - Configure Firebase Cloud Messaging (FCM) for push notifications
    - Configure SMS gateway (Termii or Africa's Talking)
    - Configure email service (SendGrid or AWS SES)
    - _Requirements: Design - Notification Component_

  - [x] 11.2 Implement NotificationDispatcher class
    - Create send_push_notification method with FCM integration
    - Create send_sms method with SMS gateway integration
    - Create send_email method with email service integration
    - Create send_multi_channel method to dispatch across all channels
    - Implement error handling and retry logic for failed notifications
    - _Requirements: Design - Notification Component, Property 15_

  - [ ]* 11.3 Write property test for notification delivery
    - **Property 15: Notification Delivery**
    - **Validates: Design Property 15**

  - [x] 11.4 Implement notification API endpoints
    - Create POST /api/v1/notifications/send endpoint
    - Create POST /api/v1/notifications/push endpoint (push only)
    - Create POST /api/v1/notifications/sms endpoint (SMS only)
    - Create POST /api/v1/notifications/email endpoint (email only)
    - Implement request validation and authentication
    - _Requirements: Design - Notification Component_

  - [x] 11.5 Implement notification templates
    - Create templates for contribution reminders
    - Create templates for payout notifications
    - Create templates for missed contribution alerts
    - Create templates for group invitations
    - Create templates for KYC status updates
    - Create templates for withdrawal confirmations
    - _Requirements: Design - Notification Component_

  - [x] 11.6 Integrate notification service with Laravel backend
    - Create Laravel notification channels for push, SMS, email
    - Implement NotificationService in Laravel to call FastAPI endpoints
    - Set up queue jobs for asynchronous notification sending
    - Implement notification logging and tracking
    - _Requirements: Design - Notification Component_

  - [ ]* 11.7 Write unit tests for notification service
    - Test push notification sending
    - Test SMS sending
    - Test email sending
    - Test multi-channel notification dispatch
    - Test notification template rendering
    - Test error handling and retry logic
    - _Requirements: Design - Testing Strategy_


- [x] 12. Fraud detection service (FastAPI)
  - [x] 12.1 Set up fraud detection service infrastructure
    - Create FastAPI fraud detection service application
    - Set up database connection for fraud pattern analysis
    - Configure Redis for caching fraud detection rules
    - Set up logging for fraud alerts
    - _Requirements: Design - Fraud Detection Component_

  - [x] 12.2 Implement fraud detection rules engine
    - Create FraudDetectionService class
    - Implement rule: multiple failed payment attempts (>3 in 1 hour)
    - Implement rule: rapid account creation from same device/IP
    - Implement rule: unusual withdrawal patterns
    - Implement rule: duplicate bank account usage across multiple users
    - Implement rule: contribution pattern anomalies
    - _Requirements: Design - Fraud Detection Component, Property 17_

  - [ ]* 12.3 Write property test for fraud detection flagging
    - **Property 17: Fraud Detection Flagging**
    - **Validates: Design Property 17**

  - [x] 12.4 Implement fraud analysis endpoints
    - Create POST /api/v1/fraud/analyze-user endpoint
    - Create POST /api/v1/fraud/analyze-payment endpoint
    - Create POST /api/v1/fraud/check-duplicate-accounts endpoint
    - Create POST /api/v1/fraud/flag-activity endpoint
    - Return fraud risk score and flagged rules
    - _Requirements: Design - Fraud Detection Component_

  - [x] 12.5 Integrate fraud detection with Laravel backend
    - Call fraud detection service during user registration
    - Call fraud detection service during payment processing
    - Call fraud detection service during withdrawal requests
    - Implement automatic actions based on fraud score (flag, suspend, block)
    - _Requirements: Design - Fraud Detection Component_

  - [ ]* 12.6 Write unit tests for fraud detection service
    - Test multiple failed payment detection
    - Test duplicate account detection
    - Test unusual withdrawal pattern detection
    - Test fraud score calculation
    - Test automatic flagging and actions
    - _Requirements: Design - Testing Strategy_


- [-] 13. Admin dashboard backend (Laravel)
  - [x] 13.1 Implement admin authentication and authorization
    - Create admin role and permissions system
    - Implement admin-only middleware
    - Create admin user seeder for initial setup
    - Implement admin login with separate authentication guard
    - _Requirements: Design - Admin Dashboard Component_

  - [x] 13.2 Implement admin dashboard statistics endpoints
    - Create GET /api/v1/admin/dashboard/stats endpoint
    - Return user statistics (total, active, suspended, KYC pending)
    - Return group statistics (total, active, completed)
    - Return transaction statistics (total volume, success rate)
    - Return system health metrics
    - _Requirements: Design - Admin Dashboard Component_

  - [x] 13.3 Implement admin user management endpoints
    - Create GET /api/v1/admin/users endpoint (list all users with filters)
    - Create GET /api/v1/admin/users/{id} endpoint (user details)
    - Create PUT /api/v1/admin/users/{id}/suspend endpoint
    - Create PUT /api/v1/admin/users/{id}/activate endpoint
    - Implement audit logging for all admin actions
    - _Requirements: Design - Admin Dashboard Component, Property 16_

  - [ ]* 13.4 Write property test for user suspension enforcement
    - **Property 16: User Suspension Enforcement**
    - **Validates: Design Property 16**

  - [x] 13.5 Implement admin KYC approval workflow
    - Create GET /api/v1/admin/kyc/pending endpoint (list pending KYC submissions)
    - Create POST /api/v1/admin/kyc/{id}/approve endpoint
    - Create POST /api/v1/admin/kyc/{id}/reject endpoint with reason
    - Send notification to user on KYC status change
    - _Requirements: Design - Admin Dashboard Component, Property 3_

  - [x] 13.6 Implement admin withdrawal approval workflow
    - Create GET /api/v1/admin/withdrawals/pending endpoint
    - Create POST /api/v1/admin/withdrawals/{id}/approve endpoint
    - Create POST /api/v1/admin/withdrawals/{id}/reject endpoint with reason
    - Process approved withdrawals through payment gateway
    - _Requirements: Design - Admin Dashboard Component_

  - [x] 13.7 Implement admin group and transaction monitoring
    - Create GET /api/v1/admin/groups endpoint (list all groups with filters)
    - Create GET /api/v1/admin/groups/{id} endpoint (group details and members)
    - Create GET /api/v1/admin/transactions endpoint (all transactions with filters)
    - Create GET /api/v1/admin/disputes endpoint (flagged issues)
    - Create POST /api/v1/admin/disputes/{id}/resolve endpoint
    - _Requirements: Design - Admin Dashboard Component_

  - [x] 13.8 Implement admin analytics endpoints
    - Create GET /api/v1/admin/analytics/users endpoint (user growth, retention)
    - Create GET /api/v1/admin/analytics/groups endpoint (group performance)
    - Create GET /api/v1/admin/analytics/transactions endpoint (transaction trends)
    - Create GET /api/v1/admin/analytics/revenue endpoint (platform revenue)
    - Implement date range filtering and export functionality
    - _Requirements: Design - Admin Dashboard Component_

  - [ ]* 13.9 Write unit tests for admin dashboard
    - Test admin authentication and authorization
    - Test user suspension and activation
    - Test KYC approval and rejection
    - Test withdrawal approval workflow
    - Test statistics calculation
    - Test audit logging for admin actions
    - _Requirements: Design - Testing Strategy_


- [x] 14. Checkpoint - Backend and microservices complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 15. Flutter mobile app - Project setup and architecture
  - [x] 15.1 Initialize Flutter project
    - Create Flutter project with latest stable SDK
    - Configure project structure (features, core, shared)
    - Set up dependency injection (GetIt or Riverpod)
    - Configure environment variables for dev, staging, production
    - _Requirements: Design - Presentation Layer_

  - [x] 15.2 Set up state management
    - Choose and configure state management solution (Riverpod or Bloc)
    - Create base state classes and providers
    - Set up error handling and loading states
    - Implement state persistence for offline support
    - _Requirements: Design - Presentation Layer_

  - [x] 15.3 Set up networking and API client
    - Configure Dio HTTP client with interceptors
    - Implement authentication interceptor (JWT token injection)
    - Implement error handling interceptor
    - Implement logging interceptor for debugging
    - Create API service classes for each backend module
    - _Requirements: Design - Presentation Layer_

  - [x] 15.4 Set up local storage and caching
    - Configure Hive or SQLite for local data storage
    - Configure flutter_secure_storage for sensitive data (tokens, credentials)
    - Implement cache manager for API responses
    - Set up offline data synchronization strategy
    - _Requirements: Design - Presentation Layer_

  - [x] 15.5 Set up navigation and routing
    - Configure app routing with named routes
    - Implement authentication guard for protected routes
    - Set up deep linking for notifications
    - Create navigation service for programmatic navigation
    - _Requirements: Design - Presentation Layer_

  - [x] 15.6 Set up theme and design system
    - Create app theme with colors, typography, spacing
    - Implement light and dark mode support
    - Create reusable UI components (buttons, cards, inputs)
    - Set up responsive layout utilities
    - _Requirements: Design - Presentation Layer_


- [x] 16. Flutter mobile app - Authentication module
  - [x] 16.1 Implement authentication data models and repositories
    - Create User model with JSON serialization
    - Create AuthRepository with login, register, logout methods
    - Implement token storage and retrieval
    - Implement automatic token refresh logic
    - _Requirements: Design - User Management Component_

  - [x] 16.2 Implement authentication state management
    - Create AuthProvider/Bloc for authentication state
    - Implement login state (idle, loading, success, error)
    - Implement registration state
    - Implement logout functionality
    - Persist authentication state across app restarts
    - _Requirements: Design - User Management Component_

  - [x] 16.3 Build login screen UI
    - Create login screen with email and password fields
    - Implement form validation
    - Add loading indicator during authentication
    - Display error messages for failed login
    - Add "Forgot Password" link
    - _Requirements: Design - User Management Component_

  - [x] 16.4 Build registration screen UI
    - Create registration screen with name, email, phone, password fields
    - Implement form validation (email format, password strength)
    - Add loading indicator during registration
    - Display error messages for validation failures
    - Navigate to OTP verification screen on success
    - _Requirements: Design - User Management Component_

  - [x] 16.5 Build OTP verification screen
    - Create OTP input screen with 6-digit code entry
    - Implement OTP verification API call
    - Add resend OTP functionality with countdown timer
    - Navigate to home screen on successful verification
    - _Requirements: Design - User Management Component_

  - [x] 16.6 Build profile screen
    - Create profile screen displaying user information
    - Implement profile editing functionality
    - Add profile picture upload
    - Display KYC status badge
    - Add logout button
    - _Requirements: Design - User Management Component_


- [x] 17. Flutter mobile app - KYC and bank account module
  - [x] 17.1 Implement KYC data models and repositories
    - Create KYC model with document upload fields
    - Create KYCRepository with submit and status check methods
    - Implement file upload functionality
    - _Requirements: Design - User Management Component_

  - [x] 17.2 Build KYC submission screen
    - Create KYC form with document upload fields
    - Implement image picker for document capture/selection
    - Add document preview before submission
    - Display upload progress indicator
    - Show success/error messages
    - _Requirements: Design - User Management Component_

  - [x] 17.3 Build KYC status screen
    - Display current KYC status (pending, verified, rejected)
    - Show rejection reason if applicable
    - Add resubmit option for rejected KYC
    - Display verification timeline
    - _Requirements: Design - User Management Component_

  - [x] 17.4 Implement bank account data models and repositories
    - Create BankAccount model with JSON serialization
    - Create BankAccountRepository with add, list, verify methods
    - Implement bank list fetching
    - Implement account number resolution
    - _Requirements: Design - User Management Component_

  - [x] 17.5 Build bank account linking screen
    - Create form with bank selection dropdown
    - Add account number input with validation
    - Implement account name resolution and display
    - Add loading indicator during verification
    - Display linked bank accounts list
    - _Requirements: Design - User Management Component_


- [x] 18. Flutter mobile app - Group management module
  - [x] 18.1 Implement group data models and repositories
    - Create Group model with JSON serialization
    - Create GroupMember model
    - Create GroupRepository with create, join, list, details methods
    - Implement group code generation and validation
    - _Requirements: Design - Group Management Component_

  - [x] 18.2 Implement group state management
    - Create GroupProvider/Bloc for group operations
    - Implement group list state (loading, loaded, error)
    - Implement group creation state
    - Implement group joining state
    - Cache group data for offline access
    - _Requirements: Design - Group Management Component_

  - [x] 18.3 Build group creation screen
    - Create form with group name, description, contribution amount, member count, cycle days
    - Implement form validation
    - Add frequency selection (daily, weekly)
    - Display generated group code on success
    - Add share group code functionality
    - _Requirements: Design - Group Management Component_

  - [x] 18.4 Build group joining screen
    - Create screen with group code input
    - Implement group code validation
    - Display group details before joining
    - Show confirmation dialog
    - Navigate to group details on successful join
    - _Requirements: Design - Group Management Component_

  - [x] 18.5 Build group list screen
    - Display list of user's groups with status badges
    - Implement pull-to-refresh
    - Add filtering by status (pending, active, completed)
    - Show group summary (contribution amount, members, next payout)
    - Navigate to group details on tap
    - _Requirements: Design - Group Management Component_

  - [x] 18.6 Build group details screen
    - Display group information (name, code, contribution amount, cycle days)
    - Show member list with positions and payout days
    - Display contribution status for current day
    - Show payout schedule calendar
    - Add "Start Group" button for creator when group is full
    - Add "Make Contribution" button for active groups
    - _Requirements: Design - Group Management Component_

  - [x] 18.7 Build payout schedule screen
    - Display calendar view of payout schedule
    - Highlight current day and upcoming payouts
    - Show member details for each payout day
    - Display contribution status for each day
    - Add filtering and search functionality
    - _Requirements: Design - Group Management Component_


- [x] 19. Flutter mobile app - Contribution module
  - [x] 19.1 Implement contribution data models and repositories
    - Create Contribution model with JSON serialization
    - Create ContributionRepository with record, verify, history methods
    - Implement payment method selection logic
    - _Requirements: Design - Contribution Component_

  - [x] 19.2 Implement contribution state management
    - Create ContributionProvider/Bloc for contribution operations
    - Implement contribution recording state
    - Implement contribution history state
    - Handle payment gateway redirects
    - _Requirements: Design - Contribution Component_

  - [x] 19.3 Build contribution screen
    - Display group details and contribution amount
    - Show payment method selection (wallet, card, bank transfer)
    - Display wallet balance if wallet payment selected
    - Add "Pay Now" button
    - Show loading indicator during payment processing
    - _Requirements: Design - Contribution Component_

  - [x] 19.4 Implement payment gateway integration
    - Integrate Paystack/Flutterwave Flutter SDK
    - Handle payment initialization and authorization
    - Implement WebView for card payment flow
    - Handle payment success/failure callbacks
    - Verify payment status after completion
    - _Requirements: Design - Payment Gateway Component_

  - [x] 19.5 Build contribution history screen
    - Display list of user's contributions with dates and amounts
    - Show payment status badges (pending, successful, failed)
    - Implement filtering by group and date range
    - Add pull-to-refresh
    - Show contribution details on tap
    - _Requirements: Design - Contribution Component_

  - [x] 19.6 Build missed contributions screen
    - Display list of missed contributions by group
    - Show missed dates and amounts
    - Add "Pay Now" button for each missed contribution
    - Display total missed amount
    - _Requirements: Design - Contribution Component_


- [x] 20. Flutter mobile app - Wallet module
  - [x] 20.1 Implement wallet data models and repositories
    - Create WalletTransaction model with JSON serialization
    - Create Withdrawal model
    - Create WalletRepository with fund, withdraw, balance, transactions methods
    - _Requirements: Design - Wallet Component_

  - [x] 20.2 Implement wallet state management
    - Create WalletProvider/Bloc for wallet operations
    - Implement wallet balance state with auto-refresh
    - Implement transaction history state
    - Implement withdrawal state
    - _Requirements: Design - Wallet Component_

  - [x] 20.3 Build wallet dashboard screen
    - Display wallet balance prominently
    - Show quick action buttons (Fund Wallet, Withdraw)
    - Display recent transactions list
    - Add "View All Transactions" link
    - Implement pull-to-refresh for balance
    - _Requirements: Design - Wallet Component_

  - [x] 20.4 Build wallet funding screen
    - Create form with amount input
    - Display payment method selection
    - Show funding fee if applicable
    - Integrate with payment gateway for funding
    - Display success/error messages
    - _Requirements: Design - Wallet Component_

  - [x] 20.5 Build withdrawal screen
    - Create form with amount input and bank account selection
    - Validate withdrawal amount against wallet balance
    - Display withdrawal fee if applicable
    - Show confirmation dialog before submission
    - Display pending approval message
    - _Requirements: Design - Wallet Component_

  - [x] 20.6 Build transaction history screen
    - Display list of all wallet transactions
    - Show transaction type badges (credit, debit)
    - Implement filtering by type and date range
    - Add search functionality
    - Show transaction details on tap
    - _Requirements: Design - Wallet Component_


- [x] 21. Flutter mobile app - Notifications module
  - [x] 21.1 Set up Firebase Cloud Messaging (FCM)
    - Configure Firebase project for Android and iOS
    - Add Firebase configuration files to Flutter project
    - Implement FCM token registration
    - Send FCM token to backend on login
    - _Requirements: Design - Notification Component_

  - [x] 21.2 Implement notification handling
    - Create NotificationService for FCM message handling
    - Handle foreground notifications with local notification display
    - Handle background notifications
    - Handle notification tap actions (deep linking)
    - Store notification history locally
    - _Requirements: Design - Notification Component_

  - [x] 21.3 Build notifications screen
    - Display list of all notifications
    - Show unread badge count
    - Implement mark as read functionality
    - Add filtering by notification type
    - Show notification details on tap
    - Implement pull-to-refresh
    - _Requirements: Design - Notification Component_

  - [x] 21.4 Implement notification preferences
    - Create notification settings screen
    - Add toggles for push, SMS, email notifications
    - Implement notification type preferences (contributions, payouts, etc.)
    - Save preferences to backend
    - _Requirements: Design - Notification Component_


- [x] 22. Flutter mobile app - Home and dashboard
  - [x] 22.1 Build home dashboard screen
    - Display wallet balance card
    - Show active groups summary
    - Display upcoming payouts
    - Show pending contributions alert
    - Add quick action buttons (Create Group, Join Group, Make Contribution)
    - Implement bottom navigation bar
    - _Requirements: Design - Presentation Layer_

  - [x] 22.2 Build navigation structure
    - Implement bottom navigation with tabs (Home, Groups, Wallet, Profile)
    - Add app drawer with additional menu items
    - Implement navigation state persistence
    - Add back button handling
    - _Requirements: Design - Presentation Layer_

  - [x] 22.3 Implement error handling and user feedback
    - Create error dialog component
    - Create success snackbar component
    - Implement loading overlays
    - Add empty state screens
    - Implement retry mechanisms for failed operations
    - _Requirements: Design - Presentation Layer_

  - [x] 22.4 Implement offline support
    - Cache API responses for offline access
    - Display offline indicator when network unavailable
    - Queue operations for sync when online
    - Implement data synchronization on reconnection
    - _Requirements: Design - Presentation Layer_


- [x] 23. Checkpoint - Mobile app core features complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 24. Integration testing and quality assurance
  - [x] 24.1 Write integration tests for complete user flows
    - Test complete registration and login flow
    - Test group creation, joining, and starting flow
    - Test contribution payment flow (wallet and card)
    - Test payout processing flow
    - Test wallet funding and withdrawal flow
    - _Requirements: Design - Testing Strategy_

  - [x]* 24.2 Write property test for contribution accounting invariant
    - **Property 19: Contribution Accounting Invariant**
    - **Validates: Design Property 19**

  - [x]* 24.3 Write property test for data serialization round-trip
    - **Property 20: Data Serialization Round-Trip**
    - **Validates: Design Property 20**

  - [x] 24.4 Perform end-to-end testing
    - Test complete user journey from registration to payout receipt
    - Test multi-user group scenarios
    - Test concurrent contribution scenarios
    - Test payment gateway integration with test cards
    - Test notification delivery across all channels
    - _Requirements: Design - Testing Strategy_

  - [x] 24.5 Perform security testing
    - Test authentication and authorization
    - Test API rate limiting
    - Test input validation and sanitization
    - Test SQL injection prevention
    - Test XSS prevention
    - Test CSRF protection
    - _Requirements: Design - Security Considerations_

  - [x] 24.6 Perform performance testing
    - Load test API endpoints (100+ concurrent users)
    - Test database query performance
    - Test caching effectiveness
    - Test payment gateway response times
    - Identify and optimize bottlenecks
    - _Requirements: Design - Performance Optimization_

  - [x] 24.7 Perform mobile app testing
    - Test on multiple Android devices and versions
    - Test on multiple iOS devices and versions
    - Test offline functionality
    - Test push notification delivery
    - Test app performance and memory usage
    - _Requirements: Design - Presentation Layer_


- [ ] 25. Deployment preparation and infrastructure
  - [ ] 25.1 Set up production database
    - Provision PostgreSQL database (AWS RDS or DigitalOcean Managed Database)
    - Configure database backups (daily full, hourly incremental)
    - Set up read replica for scaling
    - Configure connection pooling (PgBouncer)
    - Run database migrations
    - _Requirements: Design - Deployment Strategy_

  - [ ] 25.2 Set up Redis cache and queue
    - Provision Redis instance (AWS ElastiCache or DigitalOcean Managed Redis)
    - Configure Redis for caching and queue management
    - Set up Redis persistence and backups
    - Configure Redis connection pooling
    - _Requirements: Design - Deployment Strategy_

  - [ ] 25.3 Set up application servers
    - Provision application servers (AWS EC2 or DigitalOcean Droplets)
    - Install PHP 8.2+, Nginx, and required extensions
    - Install Python 3.9+ and required packages
    - Configure server firewall and security groups
    - Set up SSL certificates (Let's Encrypt)
    - _Requirements: Design - Deployment Strategy_

  - [ ] 25.4 Configure load balancer
    - Set up Nginx load balancer
    - Configure health check endpoints
    - Implement sticky sessions if needed
    - Configure SSL termination
    - Set up auto-scaling rules
    - _Requirements: Design - Deployment Strategy_

  - [ ] 25.5 Set up CI/CD pipeline
    - Configure GitHub Actions or GitLab CI
    - Create pipeline for running tests on push
    - Create pipeline for building Docker images
    - Create pipeline for deploying to staging
    - Create pipeline for deploying to production (with approval)
    - _Requirements: Design - Deployment Strategy_

  - [ ] 25.6 Configure monitoring and logging
    - Set up application performance monitoring (New Relic or Datadog)
    - Configure centralized logging (ELK stack or CloudWatch)
    - Set up uptime monitoring (Pingdom or UptimeRobot)
    - Configure alerting rules for critical issues
    - Set up custom metrics dashboard (Grafana)
    - _Requirements: Design - Monitoring and Observability_

  - [ ] 25.7 Configure backup and disaster recovery
    - Set up automated database backups with off-site storage
    - Configure application code backups
    - Set up user upload backups (S3 with versioning)
    - Document disaster recovery procedures
    - Test backup restoration process
    - _Requirements: Design - Backup and Disaster Recovery_


- [ ] 26. Production deployment
  - [ ] 26.1 Deploy Laravel backend to production
    - Build production Docker image
    - Deploy to application servers
    - Run database migrations
    - Clear and cache configuration
    - Restart queue workers
    - Verify deployment with smoke tests
    - _Requirements: Design - Deployment Strategy_

  - [ ] 26.2 Deploy FastAPI microservices to production
    - Build production Docker images for each service
    - Deploy payment service
    - Deploy scheduler service with Celery workers
    - Deploy notification service
    - Deploy fraud detection service
    - Verify all services are running and healthy
    - _Requirements: Design - Deployment Strategy_

  - [ ] 26.3 Configure production environment variables
    - Set all required environment variables
    - Configure payment gateway production keys
    - Configure notification service production keys
    - Configure database connection strings
    - Configure Redis connection strings
    - Verify all configurations are correct
    - _Requirements: Design - Deployment Strategy_

  - [ ] 26.4 Deploy Flutter mobile app
    - Build Android APK/AAB for Google Play Store
    - Build iOS IPA for Apple App Store
    - Submit to app stores for review
    - Configure app store listings with screenshots and descriptions
    - Set up app analytics (Firebase Analytics or Mixpanel)
    - _Requirements: Design - Presentation Layer_

  - [ ] 26.5 Perform production smoke tests
    - Test user registration and login
    - Test group creation and joining
    - Test contribution payment with real payment gateway (small amount)
    - Test wallet funding and withdrawal
    - Test notification delivery
    - Verify all monitoring and logging is working
    - _Requirements: Design - Deployment Strategy_

  - [ ] 26.6 Set up production monitoring and alerts
    - Verify all monitoring dashboards are displaying data
    - Test alert notifications for critical issues
    - Set up on-call rotation for production support
    - Document incident response procedures
    - _Requirements: Design - Monitoring and Observability_


- [ ] 27. Final checkpoint and launch preparation
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP delivery
- Each task references specific design sections and properties for traceability
- Property tests validate universal correctness properties across randomized inputs
- Unit tests validate specific examples and edge cases
- Checkpoints ensure incremental validation and provide opportunities for user feedback
- The implementation follows a layered approach: infrastructure → backend → microservices → mobile app → testing → deployment
- All financial operations use database transactions with pessimistic locking to prevent race conditions
- Payment gateway integration uses webhooks for asynchronous payment confirmation
- The scheduler service runs daily tasks for payout processing and notifications
- The mobile app supports offline functionality with data synchronization
- Security is prioritized throughout with authentication, authorization, encryption, and fraud detection
- Monitoring and logging are configured for production observability and incident response

## Implementation Guidelines

1. **Database Transactions**: Always wrap financial operations in database transactions with proper locking
2. **Idempotency**: Ensure all payment-related operations are idempotent (can be safely retried)
3. **Error Handling**: Implement comprehensive error handling with user-friendly messages
4. **Validation**: Validate all user inputs on both client and server side
5. **Testing**: Write tests before or alongside implementation (TDD approach recommended)
6. **Security**: Never store sensitive data in plain text, always use encryption
7. **Performance**: Implement caching for frequently accessed data
8. **Logging**: Log all critical operations for audit trail and debugging
9. **Documentation**: Document all API endpoints with request/response examples
10. **Code Quality**: Follow coding standards and use linters/formatters consistently

## Technology Stack Summary

- **Backend API**: Laravel 10+ (PHP 8.2+)
- **Microservices**: FastAPI (Python 3.9+)
- **Mobile App**: Flutter (latest stable SDK)
- **Database**: PostgreSQL 14+
- **Cache/Queue**: Redis 7+
- **Payment Gateway**: Paystack or Flutterwave
- **Notifications**: Firebase Cloud Messaging, SendGrid, Termii/Africa's Talking
- **Monitoring**: New Relic/Datadog, ELK Stack/CloudWatch, Grafana
- **CI/CD**: GitHub Actions or GitLab CI
- **Hosting**: AWS, DigitalOcean, or VPS with Docker

## Estimated Timeline

- Phase 1 (Tasks 1-8): 3-4 weeks - Core backend infrastructure and user/group/contribution management
- Phase 2 (Tasks 9-13): 2-3 weeks - Microservices and admin dashboard
- Phase 3 (Tasks 15-22): 4-5 weeks - Mobile app development
- Phase 4 (Tasks 24-27): 2-3 weeks - Testing, deployment, and launch
- **Total**: 11-15 weeks for MVP

## Success Criteria

- All core features implemented and tested
- All property tests passing (100+ iterations each)
- Unit test coverage > 80%
- API response time < 500ms (p95)
- Payment success rate > 95%
- Zero critical security vulnerabilities
- Mobile app approved on both app stores
- Production monitoring and alerting operational
- Disaster recovery procedures documented and tested
