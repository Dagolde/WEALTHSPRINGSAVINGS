# Rotational Contribution App - Design Document

## Overview

The Rotational Contribution App (Ajo Platform) is a digital rotational savings system that enables users to participate in structured group savings cycles. The platform automates the traditional Ajo system where participants contribute fixed amounts daily, and pooled funds are distributed to one member per day according to their assigned position in the rotation cycle.

### Core Concept

In a typical scenario with 10 participants contributing ₦1,000 daily over 10 days:
- Each member contributes ₦1,000 every day
- Total pool per day: ₦10,000
- Each member receives ₦10,000 once during the cycle based on their assigned position
- The cycle repeats or concludes based on group configuration

### System Goals

1. **Automation**: Eliminate manual tracking and distribution of contributions
2. **Trust**: Provide transparent, verifiable contribution and payout records
3. **Accessibility**: Enable participation through mobile devices with multiple payment options
4. **Security**: Ensure financial transactions are secure and compliant
5. **Scalability**: Support multiple concurrent groups with varying configurations

### Key Features

- User registration with KYC verification
- Group creation and management with flexible configurations
- Automated contribution tracking and payout scheduling
- Integrated wallet system for seamless transactions
- Multi-channel notifications (push, SMS, email)
- Payment gateway integration (Paystack/Flutterwave)
- Admin dashboard for monitoring and dispute resolution
- Fraud detection and prevention mechanisms

## Architecture

### High-Level Architecture

The system follows a 3-tier architecture pattern:

```
┌─────────────────────────────┐
│   Mobile App (Flutter)      │
│   - Android & iOS           │
└──────────────┬──────────────┘
               │ REST API
               │ (HTTPS/JSON)
               ▼
┌─────────────────────────────┐
│   Backend Services          │
│   ┌─────────────────────┐   │
│   │ Laravel API Server  │   │
│   │ - Authentication    │   │
│   │ - Business Logic    │   │
│   │ - API Endpoints     │   │
│   └─────────────────────┘   │
│   ┌─────────────────────┐   │
│   │ FastAPI Services    │   │
│   │ - Payment Processor │   │
│   │ - Scheduler         │   │
│   │ - Fraud Detection   │   │
│   │ - Notifications     │   │
│   └─────────────────────┘   │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│   Data Layer                │
│   - MySQL/PostgreSQL        │
│   - Redis (Cache/Queue)     │
└─────────────────────────────┘
               │
               ▼
┌─────────────────────────────┐
│   External Services         │
│   - Paystack/Flutterwave    │
│   - SMS Gateway             │
│   - Email Service           │
│   - Push Notifications      │
└─────────────────────────────┘
```

### Architecture Layers

#### 1. Presentation Layer (Flutter Mobile App)

**Responsibilities:**
- User interface rendering
- User input handling
- Local state management
- API communication
- Offline data caching

**Technology Stack:**
- Flutter SDK (latest stable)
- Dart programming language
- State Management: Riverpod or Bloc
- HTTP client: Dio
- Local storage: Hive or SQLite
- Secure storage: flutter_secure_storage

**Key Components:**
- Authentication module
- Group management UI
- Contribution tracking screens
- Wallet interface
- Notification handler
- Payment integration UI

#### 2. Application Layer (Laravel Backend)

**Responsibilities:**
- RESTful API endpoints
- Business logic orchestration
- Authentication and authorization
- Request validation
- Response formatting
- Database transactions

**Technology Stack:**
- Laravel 10+ (PHP 8+)
- Laravel Sanctum for API authentication
- Laravel Queue for background jobs
- Laravel Events for decoupled operations
- Laravel Notifications for multi-channel alerts

**Core Modules:**
- User Management Service
- Group Management Service
- Contribution Service
- Payout Service
- Wallet Service
- Transaction Service
- Notification Service

#### 3. Microservices Layer (FastAPI)

**Responsibilities:**
- High-performance payment processing
- Scheduled task execution
- Real-time fraud detection
- Notification dispatching
- Analytics processing

**Technology Stack:**
- FastAPI (Python 3.9+)
- Celery for task queue
- Redis for message broker and caching
- SQLAlchemy for database ORM
- Pydantic for data validation

**Services:**
- Payment Processor Service
- Scheduler Service (Celery Beat)
- Fraud Detection Service
- Notification Dispatcher Service

#### 4. Data Layer

**Primary Database: PostgreSQL**
- Relational data storage
- ACID compliance for financial transactions
- Complex query support
- JSON field support for flexible data

**Cache & Queue: Redis**
- Session storage
- API response caching
- Task queue management
- Real-time data caching

**Storage Strategy:**
- Hot data: Redis cache (recent transactions, active sessions)
- Warm data: PostgreSQL (current cycle data)
- Cold data: Archived tables (completed cycles)

#### 5. External Integration Layer

**Payment Gateway (Paystack/Flutterwave):**
- Payment initialization
- Webhook handling
- Transaction verification
- Payout processing

**Communication Services:**
- SMS Gateway: Termii or Africa's Talking
- Email Service: SendGrid or AWS SES
- Push Notifications: Firebase Cloud Messaging (FCM)

### Deployment Architecture

```
┌─────────────────────────────────────────────┐
│   Load Balancer (Nginx)                     │
└──────────────┬──────────────────────────────┘
               │
       ┌───────┴───────┐
       │               │
       ▼               ▼
┌─────────────┐ ┌─────────────┐
│ Laravel     │ │ Laravel     │
│ Instance 1  │ │ Instance 2  │
└─────────────┘ └─────────────┘
       │               │
       └───────┬───────┘
               ▼
┌─────────────────────────────┐
│   Database Cluster          │
│   - Primary (Write)         │
│   - Replica (Read)          │
└─────────────────────────────┘
```

**Hosting Options:**
- AWS (EC2, RDS, ElastiCache, S3)
- DigitalOcean (Droplets, Managed Database)
- VPS with Docker containerization

## Components and Interfaces

### 1. User Management Component

**Purpose:** Handle user lifecycle, authentication, and profile management.

**Interfaces:**

```php
// Laravel Service Interface
interface UserServiceInterface
{
    public function register(array $userData): User;
    public function login(string $email, string $password): array; // returns token
    public function verifyKYC(int $userId, array $kycData): bool;
    public function linkBankAccount(int $userId, array $bankDetails): BankAccount;
    public function updateProfile(int $userId, array $profileData): User;
    public function getWalletBalance(int $userId): float;
}
```

**API Endpoints:**

```
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
POST   /api/v1/auth/verify-otp
GET    /api/v1/user/profile
PUT    /api/v1/user/profile
POST   /api/v1/user/kyc/submit
GET    /api/v1/user/kyc/status
POST   /api/v1/user/bank-account
GET    /api/v1/user/bank-accounts
```

**Key Operations:**
- User registration with email/phone verification
- JWT token-based authentication
- KYC document upload and verification
- Bank account linking with verification
- Profile management

### 2. Group Management Component

**Purpose:** Handle contribution group creation, membership, and lifecycle.

**Interfaces:**

