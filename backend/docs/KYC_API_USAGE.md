# KYC API Usage Guide

This document provides examples of how to use the KYC submission and verification endpoints.

## Endpoints

### 1. Submit KYC Document

**Endpoint:** `POST /api/v1/user/kyc/submit`

**Authentication:** Required (Bearer token)

**Request:**
- Content-Type: `multipart/form-data`
- Body:
  - `document`: File (jpg, jpeg, png, or pdf, max 5MB)

**Example using cURL:**

```bash
curl -X POST http://localhost:8000/api/v1/user/kyc/submit \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -F "document=@/path/to/kyc_document.jpg"
```

**Success Response (200):**

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

**Error Response - Already Verified (422):**

```json
{
  "success": false,
  "message": "Your KYC is already verified"
}
```

**Error Response - Validation Error (422):**

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "document": [
      "The document must be a file of type: jpg, jpeg, png, pdf."
    ]
  }
}
```

### 2. Get KYC Status

**Endpoint:** `GET /api/v1/user/kyc/status`

**Authentication:** Required (Bearer token)

**Example using cURL:**

```bash
curl -X GET http://localhost:8000/api/v1/user/kyc/status \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "KYC status retrieved successfully",
  "data": {
    "kyc_status": "pending",
    "kyc_document_url": "kyc_documents/user_1_1234567890.jpg",
    "kyc_rejection_reason": null,
    "submitted_at": "2024-01-15T10:30:00Z"
  }
}
```

**Response with Rejection:**

```json
{
  "success": true,
  "message": "KYC status retrieved successfully",
  "data": {
    "kyc_status": "rejected",
    "kyc_document_url": "kyc_documents/user_1_1234567890.jpg",
    "kyc_rejection_reason": "Document is not clear enough",
    "submitted_at": "2024-01-15T10:30:00Z"
  }
}
```

## KYC Status Values

- `pending`: KYC document has been submitted and is awaiting admin review
- `verified`: KYC has been approved by admin
- `rejected`: KYC has been rejected by admin (user can resubmit)

## File Requirements

- **Accepted formats:** JPG, JPEG, PNG, PDF
- **Maximum size:** 5MB
- **Storage location:** Files are stored securely in `storage/app/kyc_documents/`
- **Filename format:** `user_{user_id}_{timestamp}.{extension}`

## Security Notes

1. All endpoints require authentication via Sanctum token
2. KYC documents are stored in private storage (not publicly accessible)
3. Users with verified KYC cannot resubmit documents
4. Users with rejected KYC can resubmit new documents
5. File uploads are validated for type and size

## Testing in Docker Environment

To test these endpoints in the Docker environment:

1. First, register a user and get a token:

```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "phone": "+2348012345678",
    "password": "SecurePass123"
  }'
```

2. Use the returned token to submit KYC:

```bash
curl -X POST http://localhost:8000/api/v1/user/kyc/submit \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -F "document=@/path/to/document.jpg"
```

3. Check KYC status:

```bash
curl -X GET http://localhost:8000/api/v1/user/kyc/status \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## Admin Actions (Future Implementation)

Admin endpoints for KYC approval/rejection will be implemented in Task 13.3:
- `POST /api/v1/admin/kyc/{id}/approve`
- `POST /api/v1/admin/kyc/{id}/reject`

These endpoints will allow admins to:
- Approve KYC documents (transition status to 'verified')
- Reject KYC documents with a reason (transition status to 'rejected')
