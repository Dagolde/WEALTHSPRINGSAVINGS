# Bugfix Requirements Document

## Introduction

This document addresses two critical bugs affecting the mobile app's payment and wallet functionality:

1. **Missing `/payments/banks` endpoint**: The mobile app cannot fetch the list of Nigerian banks for bank account linking because the backend endpoint doesn't exist, resulting in 404 errors.

2. **Wallet balance display issue**: The home screen displays ₦0.0 despite the database containing the correct balance (e.g., ₦1,015,000 for User 18), indicating a problem with fetching or displaying wallet balance data.

These bugs prevent users from linking bank accounts and viewing their correct wallet balance, significantly impacting core financial functionality.

## Bug Analysis

### Current Behavior (Defect)

**Bug 1: Missing Banks Endpoint**

1.1 WHEN the mobile app calls `GET /api/v1/payments/banks` to fetch Nigerian banks list THEN the system returns a 404 error

1.2 WHEN the bank account linking screen attempts to load available banks THEN the system fails to provide any bank options to the user

**Bug 2: Wallet Balance Display**

1.3 WHEN the home screen requests wallet balance via `/api/v1/wallet/balance` after a successful wallet funding THEN the system displays ₦0.0 instead of the actual balance

1.4 WHEN User 18 views their wallet balance on the home screen THEN the system shows ₦0.0 despite the database containing ₦1,015,000

### Expected Behavior (Correct)

**Bug 1: Missing Banks Endpoint**

2.1 WHEN the mobile app calls `GET /api/v1/payments/banks` THEN the system SHALL return a 200 status with a JSON array of Nigerian banks including bank codes and names

2.2 WHEN the bank account linking screen loads THEN the system SHALL provide a complete list of Nigerian banks for user selection

**Bug 2: Wallet Balance Display**

2.3 WHEN the home screen requests wallet balance via `/api/v1/wallet/balance` THEN the system SHALL return the correct balance amount from the database

2.4 WHEN User 18 views their wallet balance on the home screen THEN the system SHALL display ₦1,015,000 (or the current accurate balance from the database)

### Unchanged Behavior (Regression Prevention)

**General API Behavior**

3.1 WHEN the mobile app calls other existing payment endpoints (wallet funding, transactions, etc.) THEN the system SHALL CONTINUE TO function correctly with proper responses

3.2 WHEN the wallet funding operation is performed THEN the system SHALL CONTINUE TO update the database balance correctly

3.3 WHEN other API endpoints are called (KYC, profile picture upload, etc.) THEN the system SHALL CONTINUE TO work as expected

**Wallet Functionality**

3.4 WHEN wallet withdrawal operations are performed THEN the system SHALL CONTINUE TO process them correctly

3.5 WHEN transaction history is requested THEN the system SHALL CONTINUE TO return accurate transaction records

**Bank Account Functionality**

3.6 WHEN existing bank account operations (if any) are performed THEN the system SHALL CONTINUE TO function without disruption