```php
interface GroupServiceInterface
{
    public function createGroup(int $creatorId, array $groupData): Group;
    public function joinGroup(int $userId, string $groupCode): GroupMember;
    public function getGroupDetails(int $groupId): Group;
    public function listUserGroups(int $userId): Collection;
    public function assignPositions(int $groupId): bool;
    public function startGroup(int $groupId): bool;
    public function getGroupMembers(int $groupId): Collection;
    public function calculatePayoutSchedule(int $groupId): array;
}
```

**API Endpoints:**

```
POST   /api/v1/groups
GET    /api/v1/groups
GET    /api/v1/groups/{id}
POST   /api/v1/groups/{id}/join
POST   /api/v1/groups/{id}/start
GET    /api/v1/groups/{id}/members
GET    /api/v1/groups/{id}/schedule
PUT    /api/v1/groups/{id}
DELETE /api/v1/groups/{id}
```

**Key Operations:**
- Group creation with configurable parameters
- Member invitation and joining via group code
- Automatic position assignment
- Group status management (pending, active, completed)
- Payout schedule calculation

### 3. Contribution Component

**Purpose:** Track and process daily contributions from group members.

**Interfaces:**

```php
interface ContributionServiceInterface
{
    public function recordContribution(int $userId, int $groupId, float $amount, string $paymentRef): Contribution;
    public function verifyContribution(string $paymentRef): bool;
    public function getContributionHistory(int $userId, ?int $groupId = null): Collection;
    public function checkDailyContribution(int $userId, int $groupId, Carbon $date): bool;
    public function getMissedContributions(int $userId, int $groupId): Collection;
    public function calculateTotalContributed(int $userId, int $groupId): float;
}
```

**API Endpoints:**

```
POST   /api/v1/contributions
GET    /api/v1/contributions
GET    /api/v1/contributions/history
GET    /api/v1/groups/{groupId}/contributions
POST   /api/v1/contributions/verify
GET    /api/v1/contributions/missed
```

**Key Operations:**
- Contribution payment processing
- Payment verification via webhook
- Contribution status tracking
- Missed contribution detection
- Contribution history retrieval

### 4. Payout Component

**Purpose:** Manage automated payout distribution to eligible members.

**Interfaces:**

```php
interface PayoutServiceInterface
{
    public function calculateDailyPayout(int $groupId, Carbon $date): ?Payout;
    public function processPayout(int $payoutId): bool;
    public function getPayoutSchedule(int $groupId): Collection;
    public function getPayoutHistory(int $userId): Collection;
    public function verifyPayoutEligibility(int $userId, int $groupId, Carbon $date): bool;
    public function retryFailedPayout(int $payoutId): bool;
}
```

**API Endpoints:**

```
GET    /api/v1/payouts/schedule/{groupId}
GET    /api/v1/payouts/history
GET    /api/v1/payouts/{id}
POST   /api/v1/payouts/{id}/retry
```

**FastAPI Scheduler Service:**

```python
# Scheduled task running daily
@celery_app.task
def process_daily_payouts():
    """
    Runs daily at configured time (e.g., 12:00 AM)
    - Identifies groups with payouts due
    - Verifies all contributions received
    - Processes payout to designated member
    - Sends notifications
    """
    pass
```

**Key Operations:**
- Daily payout calculation
- Eligibility verification (all contributions received)
- Automated payout processing
- Failed payout retry mechanism
- Payout notification dispatch

### 5. Wallet Component

**Purpose:** Manage user wallet balances and transactions.

**Interfaces:**

```php
interface WalletServiceInterface
{
    public function fundWallet(int $userId, float $amount, string $paymentRef): Transaction;
    public function debitWallet(int $userId, float $amount, string $purpose): Transaction;
    public function creditWallet(int $userId, float $amount, string $purpose): Transaction;
    public function getBalance(int $userId): float;
    public function getTransactionHistory(int $userId): Collection;
    public function initiateWithdrawal(int $userId, float $amount, int $bankAccountId): Withdrawal;
    public function processWithdrawal(int $withdrawalId): bool;
}
```

**API Endpoints:**

```
POST   /api/v1/wallet/fund
POST   /api/v1/wallet/withdraw
GET    /api/v1/wallet/balance
GET    /api/v1/wallet/transactions
GET    /api/v1/wallet/transactions/{id}
```

**Key Operations:**
- Wallet funding via payment gateway
- Contribution deduction from wallet
- Payout credit to wallet
- Withdrawal to bank account
- Transaction history tracking
- Balance inquiry

### 6. Payment Gateway Component

**Purpose:** Interface with external payment providers for transactions.

**Interfaces:**

```php
interface PaymentGatewayInterface
{
    public function initializePayment(float $amount, string $email, array $metadata): array;
    public function verifyPayment(string $reference): array;
    public function initiatePayout(float $amount, string $accountNumber, string $bankCode): array;
    public function verifyPayout(string $reference): array;
    public function listBanks(): array;
    public function resolveAccountNumber(string $accountNumber, string $bankCode): array;
}
```

**FastAPI Payment Service:**

```python
class PaymentService:
    async def initialize_payment(self, amount: float, email: str, metadata: dict) -> dict:
        """Initialize payment with Paystack/Flutterwave"""
        pass
    
    async def verify_payment(self, reference: str) -> dict:
        """Verify payment status"""
        pass
    
    async def process_payout(self, amount: float, account: str, bank_code: str) -> dict:
        """Process payout to bank account"""
        pass
    
    async def handle_webhook(self, payload: dict, signature: str) -> bool:
        """Handle payment gateway webhook"""
        pass
```

**Webhook Endpoints:**

```
POST   /api/v1/webhooks/paystack
POST   /api/v1/webhooks/flutterwave
```

**Key Operations:**
- Payment initialization
- Payment verification
- Webhook processing
- Payout initiation
- Bank account resolution

### 7. Notification Component

**Purpose:** Send multi-channel notifications to users.

**Interfaces:**

```php
interface NotificationServiceInterface
{
    public function sendContributionReminder(int $userId, int $groupId): bool;
    public function sendPayoutNotification(int $userId, float $amount): bool;
    public function sendMissedContributionAlert(int $userId, int $groupId): bool;
    public function sendGroupInvitation(int $userId, int $groupId, string $inviterName): bool;
    public function sendKYCStatusUpdate(int $userId, string $status): bool;
}
```

**FastAPI Notification Service:**

```python
class NotificationDispatcher:
    async def send_push_notification(self, user_id: int, title: str, body: str, data: dict):
        """Send FCM push notification"""
        pass
    
    async def send_sms(self, phone: str, message: str):
        """Send SMS via Termii/Africa's Talking"""
        pass
    
    async def send_email(self, email: str, subject: str, template: str, data: dict):
        """Send email via SendGrid"""
        pass
    
    async def send_multi_channel(self, user_id: int, notification: Notification):
        """Send notification across all enabled channels"""
        pass
```

**Notification Types:**
- Contribution reminder (daily, before deadline)
- Payout notification (when funds are received)
- Missed contribution alert
- Group invitation
- KYC status update
- Withdrawal confirmation
- Group cycle completion

### 8. Admin Dashboard Component

**Purpose:** Provide administrative oversight and management capabilities.

**Interfaces:**

