# KYC System - Complete Sync Documentation

## Overview
The KYC (Know Your Customer) system is fully implemented and synced across:
1. Mobile App (Flutter)
2. Backend API (Laravel)
3. Admin Dashboard (Web)

## System Architecture

### 1. Mobile App (Flutter)

#### Screens
- **KYC Submission Screen** (`mobile/lib/features/kyc/screens/kyc_submission_screen.dart`)
  - Document type selection (National ID, Driver's License, Passport, Voter's Card)
  - Image upload (Camera or Gallery)
  - Submit for verification

- **KYC Status Screen** (`mobile/lib/features/kyc/screens/kyc_status_screen.dart`)
  - View current KYC status (Pending, Verified, Rejected)
  - Timeline visualization
  - Rejection reason display
  - Resubmit option for rejected documents

#### State Management
- **Provider** (`mobile/lib/providers/kyc_provider.dart`)
  - `KycNotifier` manages KYC state
  - States: Initial, Loading, StatusLoaded, Submitted, Error

#### Data Layer
- **Repository** (`mobile/lib/repositories/kyc_repository.dart`)
  - `submitKyc()` - Upload document with multipart form data
  - `getKycStatus()` - Fetch current KYC status

- **Models** (`mobile/lib/models/kyc.dart`)
  - `KycDocument` - Document submission data
  - `KycStatus` - Current verification status

### 2. Backend API (Laravel)

#### User Endpoints
**Base URL**: `/api/v1/user`

1. **Submit KYC Document**
   - **POST** `/kyc/submit`
   - **Auth**: Required (Sanctum)
   - **Body**: Multipart form data
     - `document`: File (jpg, jpeg, png, pdf, max 5MB)
     - `document_type`: String (optional)
   - **Response**: KYC status and document URL

2. **Get KYC Status**
   - **GET** `/kyc/status`
   - **Auth**: Required (Sanctum)
   - **Response**: Current KYC status, document URL, rejection reason

#### Admin Endpoints
**Base URL**: `/api/v1/admin`

1. **List Pending KYC**
   - **GET** `/kyc/pending`
   - **Auth**: Required (Admin)
   - **Query Params**: `per_page`, `page`
   - **Response**: Paginated list of users with pending KYC

2. **Approve KYC**
   - **POST** `/kyc/{id}/approve`
   - **Auth**: Required (Admin)
   - **Response**: Updated user with verified status

3. **Reject KYC**
   - **POST** `/kyc/{id}/reject`
   - **Auth**: Required (Admin)
   - **Body**: `reason` (string, required)
   - **Response**: Updated user with rejected status


### 3. Admin Dashboard (Web)

#### KYC Management Section
**File**: `admin-dashboard/app.js`

**Features**:
- View all pending KYC submissions
- Display user information and document
- Approve KYC with one click
- Reject KYC with reason input
- Real-time badge updates for pending count

**Functions**:
- `loadKYC()` - Fetch and display pending submissions
- `approveKYC(userId)` - Approve user's KYC
- `rejectKYC(userId)` - Reject with reason
- Auto-refresh dashboard stats after approval/rejection

## Database Schema

### Users Table Fields
```sql
kyc_status VARCHAR(20) DEFAULT 'pending'
  -- Values: 'pending', 'verified', 'rejected'
  
kyc_document_url VARCHAR(255) NULL
  -- Storage path to uploaded document
  
kyc_rejection_reason TEXT NULL
  -- Admin's reason for rejection
  
updated_at TIMESTAMP
  -- Used as submission timestamp
```

## File Storage

### Backend Storage
- **Path**: `storage/app/kyc_documents/`
- **Naming**: `user_{user_id}_{timestamp}.{extension}`
- **Allowed**: jpg, jpeg, png, pdf
- **Max Size**: 5MB

### Access
- Documents are stored privately
- Admin can view via document URL
- Users cannot directly access other users' documents

## Workflow

### User Submission Flow
1. User opens KYC Submission Screen
2. Selects document type
3. Takes photo or selects from gallery
4. Submits document
5. Backend stores file and updates user status to 'pending'
6. User can check status on KYC Status Screen

### Admin Review Flow
1. Admin logs into dashboard
2. Navigates to KYC section
3. Views list of pending submissions
4. Clicks "View" to see document
5. Either:
   - Approves → User status becomes 'verified'
   - Rejects → User status becomes 'rejected' with reason
6. User receives updated status in mobile app

### User Resubmission Flow (if rejected)
1. User checks KYC Status Screen
2. Sees rejection reason
3. Clicks "Resubmit Document"
4. Uploads new document
5. Status changes back to 'pending'
6. Previous rejection reason is cleared

## API Response Formats

### Submit KYC Response
```json
{
  "success": true,
  "message": "KYC document submitted successfully",
  "data": {
    "kyc_status": "pending",
    "kyc_document_url": "kyc_documents/user_1_1234567890.jpg",
    "submitted_at": "2024-01-15T10:30:00Z"
  }
}
```