```php
interface AdminServiceInterface
{
    public function getUserStatistics(): array;
    public function getGroupStatistics(): array;
    public function getTransactionStatistics(Carbon $startDate, Carbon $endDate): array;
    public function approveKYC(int $userId): bool;
    public function rejectKYC(int $userId, string $reason): bool;
    public function suspendUser(int $userId, string $reason): bool;
    public function resolveDispute(int $disputeId, string $resolution): bool;
    public function approveWithdrawal(int $withdrawalId): bool;
    public function getSystemHealth(): array;
}
```

**Admin API Endpoints:**

```
GET    /api/v1/admin/dashboard/stats
GET    /api/v1/admin/users
GET    /api/v1/admin/users/{id}
PUT    /api/v1/admin/users/{id}/suspend
GET    /api/v1/admin/groups
GET    /api/v1/admin/groups/{id}
GET    /api/v1/admin/transactions
POST   /api/v1/admin/kyc/{id}/approve
POST   /api/v1/admin/kyc/{id}/reject
GET    /api/v1/admin/disputes
POST   /api/v1/admin/disputes/{id}/resolve
GET    /api/v1/admin/withdrawals/pending
POST   /api/v1/admin/withdrawals/{id}/approve
GET    /api/v1/admin/analytics
```

**Key Features:**
- User management and monitoring
- Group oversight
- Transaction monitoring
- KYC approval workflow
- Dispute resolution
- Withdrawal approval
- System analytics and reporting

### 9. Fraud Detection Component

**Purpose:** Identify and prevent fraudulent activities.

**FastAPI Fraud Detection Service:**

```python
class FraudDetectionService:
    async def analyze_user_behavior(self, user_id: int) -> dict:
        """Analyze user patterns for anomalies"""
        pass
    
    async def check_payment_fraud(self, payment_data: dict) -> dict:
        """Check payment for fraud indicators"""
        pass
    
    async def detect_duplicate_accounts(self, user_data: dict) -> list:
        """Detect potential duplicate accounts"""
        pass
    
    async def flag_suspicious_activity(self, activity: dict) -> bool:
        """Flag suspicious activity for review"""
        pass
```

**Fraud Detection Rules:**
- Multiple failed payment attempts
- Rapid account creation from same device/IP
- Unusual withdrawal patterns
- Duplicate bank account usage
- Contribution pattern anomalies
- Geographic location inconsistencies

**Actions:**
- Automatic flagging for admin review
- Temporary account suspension
- Additional verification requirements
- Transaction blocking

## Data Models

### User Model

```sql
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    kyc_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    kyc_document_url VARCHAR(500),
    kyc_rejection_reason TEXT,
    wallet_balance DECIMAL(15, 2) DEFAULT 0.00,
    status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    email_verified_at TIMESTAMP,
    phone_verified_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
```

### Bank Account Model

```sql
CREATE TABLE bank_accounts (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    account_name VARCHAR(255) NOT NULL,
    account_number VARCHAR(20) NOT NULL,
    bank_name VARCHAR(255) NOT NULL,
    bank_code VARCHAR(10) NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Group Model

```sql
CREATE TABLE groups (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    group_code VARCHAR(10) UNIQUE NOT NULL,
    contribution_amount DECIMAL(15, 2) NOT NULL,
    total_members INT NOT NULL,
    current_members INT DEFAULT 0,
    cycle_days INT NOT NULL,
    frequency ENUM('daily', 'weekly') DEFAULT 'daily',
    start_date DATE,
    end_date DATE,
    status ENUM('pending', 'active', 'completed', 'cancelled') DEFAULT 'pending',
    created_by BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### Group Member Model

```sql
CREATE TABLE group_members (
    id BIGSERIAL PRIMARY KEY,
    group_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    position_number INT NOT NULL,
    payout_day INT NOT NULL,
    has_received_payout BOOLEAN DEFAULT FALSE,
    payout_received_at TIMESTAMP NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'removed', 'left') DEFAULT 'active',
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_user (group_id, user_id),
    UNIQUE KEY unique_group_position (group_id, position_number)
);
```

### Contribution Model

```sql
CREATE TABLE contributions (
    id BIGSERIAL PRIMARY KEY,
    group_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    payment_method ENUM('wallet', 'card', 'bank_transfer') NOT NULL,
    payment_reference VARCHAR(255) UNIQUE NOT NULL,
    payment_status ENUM('pending', 'successful', 'failed') DEFAULT 'pending',
    contribution_date DATE NOT NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_daily_contribution (group_id, user_id, contribution_date)
);
```

### Payout Model

```sql
CREATE TABLE payouts (
    id BIGSERIAL PRIMARY KEY,
    group_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    payout_day INT NOT NULL,
    payout_date DATE NOT NULL,
    status ENUM('pending', 'processing', 'successful', 'failed') DEFAULT 'pending',
    payout_method ENUM('wallet', 'bank_transfer') NOT NULL,
    payout_reference VARCHAR(255) UNIQUE,
    failure_reason TEXT,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Wallet Transaction Model

```sql
CREATE TABLE wallet_transactions (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    type ENUM('credit', 'debit') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    balance_before DECIMAL(15, 2) NOT NULL,
    balance_after DECIMAL(15, 2) NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    reference VARCHAR(255) UNIQUE NOT NULL,
    metadata JSON,
    status ENUM('pending', 'successful', 'failed') DEFAULT 'successful',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_created (user_id, created_at)
);
```

### Withdrawal Model

```sql
CREATE TABLE withdrawals (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    bank_account_id BIGINT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    status ENUM('pending', 'approved', 'processing', 'successful', 'rejected', 'failed') DEFAULT 'pending',
    admin_approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by BIGINT NULL,
    approved_at TIMESTAMP NULL,
    rejection_reason TEXT,
    payment_reference VARCHAR(255) UNIQUE,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);
```

### Notification Model

```sql
CREATE TABLE notifications (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    channels JSON, -- ['push', 'sms', 'email']
    read_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, read_at)
);
```

### Audit Log Model

```sql
CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT NOT NULL,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_user_action (user_id, action, created_at)
);
```

### Data Relationships

```
users (1) ──────── (N) bank_accounts
users (1) ──────── (N) group_members
users (1) ──────── (N) contributions
users (1) ──────── (N) payouts
users (1) ──────── (N) wallet_transactions
users (1) ──────── (N) withdrawals
users (1) ──────── (N) notifications

groups (1) ─────── (N) group_members
groups (1) ─────── (N) contributions
groups (1) ─────── (N) payouts

group_members (N) ─ (1) users
group_members (N) ─ (1) groups

withdrawals (N) ── (1) bank_accounts
```

### Indexing Strategy

**Performance-Critical Indexes:**
- `users.email`, `users.phone` (unique, for authentication)
- `groups.group_code` (unique, for joining)
- `contributions.payment_reference` (unique, for verification)
- `contributions(group_id, user_id, contribution_date)` (composite, for daily checks)
- `wallet_transactions(user_id, created_at)` (composite, for history queries)
- `payouts(group_id, payout_date)` (composite, for scheduler)
- `group_members(group_id, position_number)` (composite, for payout order)

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property Reflection

After analyzing all acceptance criteria, I've identified the following properties and eliminated redundancies:

**Redundancies Identified:**
- Properties 5.1 and 5.2 (wallet credit/debit) are subsumed by Property 5.5 (balance calculation invariant)
- Property 3.4 (contribution history query) is a standard CRUD operation, not a critical correctness property
- Property 6.1, 6.2, 6.3 (individual notification types) can be combined into a general notification delivery property
- Properties 2.4 and 2.5 (position assignment and schedule calculation) are tightly coupled and can be combined

**Final Property Set:**

### Property 1: User Registration Uniqueness

*For any* two user registration attempts with the same email or phone number, the system should reject the second registration and maintain only one account per unique email/phone.

**Validates: Requirements 1.1, 1.2**

### Property 2: Authentication Token Validity

*For any* user with valid credentials, successful login should return a JWT token that can be used to authenticate subsequent API requests.

**Validates: Requirements 1.3**

### Property 3: KYC Status Transition

*For any* user submitting KYC documents, the system should transition the KYC status from the current state to 'pending', and admin actions should transition it to either 'verified' or 'rejected' with appropriate metadata.

**Validates: Requirements 1.4, 7.1, 7.2**

### Property 4: Group Code Uniqueness

*For any* group creation request, the system should generate a unique group code that does not conflict with any existing group code.

**Validates: Requirements 2.1**

### Property 5: Group Membership Capacity

*For any* group with defined capacity N and current membership count M, join requests should succeed when M < N and fail when M >= N.

**Validates: Requirements 2.2, 2.3**

### Property 6: Position Assignment Completeness

*For any* group with N members that is started, the system should assign exactly N unique position numbers (1 through N) such that each member has exactly one position and each position is assigned to exactly one member, with corresponding payout days calculated as (start_date + position - 1).

**Validates: Requirements 2.4, 2.5**

### Property 7: Daily Contribution Uniqueness

*For any* group, user, and date combination, the system should accept at most one successful contribution, rejecting any duplicate contribution attempts for the same day.

**Validates: Requirements 3.1, 3.2**

### Property 8: Payment Webhook Idempotency

*For any* payment reference, processing the webhook multiple times should result in the same final state as processing it once (idempotent operation).

**Validates: Requirements 3.3**

### Property 9: Payout Eligibility

*For any* group on a given payout day, the system should process the payout to the designated member if and only if all members have made their contributions for that day.

**Validates: Requirements 4.1, 4.3**

### Property 10: Payout Amount Correctness

*For any* payout processed for a group, the amount credited to the recipient's wallet should equal (contribution_amount × total_members).

**Validates: Requirements 4.2**

### Property 11: Payout Retry After Failure

*For any* payout that fails, the system should mark it as 'failed', preserve the payout record, and allow retry operations that can transition it to 'successful'.

**Validates: Requirements 4.4**

### Property 12: Wallet Balance Invariant

*For any* user at any point in time, the wallet balance should equal the sum of all credit transactions minus the sum of all debit transactions, and should never be negative.

**Validates: Requirements 5.1, 5.2, 5.3, 5.5, 9.4**

### Property 13: Wallet Transaction Audit Trail

*For any* wallet transaction (credit or debit), the system should record the balance immediately before and immediately after the transaction, such that balance_after = balance_before ± amount.

**Validates: Requirements 5.3**

### Property 14: Withdrawal Validation

*For any* withdrawal request with amount A from a user with wallet balance B, the system should reject the request if A > B and only process it if A <= B.

**Validates: Requirements 5.4**

### Property 15: Notification Delivery

*For any* system event requiring user notification (contribution reminder, payout, missed contribution), the system should dispatch notifications to all enabled channels (push, SMS, email) for that user.

**Validates: Requirements 6.1, 6.2, 6.3**

### Property 16: User Suspension Enforcement

*For any* user with status 'suspended', the system should reject attempts to make contributions, receive payouts, or initiate withdrawals.

**Validates: Requirements 7.3**

### Property 17: Fraud Detection Flagging

*For any* activity matching fraud detection rules (multiple failed payments, duplicate accounts, suspicious patterns), the system should create a flag record for admin review.

**Validates: Requirements 8.1, 8.2**

### Property 18: Group Cycle Completion Invariant

*For any* group that has completed its cycle, every member should have received exactly one payout, and the sum of all payouts should equal (contribution_amount × total_members × cycle_days).

**Validates: Requirements 9.1**

### Property 19: Contribution Accounting Invariant

*For any* active group at any point in time, the total number of recorded contributions should equal the sum of contributions made by each individual member.

**Validates: Requirements 9.2**

### Property 20: Data Serialization Round-Trip

*For any* domain object (User, Group, Contribution, Payout), serializing to JSON and then deserializing should produce an equivalent object with the same field values.

**Validates: Requirements 9.3**

## Error Handling

### Error Categories

#### 1. Validation Errors (4xx)

**Client-side errors that should be handled gracefully:**

- **400 Bad Request**: Invalid input data, malformed JSON
- **401 Unauthorized**: Missing or invalid authentication token
- **403 Forbidden**: Insufficient permissions, suspended account
- **404 Not Found**: Resource does not exist
- **409 Conflict**: Duplicate resource (email, phone, contribution)
- **422 Unprocessable Entity**: Business rule violation (insufficient balance, group full)

**Response Format:**
```json
{
  "success": false,
  "error": {
    "code": "INSUFFICIENT_BALANCE",
    "message": "Wallet balance is insufficient for this transaction",
    "details": {
      "required": 1000.00,
      "available": 500.00
    }
  }
}
```

#### 2. Server Errors (5xx)

**System-level errors requiring logging and monitoring:**

- **500 Internal Server Error**: Unexpected application error
- **502 Bad Gateway**: External service failure (payment gateway)
- **503 Service Unavailable**: System maintenance or overload
- **504 Gateway Timeout**: External service timeout

**Response Format:**
```json
{
  "success": false,
  "error": {
    "code": "INTERNAL_ERROR",
    "message": "An unexpected error occurred. Please try again later.",
    "reference": "ERR-20240115-ABC123"
  }
}
```

### Error Handling Strategies

#### Payment Gateway Failures

**Scenario:** Payment gateway is unavailable or returns error

**Strategy:**
1. Catch gateway exceptions
2. Log error with full context
3. Return user-friendly error message
4. Queue for retry if transient error
5. Send notification to admin if persistent

**Implementation:**
```php
try {
    $result = $this->paymentGateway->initializePayment($amount, $email);
} catch (GatewayException $e) {
    Log::error('Payment gateway error', [
        'user_id' => $userId,
        'amount' => $amount,
        'error' => $e->getMessage()
    ]);
    
    if ($e->isTransient()) {
        dispatch(new RetryPaymentJob($paymentData))->delay(now()->addMinutes(5));
    }
    
    throw new PaymentFailedException('Unable to process payment. Please try again.');
}
```

#### Database Transaction Failures

**Scenario:** Database transaction fails mid-operation

**Strategy:**
1. Wrap critical operations in database transactions
2. Implement automatic rollback on failure
3. Use pessimistic locking for concurrent operations
4. Implement idempotency keys for retries

**Implementation:**
```php
DB::transaction(function () use ($userId, $amount) {
    $user = User::lockForUpdate()->findOrFail($userId);
    
    if ($user->wallet_balance < $amount) {
        throw new InsufficientBalanceException();
    }
    
    $user->decrement('wallet_balance', $amount);
    
    WalletTransaction::create([
        'user_id' => $userId,
        'type' => 'debit',
        'amount' => $amount,
        'balance_before' => $user->wallet_balance + $amount,
        'balance_after' => $user->wallet_balance,
    ]);
});
```

#### Webhook Duplicate Processing

**Scenario:** Payment gateway sends duplicate webhook

**Strategy:**
1. Check for existing transaction by reference
2. Return success if already processed (idempotent)
3. Use database unique constraints as safety net
4. Log duplicate attempts for monitoring

**Implementation:**
```php
public function handleWebhook(array $payload): bool
{
    $reference = $payload['reference'];
    
    // Check if already processed
    $existing = Contribution::where('payment_reference', $reference)->first();
    if ($existing && $existing->payment_status === 'successful') {
        Log::info('Duplicate webhook ignored', ['reference' => $reference]);
        return true; // Idempotent response
    }
    
    // Process webhook...
}
```

#### Payout Processing Failures

**Scenario:** Payout to bank account fails

**Strategy:**
1. Mark payout as 'failed' with reason
2. Preserve payout record for retry
3. Credit funds back to user's wallet as fallback
4. Send notification to user and admin
5. Implement manual retry mechanism

**Implementation:**
```php
try {
    $result = $this->paymentGateway->initiatePayout($amount, $accountNumber, $bankCode);
    
    $payout->update([
        'status' => 'successful',
        'payout_reference' => $result['reference'],
        'processed_at' => now()
    ]);
} catch (PayoutException $e) {
    $payout->update([
        'status' => 'failed',
        'failure_reason' => $e->getMessage()
    ]);
    
    // Fallback: Credit to wallet
    $this->walletService->creditWallet(
        $payout->user_id,
        $payout->amount,
        "Payout for group {$payout->group->name} (bank transfer failed)"
    );
    
    // Notify user and admin
    $this->notificationService->sendPayoutFailureNotification($payout);
}
```

#### Concurrent Contribution Attempts

**Scenario:** User attempts to contribute twice simultaneously

**Strategy:**
1. Use database unique constraint on (group_id, user_id, contribution_date)
2. Catch duplicate key exception
3. Return appropriate error message
4. Use pessimistic locking for wallet operations

**Implementation:**
```php
try {
    Contribution::create([
        'group_id' => $groupId,
        'user_id' => $userId,
        'contribution_date' => now()->toDateString(),
        'amount' => $amount,
        // ...
    ]);
} catch (QueryException $e) {
    if ($e->getCode() === '23000') { // Duplicate key
        throw new DuplicateContributionException(
            'You have already contributed to this group today'
        );
    }
    throw $e;
}
```

### Logging and Monitoring

**Critical Events to Log:**
- All financial transactions (contributions, payouts, withdrawals)
- Authentication attempts (success and failure)
- Payment gateway interactions
- Webhook processing
- Admin actions (KYC approval, user suspension)
- Fraud detection triggers
- System errors and exceptions

**Log Format:**
```json
{
  "timestamp": "2024-01-15T10:30:00Z",
  "level": "info",
  "event": "contribution_recorded",
  "user_id": 123,
  "group_id": 45,
  "amount": 1000.00,
  "payment_reference": "PAY-ABC123",
  "ip_address": "192.168.1.1",
  "context": {
    "contribution_date": "2024-01-15",
    "payment_method": "wallet"
  }
}
```

**Monitoring Alerts:**
- Payment gateway failure rate > 5%
- Payout processing failures
- Database connection issues
- High API error rates (> 10%)
- Unusual transaction patterns
- System resource exhaustion

## Testing Strategy

### Dual Testing Approach

The system will employ both unit testing and property-based testing to ensure comprehensive coverage:

**Unit Tests:** Focus on specific examples, edge cases, and integration points
**Property Tests:** Verify universal properties across randomized inputs

Together, these approaches provide complementary coverage where unit tests catch concrete bugs and property tests verify general correctness.

### Property-Based Testing

**Library:** PHPUnit with `eris/eris` for property-based testing in PHP

**Configuration:**
- Minimum 100 iterations per property test
- Each test tagged with reference to design document property
- Tag format: `@group Feature: rotational-contribution-app, Property {number}: {property_text}`

**Property Test Examples:**

```php
/**
 * @test
 * @group Feature: rotational-contribution-app, Property 1: User Registration Uniqueness
 */