### Get KYC Status Response
```json
{
  "success": true,
  "message": "KYC status retrieved successfully",
  "data": {
    "status": "pending",
    "rejection_reason": null,
    "submitted_at": "2024-01-15T10:30:00Z",
    "verified_at": null,
    "document_url": "kyc_documents/user_1_1234567890.jpg",
    "document_type": "national_id"
  }
}
```

### Pending KYC List Response
```json
{
  "success": true,
  "message": "Pending KYC submissions retrieved",
  "data": {
    "data": [
      {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "kyc_document_url": "kyc_documents/user_1_1234567890.jpg",
        "created_at": "2024-01-15T10:30:00Z"
      }
    ],
    "current_page": 1,
    "per_page": 50,
    "total": 10
  }
}
```

## Security Features

1. **Authentication Required**
   - All KYC endpoints require authentication
   - Admin endpoints require admin role

2. **File Validation**
   - Type checking (only images and PDFs)
   - Size limit (5MB max)
   - Secure filename generation

3. **Access Control**
   - Users can only submit/view their own KYC
   - Admins can view all KYC submissions
   - Documents stored in private storage

4. **Audit Trail**
   - Submission timestamps recorded
   - Approval/rejection tracked
   - Admin actions logged

## Status Badges

### Mobile App
- **Pending**: Orange badge with hourglass icon
- **Verified**: Green badge with checkmark icon
- **Rejected**: Red badge with cancel icon

### Admin Dashboard
- **Pending**: Yellow/warning badge
- **Verified**: Green/success badge
- **Rejected**: Red/danger badge

## Integration Points

### Mobile → Backend
- Multipart form upload for documents
- Bearer token authentication
- Error handling with user-friendly messages

### Backend → Admin Dashboard
- RESTful API endpoints
- JSON responses
- Real-time badge updates

### Admin Dashboard → Backend
- Approval/rejection actions
- Reason input for rejections
- Auto-refresh after actions

## Testing Checklist

### Mobile App
- [ ] Document type selection works
- [ ] Camera capture works
- [ ] Gallery selection works
- [ ] Upload progress shown
- [ ] Success message displayed
- [ ] Status screen shows correct status
- [ ] Timeline displays correctly
- [ ] Rejection reason shown when rejected
- [ ] Resubmit button works for rejected KYC

### Backend API
- [ ] Submit endpoint accepts valid documents
- [ ] Submit endpoint rejects invalid files
- [ ] Status endpoint returns correct data
- [ ] Pending list shows only pending KYC
- [ ] Approve endpoint updates status
- [ ] Reject endpoint saves reason
- [ ] File storage works correctly
- [ ] Authentication required for all endpoints

### Admin Dashboard
- [ ] KYC section loads pending submissions
- [ ] Document view link works
- [ ] Approve button works
- [ ] Reject button prompts for reason
- [ ] Badge count updates after actions
- [ ] Dashboard stats refresh after actions
- [ ] Error messages display correctly

## Common Issues & Solutions

### Issue: Document upload fails
**Solution**: Check file size (<5MB) and format (jpg, jpeg, png, pdf)

### Issue: KYC status not updating in mobile
**Solution**: Pull to refresh or restart app to fetch latest status

### Issue: Admin can't see document
**Solution**: Ensure document URL is accessible and storage is configured

### Issue: Rejection reason not showing
**Solution**: Verify reason was provided during rejection

## Future Enhancements

1. **Document Type Validation**
   - OCR to verify document authenticity
   - Face matching with selfie

2. **Notifications**
   - Push notification when KYC approved/rejected
   - Email notification to user

3. **Bulk Actions**
   - Approve/reject multiple KYC at once
   - Export pending KYC list

4. **Analytics**
   - KYC approval rate
   - Average processing time
   - Rejection reasons analysis

## Maintenance

### Regular Tasks
1. Clean up old rejected documents (>90 days)
2. Monitor storage usage
3. Review rejection reasons for patterns
4. Update document type list as needed

### Monitoring
- Track KYC submission rate
- Monitor approval/rejection ratio
- Alert on high rejection rates
- Check storage capacity

## Support

### User Support
- Guide users on document requirements
- Help with resubmission process
- Explain rejection reasons

### Admin Support
- Train admins on verification process
- Provide rejection reason guidelines
- Monitor admin response times

---

## Quick Start Guide

### For Users (Mobile App)
1. Open app and navigate to Profile
2. Tap "Complete KYC Verification"
3. Select document type
4. Take photo or choose from gallery
5. Submit and wait for verification (24-48 hours)
6. Check status in KYC Status screen

### For Admins (Dashboard)
1. Login to admin dashboard
2. Click "KYC Verification" in sidebar
3. Review pending submissions
4. Click "View" to see document
5. Click "Approve" or "Reject" (with reason)
6. Confirmation message will appear

---

**Status**: ✅ Fully Implemented and Synced
**Last Updated**: 2024-03-12
**Version**: 1.0.0