public function user_registration_with_duplicate_email_should_be_rejected()
{
    $this->forAll(
        Generator\string(),
        Generator\string(),
        Generator\string()
    )->then(function ($name, $email, $phone) {
        // First registration should succeed
        $user1 = $this->userService->register([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => 'password123'
        ]);
        
        $this->assertNotNull($user1);
        
        // Second registration with same email should fail
        $this->expectException(DuplicateEmailException::class);
        $this->userService->register([
            'name' => $name . '2',
            'email' => $email, // Same email
            'phone' => $phone . '2',
            'password' => 'password456'
        ]);
    });
}

/**
 * @test
 * @group Feature: rotational-contribution-app, Property 12: Wallet Balance Invariant
 */
public function wallet_balance_should_equal_sum_of_transactions()
{
    $this->forAll(
        Generator\int(1, 1000),
        Generator\seq(Generator\tuple(
            Generator\elements(['credit', 'debit']),
            Generator\int(1, 1000)
        ))
    )->then(function ($userId, $transactions) {
        $expectedBalance = 0;
        
        foreach ($transactions as [$type, $amount]) {
            if ($type === 'credit') {
                $this->walletService->creditWallet($userId, $amount, 'test');
                $expectedBalance += $amount;
            } else {
                if ($expectedBalance >= $amount) {
                    $this->walletService->debitWallet($userId, $amount, 'test');
                    $expectedBalance -= $amount;
                }
            }
        }
        
        $actualBalance = $this->walletService->getBalance($userId);
        $this->assertEquals($expectedBalance, $actualBalance);
        $this->assertGreaterThanOrEqual(0, $actualBalance);
    });
}
```

```php
/**
 * @test
 * @group Feature: rotational-contribution-app, Property 6: Position Assignment Completeness
 */
public function group_position_assignment_should_be_complete_and_unique()
{
    $this->forAll(
        Generator\int(3, 20) // Group size between 3 and 20
    )->then(function ($memberCount) {
        $group = $this->createGroupWithMembers($memberCount);
        
        $this->groupService->startGroup($group->id);
        
        $members = $this->groupService->getGroupMembers($group->id);
        
        // Check all positions from 1 to N are assigned
        $positions = $members->pluck('position_number')->sort()->values();
        $expectedPositions = range(1, $memberCount);
        
        $this->assertEquals($expectedPositions, $positions->toArray());
        
        // Check each member has unique position
        $this->assertEquals($memberCount, $positions->unique()->count());
        
        // Check payout days are calculated correctly
        foreach ($members as $member) {
            $expectedPayoutDay = $member->position_number;
            $this->assertEquals($expectedPayoutDay, $member->payout_day);
        }
    });
}

/**
 * @test
 * @group Feature: rotational-contribution-app, Property 20: Data Serialization Round-Trip
 */
public function group_serialization_should_preserve_data()
{
    $this->forAll(
        Generator\string(),
        Generator\int(100, 10000),
        Generator\int(3, 50),
        Generator\int(3, 365)
    )->then(function ($name, $amount, $members, $days) {
        $group = Group::create([
            'name' => $name,
            'contribution_amount' => $amount,
            'total_members' => $members,
            'cycle_days' => $days,
            'created_by' => 1
        ]);
        
        $serialized = json_encode($group->toArray());
        $deserialized = json_decode($serialized, true);
        
        $this->assertEquals($group->name, $deserialized['name']);
        $this->assertEquals($group->contribution_amount, $deserialized['contribution_amount']);
        $this->assertEquals($group->total_members, $deserialized['total_members']);
        $this->assertEquals($group->cycle_days, $deserialized['cycle_days']);
    });
}
```

### Unit Testing

**Test Coverage Areas:**

#### 1. User Management Tests
```php
class UserServiceTest extends TestCase
{
    /** @test */
    public function it_registers_user_with_valid_data()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+2348012345678',
            'password' => 'SecurePass123!'
        ];
        
        $user = $this->userService->register($userData);
        
        $this->assertNotNull($user);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals('pending', $user->kyc_status);
    }
    
    /** @test */
    public function it_rejects_registration_with_invalid_email()
    {
        $this->expectException(ValidationException::class);
        
        $this->userService->register([
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'phone' => '+2348012345678',
            'password' => 'SecurePass123!'
        ]);
    }
    
    /** @test */
    public function it_hashes_password_during_registration()
    {
        $user = $this->userService->register([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+2348012345678',
            'password' => 'PlainPassword'
        ]);
        
        $this->assertNotEquals('PlainPassword', $user->password);
        $this->assertTrue(Hash::check('PlainPassword', $user->password));
    }
}
```

#### 2. Group Management Tests
```php
class GroupServiceTest extends TestCase
{
    /** @test */
    public function it_creates_group_with_unique_code()
    {
        $group1 = $this->groupService->createGroup(1, [
            'name' => 'Test Group',
            'contribution_amount' => 1000,
            'total_members' => 10,
            'cycle_days' => 10
        ]);
        
        $group2 = $this->groupService->createGroup(1, [
            'name' => 'Another Group',
            'contribution_amount' => 2000,
            'total_members' => 5,
            'cycle_days' => 5
        ]);
        
        $this->assertNotEquals($group1->group_code, $group2->group_code);
    }
    
    /** @test */
    public function it_prevents_joining_full_group()
    {
        $group = $this->createFullGroup();
        
        $this->expectException(GroupFullException::class);
        $this->groupService->joinGroup(999, $group->group_code);
    }
}
```

#### 3. Contribution Tests
```php
class ContributionServiceTest extends TestCase
{
    /** @test */
    public function it_records_contribution_successfully()
    {
        $user = $this->createUser();
        $group = $this->createActiveGroup();
        
        $contribution = $this->contributionService->recordContribution(
            $user->id,
            $group->id,
            1000.00,
            'PAY-TEST-123'
        );
        
        $this->assertEquals('pending', $contribution->payment_status);
        $this->assertEquals(1000.00, $contribution->amount);
    }
    
    /** @test */
    public function it_prevents_duplicate_daily_contribution()
    {
        $user = $this->createUser();
        $group = $this->createActiveGroup();
        
        // First contribution
        $this->contributionService->recordContribution(
            $user->id,
            $group->id,
            1000.00,
            'PAY-TEST-123'
        );
        
        // Second contribution same day
        $this->expectException(DuplicateContributionException::class);
        $this->contributionService->recordContribution(
            $user->id,
            $group->id,
            1000.00,
            'PAY-TEST-456'
        );
    }
}
```

#### 4. Payout Tests
```php
class PayoutServiceTest extends TestCase
{
    /** @test */
    public function it_processes_payout_when_all_contributions_received()
    {
        $group = $this->createGroupWithAllContributions();
        $member = $group->members->first();
        
        $payout = $this->payoutService->calculateDailyPayout($group->id, now());
        
        $this->assertNotNull($payout);
        $this->assertEquals($member->user_id, $payout->user_id);
        $this->assertEquals(10000.00, $payout->amount); // 10 members × ₦1000
    }
    
    /** @test */
    public function it_delays_payout_when_contributions_missing()
    {
        $group = $this->createGroupWithMissingContributions();
        
        $payout = $this->payoutService->calculateDailyPayout($group->id, now());
        
        $this->assertNull($payout);
    }
}
```

#### 5. Wallet Tests
```php
class WalletServiceTest extends TestCase
{
    /** @test */
    public function it_funds_wallet_successfully()
    {
        $user = $this->createUser();
        $initialBalance = $user->wallet_balance;
        
        $this->walletService->fundWallet($user->id, 5000.00, 'PAY-FUND-123');
        
        $user->refresh();
        $this->assertEquals($initialBalance + 5000.00, $user->wallet_balance);
    }
    
    /** @test */
    public function it_rejects_withdrawal_with_insufficient_balance()
    {
        $user = $this->createUserWithBalance(1000.00);
        
        $this->expectException(InsufficientBalanceException::class);
        $this->walletService->initiateWithdrawal($user->id, 2000.00, 1);
    }
    
    /** @test */
    public function it_maintains_transaction_audit_trail()
    {
        $user = $this->createUserWithBalance(1000.00);
        
        $this->walletService->debitWallet($user->id, 500.00, 'Test debit');
        
        $transaction = WalletTransaction::latest()->first();
        $this->assertEquals(1000.00, $transaction->balance_before);
        $this->assertEquals(500.00, $transaction->balance_after);
    }
}
```

#### 6. Integration Tests
```php
class ContributionFlowIntegrationTest extends TestCase
{
    /** @test */
    public function complete_contribution_flow_works_end_to_end()
    {
        // Setup
        $user = $this->createVerifiedUser();
        $group = $this->createActiveGroup();
        $this->groupService->joinGroup($user->id, $group->group_code);
        $this->walletService->fundWallet($user->id, 5000.00, 'FUND-123');
        
        // Make contribution
        $contribution = $this->contributionService->recordContribution(
            $user->id,
            $group->id,
            1000.00,
            'CONTRIB-123'
        );
        
        // Simulate webhook
        $this->webhookService->handlePaystackWebhook([
            'event' => 'charge.success',
            'data' => ['reference' => 'CONTRIB-123']
        ]);
        
        // Verify
        $contribution->refresh();
        $this->assertEquals('successful', $contribution->payment_status);
        
        $user->refresh();
        $this->assertEquals(4000.00, $user->wallet_balance);
    }
}
```

### FastAPI Microservices Testing

**Python Testing with Hypothesis (Property-Based Testing):**

```python
from hypothesis import given, strategies as st
import pytest

class TestPaymentService:
    @given(
        amount=st.floats(min_value=100, max_value=1000000),
        email=st.emails()
    )
    def test_payment_initialization_returns_valid_response(self, amount, email):
        """
        Property: For any valid amount and email, payment initialization 
        should return a response with authorization_url and reference.
        """
        service = PaymentService()
        result = service.initialize_payment(amount, email, {})
        
        assert 'authorization_url' in result
        assert 'reference' in result
        assert result['amount'] == amount

    @given(reference=st.text(min_size=10, max_size=50))
    def test_webhook_processing_is_idempotent(self, reference):
        """
        Property: Processing the same webhook multiple times should 
        produce the same result (idempotent).
        """
        service = PaymentService()
        webhook_data = {
            'event': 'charge.success',
            'data': {'reference': reference, 'amount': 1000}
        }
        
        result1 = service.handle_webhook(webhook_data)
        result2 = service.handle_webhook(webhook_data)
        
        assert result1 == result2

class TestSchedulerService:
    def test_daily_payout_processing(self):
        """Unit test for daily payout scheduler"""
        scheduler = SchedulerService()
        
        # Create test data
        group = self.create_test_group_with_contributions()
        
        # Run scheduler
        scheduler.process_daily_payouts()
        
        # Verify payout was processed
        payout = Payout.objects.filter(group_id=group.id).first()
        assert payout is not None
        assert payout.status == 'successful'
```

### Test Data Management

**Factory Pattern for Test Data:**

```php
class UserFactory
{
    public static function create(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->phoneNumber(),
            'password' => Hash::make('password'),
            'kyc_status' => 'pending',
            'wallet_balance' => 0
        ], $overrides));
    }
}

class GroupFactory
{
    public static function create(array $overrides = []): Group
    {
        return Group::create(array_merge([
            'name' => fake()->words(3, true),
            'group_code' => strtoupper(Str::random(8)),
            'contribution_amount' => 1000,
            'total_members' => 10,
            'cycle_days' => 10,
            'status' => 'pending',
            'created_by' => UserFactory::create()->id
        ], $overrides));
    }
}
```

### Test Environment Setup

**Database:**
- Use SQLite in-memory database for unit tests (fast)
- Use PostgreSQL test database for integration tests (realistic)
- Reset database between tests using transactions

**External Services:**
- Mock payment gateway responses in unit tests
- Use sandbox/test mode for integration tests
- Implement fake notification services for testing

**Configuration:**

```php
// phpunit.xml
<phpunit>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
        <testsuite name="Property">
            <directory>tests/Property</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
    </php>
</phpunit>
```

### Continuous Integration

**CI Pipeline:**
1. Run linter (PHP CS Fixer, Psalm)
2. Run unit tests
3. Run property-based tests (100+ iterations)
4. Run integration tests
5. Generate coverage report (target: 80%+)
6. Run security scan (Snyk, Dependabot)

**GitHub Actions Example:**

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      postgres:
        image: postgres:14
        env:
          POSTGRES_PASSWORD: password
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo, pdo_pgsql
      
      - name: Install Dependencies
        run: composer install
      
      - name: Run Tests
        run: |
          php artisan test --testsuite=Unit
          php artisan test --testsuite=Property
          php artisan test --testsuite=Feature
      
      - name: Generate Coverage
        run: php artisan test --coverage --min=80
```

## Security Considerations

### Authentication & Authorization

**JWT Token Management:**
- Access tokens expire after 1 hour
- Refresh tokens expire after 7 days
- Tokens include user ID, role, and permissions
- Implement token blacklist for logout

**Password Security:**
- Minimum 8 characters
- Require mix of uppercase, lowercase, numbers
- Hash using bcrypt (cost factor: 12)
- Implement rate limiting on login attempts

**API Security:**
- All endpoints require authentication (except public routes)
- Role-based access control (user, admin)
- Rate limiting: 60 requests per minute per user
- CORS configuration for mobile app domains

### Data Protection

**Encryption:**
- SSL/TLS for all API communication
- Encrypt sensitive data at rest (KYC documents, bank details)
- Use Laravel's encryption for sensitive fields
- Secure key management (AWS KMS or similar)

**PII Handling:**
- Minimize collection of personal data
- Implement data retention policies
- Support user data export (GDPR compliance)
- Support account deletion with data anonymization

**Database Security:**
- Use parameterized queries (prevent SQL injection)
- Implement row-level security where applicable
- Regular database backups (encrypted)
- Separate read/write database users

### Payment Security

**Transaction Security:**
- Verify all webhook signatures
- Implement idempotency keys
- Use HTTPS for all payment communications
- Never store card details (PCI DSS compliance)
- Implement transaction amount limits

**Fraud Prevention:**
- Monitor for unusual patterns
- Implement velocity checks
- Require additional verification for large transactions
- Geographic location validation
- Device fingerprinting

### Compliance

**Regulatory Requirements:**
- KYC verification for all users
- Transaction monitoring and reporting
- Anti-money laundering (AML) checks
- Data protection compliance (NDPR/GDPR)
- Financial service licensing (as required)

**Audit Trail:**
- Log all financial transactions
- Track all admin actions
- Maintain immutable audit logs
- Regular security audits
- Penetration testing

## Performance Optimization

### Database Optimization

**Query Optimization:**
- Use eager loading to prevent N+1 queries
- Implement database indexes on frequently queried columns
- Use database views for complex reporting queries
- Implement query result caching

**Example:**
```php
// Bad: N+1 query problem
$groups = Group::all();
foreach ($groups as $group) {
    echo $group->creator->name; // Separate query for each group
}

// Good: Eager loading
$groups = Group::with('creator')->get();
foreach ($groups as $group) {
    echo $group->creator->name; // No additional queries
}
```

**Connection Pooling:**
- Use persistent database connections
- Implement read replicas for read-heavy operations
- Use connection pooling (PgBouncer for PostgreSQL)

### Caching Strategy

**Redis Caching:**

```php
// Cache user wallet balance (frequently accessed)
$balance = Cache::remember("user:{$userId}:balance", 300, function () use ($userId) {
    return $this->walletService->getBalance($userId);
});

// Cache group details
$group = Cache::remember("group:{$groupId}", 600, function () use ($groupId) {
    return Group::with('members')->find($groupId);
});

// Invalidate cache on updates
Cache::forget("user:{$userId}:balance");
```

**Cache Layers:**
- **L1 (Application):** In-memory cache for request lifecycle
- **L2 (Redis):** Shared cache across application instances
- **L3 (Database):** Query result cache

**Cache Invalidation:**
- Time-based expiration (TTL)
- Event-based invalidation (on data updates)
- Cache tags for bulk invalidation

### API Response Optimization

**Pagination:**
```php
// Paginate large result sets
$contributions = Contribution::where('user_id', $userId)
    ->orderBy('created_at', 'desc')
    ->paginate(20);

return response()->json([
    'data' => $contributions->items(),
    'meta' => [
        'current_page' => $contributions->currentPage(),
        'total' => $contributions->total(),
        'per_page' => $contributions->perPage()
    ]
]);
```

**Response Compression:**
- Enable gzip compression for API responses
- Minimize JSON payload size
- Use field selection (sparse fieldsets)

**Example:**
```
GET /api/v1/groups/123?fields=id,name,contribution_amount
```

### Background Job Processing

**Queue Configuration:**
- Use Redis for queue backend
- Implement job priorities (high, normal, low)
- Set appropriate timeouts and retry limits
- Monitor queue depth and processing time

**Job Examples:**
```php
// High priority: Payment processing
dispatch(new ProcessPaymentJob($paymentData))->onQueue('high');

// Normal priority: Notifications
dispatch(new SendNotificationJob($notification))->onQueue('default');

// Low priority: Analytics
dispatch(new UpdateAnalyticsJob($data))->onQueue('low');
```

### Load Balancing

**Horizontal Scaling:**
- Deploy multiple Laravel instances behind load balancer
- Use sticky sessions for WebSocket connections
- Implement health check endpoints
- Auto-scaling based on CPU/memory metrics

**Load Balancer Configuration (Nginx):**
```nginx
upstream backend {
    least_conn;
    server app1.example.com:8000;
    server app2.example.com:8000;
    server app3.example.com:8000;
}

server {
    listen 80;
    location / {
        proxy_pass http://backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

## Deployment Strategy

### Environment Configuration

**Environments:**
1. **Development:** Local development with hot reload
2. **Staging:** Production-like environment for testing
3. **Production:** Live environment with monitoring

**Environment Variables:**
```env
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.ajo.example.com

# Database
DB_CONNECTION=pgsql
DB_HOST=db.example.com
DB_DATABASE=ajo_production
DB_USERNAME=ajo_user
DB_PASSWORD=secure_password

# Redis
REDIS_HOST=redis.example.com
REDIS_PASSWORD=redis_password

# Payment Gateway
PAYSTACK_SECRET_KEY=sk_live_xxx
PAYSTACK_PUBLIC_KEY=pk_live_xxx

# Notifications
FCM_SERVER_KEY=xxx
SENDGRID_API_KEY=xxx
SMS_API_KEY=xxx

# Security
JWT_SECRET=xxx
ENCRYPTION_KEY=xxx
```

### Docker Deployment

**Docker Compose Configuration:**

```yaml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "8000:8000"
    environment:
      - APP_ENV=production
    depends_on:
      - db
      - redis
    volumes:
      - ./storage:/app/storage
  
  db:
    image: postgres:14
    environment:
      POSTGRES_DB: ajo_db
      POSTGRES_PASSWORD: password
    volumes:
      - postgres_data:/var/lib/postgresql/data
  
  redis:
    image: redis:7-alpine
    command: redis-server --requirepass password
  
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
    depends_on:
      - app

volumes:
  postgres_data:
```

### CI/CD Pipeline

**Deployment Workflow:**

1. **Code Push:** Developer pushes to repository
2. **CI Triggers:** GitHub Actions/GitLab CI runs
3. **Tests:** Run all test suites
4. **Build:** Create Docker image
5. **Push:** Push image to registry
6. **Deploy:** Deploy to target environment
7. **Verify:** Run smoke tests
8. **Monitor:** Check application health

**Deployment Script:**

```bash
#!/bin/bash

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
php artisan queue:restart
sudo systemctl restart php8.2-fpm
sudo systemctl reload nginx

# Run health check
curl -f http://localhost/api/health || exit 1

echo "Deployment completed successfully"
```

### Database Migration Strategy

**Zero-Downtime Migrations:**

1. **Backward Compatible Changes:**
   - Add new columns as nullable
   - Create new tables without foreign keys initially
   - Deploy application code that works with both old and new schema

2. **Data Migration:**
   - Run data migration in background
   - Verify data integrity
   - Switch application to use new schema

3. **Cleanup:**
   - Remove old columns/tables
   - Add constraints and indexes
   - Optimize database

**Migration Example:**

```php
// Step 1: Add new column (nullable)
Schema::table('users', function (Blueprint $table) {
    $table->string('new_field')->nullable();
});

// Step 2: Backfill data (background job)
User::chunk(1000, function ($users) {
    foreach ($users as $user) {
        $user->new_field = calculateNewValue($user);
        $user->save();
    }
});

// Step 3: Make column required (after backfill complete)
Schema::table('users', function (Blueprint $table) {
    $table->string('new_field')->nullable(false)->change();
});
```

### Monitoring and Observability

**Application Monitoring:**
- **APM:** New Relic or Datadog for application performance
- **Logging:** Centralized logging with ELK stack or CloudWatch
- **Metrics:** Prometheus + Grafana for custom metrics
- **Uptime:** Pingdom or UptimeRobot for availability monitoring

**Key Metrics to Track:**

```php
// Custom metrics
Metrics::increment('contribution.created');
Metrics::timing('payout.processing_time', $duration);
Metrics::gauge('wallet.total_balance', $totalBalance);

// Business metrics
- Daily active users
- Contribution success rate
- Payout processing time
- Payment gateway success rate
- API response time (p50, p95, p99)
- Error rate by endpoint
- Queue depth and processing time
```

**Alerting Rules:**
- API error rate > 5% for 5 minutes
- Payment gateway failure rate > 10%
- Database connection pool exhausted
- Queue depth > 10,000 jobs
- Disk space < 20%
- Memory usage > 90%

### Backup and Disaster Recovery

**Backup Strategy:**

1. **Database Backups:**
   - Full backup: Daily at 2 AM
   - Incremental backup: Every 6 hours
   - Transaction log backup: Every 15 minutes
   - Retention: 30 days
   - Off-site storage: AWS S3 or similar

2. **Application Backups:**
   - Code: Git repository (GitHub/GitLab)
   - Configuration: Encrypted in version control
   - User uploads: S3 with versioning enabled

**Disaster Recovery Plan:**

1. **RTO (Recovery Time Objective):** 4 hours
2. **RPO (Recovery Point Objective):** 15 minutes

**Recovery Procedures:**

```bash
# Database restore
pg_restore -h localhost -U postgres -d ajo_db backup_file.dump

# Application restore
git clone repository
composer install
php artisan migrate
php artisan config:cache

# Verify integrity
php artisan app:verify-data-integrity
```

### Scaling Considerations

**Vertical Scaling (Short-term):**
- Increase server resources (CPU, RAM)
- Optimize database queries
- Implement caching

**Horizontal Scaling (Long-term):**
- Add more application servers
- Implement database read replicas
- Use CDN for static assets
- Microservices architecture for specific components

**Database Sharding Strategy:**
- Shard by user_id for user-specific data
- Shard by group_id for group-specific data
- Keep reference data (banks, etc.) in shared database

## Future Enhancements

### Phase 2 Features

1. **Multi-Currency Support:**
   - Support for USD, GBP, EUR
   - Real-time exchange rates
   - Currency conversion for international groups

2. **Advanced Group Types:**
   - Flexible contribution schedules (weekly, bi-weekly)
   - Variable contribution amounts
   - Multiple payout recipients per cycle
   - Group savings goals

3. **Social Features:**
   - In-app group chat
   - Activity feed
   - Member ratings and reviews
   - Referral program

4. **Financial Services:**
   - Loans against contribution history
   - Investment pools
   - Savings interest
   - Credit scoring

5. **Analytics Dashboard:**
   - Contribution trends
   - Group performance metrics
   - User engagement analytics
   - Financial forecasting

### Technical Debt and Improvements

1. **Code Quality:**
   - Increase test coverage to 90%+
   - Implement static analysis (Psalm level 1)
   - Refactor legacy code
   - Document all APIs with OpenAPI/Swagger

2. **Performance:**
   - Implement GraphQL for flexible queries
   - Add full-text search (Elasticsearch)
   - Optimize database indexes
   - Implement database query caching

3. **Security:**
   - Implement 2FA for all users
   - Add biometric authentication
   - Regular security audits
   - Bug bounty program

4. **Infrastructure:**
   - Migrate to Kubernetes for orchestration
   - Implement blue-green deployments
   - Add chaos engineering tests
   - Multi-region deployment

---

**Document Version:** 1.0  
**Last Updated:** 2024-01-15  
**Status:** Ready for Review
