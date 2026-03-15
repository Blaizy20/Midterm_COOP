# Mobile API Endpoint Specifications

## Base Configuration

```
Base URL: https://your-domain.com/api/v1
Content-Type: application/json
Authentication: Bearer Token (see Auth Endpoints)
API Version: 1.0
```

## Response Format

### Success Response

```json
{
    "success": true,
    "data": {
        // Response data
    },
    "message": "Operation successful",
    "timestamp": "2026-03-14T10:30:00+00:00",
    "version": "v1"
}
```

### Error Response

```json
{
    "success": false,
    "message": "Error description",
    "error_code": "ERROR_CODE",
    "timestamp": "2026-03-14T10:30:00+00:00",
    "version": "v1"
}
```

## Authentication Endpoints

### POST /auth/register
Customer registration for mobile app

**Request:**
```json
{
    "username": "john_doe",
    "password": "SecurePass123!",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "contact_no": "09XXXXXXXXX"
}
```

**Response (201 Created):**
```json
{
    "success": true,
    "data": {
        "user_id": 1,
        "customer_id": 1,
        "username": "john_doe",
        "full_name": "John Doe",
        "email": "john@example.com",
        "contact_no": "09XXXXXXXXX"
    },
    "message": "Registration successful"
}
```

**Error Cases:**
- `400` - Missing required fields
- `409` - Username already exists
- `422` - Password doesn't meet requirements

---

### POST /auth/login
Customer login

**Request:**
```json
{
    "username": "john_doe",
    "password": "SecurePass123!"
}
```

**Response (200 OK):**
```json
{
    "success": true,
    "data": {
        "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "token_type": "Bearer",
        "expires_in": 86400,
        "user": {
            "user_id": 1,
            "customer_id": 1,
            "full_name": "John Doe",
            "email": "john@example.com",
            "username": "john_doe"
        }
    },
    "message": "Login successful"
}
```

**Error Cases:**
- `400` - Missing username or password
- `401` - Invalid credentials
- `403` - Account disabled

---

### POST /auth/refresh-token
Refresh expired authentication token

**Request:**
```json
{
    "refresh_token": "previous_refresh_token"
}
```

**Response (200 OK):**
```json
{
    "success": true,
    "data": {
        "token": "new_jwt_token",
        "token_type": "Bearer",
        "expires_in": 86400
    }
}
```

---

### POST /auth/forgot-password
Initiate password reset

**Request:**
```json
{
    "email": "john@example.com"
}
```

**Response (200 OK):**
```json
{
    "success": true,
    "message": "Password reset link sent to email"
}
```

---

### POST /auth/reset-password
Reset password with token

**Request:**
```json
{
    "reset_token": "token_from_email",
    "new_password": "NewSecurePass456!"
}
```

**Response (200 OK):**
```json
{
    "success": true,
    "message": "Password reset successfully"
}
```

**Error Cases:**
- `400` - Invalid or expired token
- `422` - Password doesn't meet requirements

---

### POST /auth/logout
Logout current session (optional - mainly for cleanup)

**Request:**
```json
{}
```

**Response (200 OK):**
```json
{
    "success": true,
    "message": "Logged out successfully"
}
```

---

## Customer Profile Endpoints

### GET /customers/profile
Get customer's own profile

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
    "success": true,
    "data": {
        "customer_id": 1,
        "customer_no": "CUST-001",
        "user_id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "contact_no": "09XXXXXXXXX",
        "email": "john@example.com",
        "province": "Metro Manila",
        "city": "Quezon City",
        "barangay": "Quezon City",
        "street": "123 Main St",
        "is_active": 1,
        "created_at": "2026-01-15T08:00:00Z"
    }
}
```

---

### PUT /customers/profile
Update customer's own profile

**Request:**
```json
{
    "first_name": "John",
    "last_name": "Doe",
    "contact_no": "09XXXXXXXXX",
    "email": "john@example.com",
    "province": "Metro Manila",
    "city": "Quezon City",
    "barangay": "Quezon City",
    "street": "123 Main St"
}
```

**Response (200 OK):**
```json
{
    "success": true,
    "data": {
        "customer_id": 1,
        "first_name": "John",
        // ... updated profile
    },
    "message": "Profile updated successfully"
}
```

---

## Loan Endpoints

### GET /loans
Get customer's loans with optional status filter

**Query Parameters:**
- `status` (optional): PENDING, CI_REVIEWED, APPROVED, DENIED, ACTIVE, OVERDUE, CLOSED
- `limit` (optional): Default 20
- `offset` (optional): Default 0

**Request:**
```
GET /loans
GET /loans?status=ACTIVE
GET /loans?status=PENDING&limit=10
```

**Response (200 OK):**
```json
{
    "success": true,
    "data": [
        {
            "loan_id": 1,
            "reference_no": "LN-20260314120000-00001",
            "principal_amount": "50000.00",
            "interest_rate": "12.50",
            "status": "ACTIVE",
            "payment_term": "monthly",
            "term_months": 12,
            "total_payable": "56250.00",
            "remaining_balance": "45000.00",
            "submitted_at": "2026-02-01T10:30:00Z",
            "ci_at": "2026-02-05T14:22:00Z",
            "manager_at": "2026-02-08T09:15:00Z",
            "activated_at": "2026-02-10T11:00:00Z",
            "due_date": "2027-02-10"
        }
    ],
    "pagination": {
        "total": 5,
        "limit": 20,
        "offset": 0
    }
}
```

---

### GET /loans/:loan_id
Get detailed information for a specific loan

**Request:**
```
GET /loans/1
```

**Response (200 OK):**
```json
{
    "success": true,
    "data": {
        "loan_id": 1,
        "reference_no": "LN-20260314120000-00001",
        "principal_amount": "50000.00",
        "interest_rate": "12.50",
        "status": "ACTIVE",
        "payment_term": "monthly",
        "term_months": 12,
        "total_payable": "56250.00",
        "remaining_balance": "45000.00",
        "submitted_at": "2026-02-01T10:30:00Z",
        "ci_name": "Maria Garcia",
        "ci_at": "2026-02-05T14:22:00Z",
        "manager_name": "Juan Santos",
        "manager_at": "2026-02-08T09:15:00Z",
        "loan_officer_name": "Carlos Reyes",
        "activated_at": "2026-02-10T11:00:00Z",
        "due_date": "2027-02-10",
        "notes": "Business expansion loan"
    }
}
```

---

### POST /loans
Submit new loan application

**Request:**
```json
{
    "principal_amount": 50000,
    "interest_rate": 12.5,
    "payment_term": "monthly",
    "term_months": 12,
    "notes": "Business expansion loan"
}
```

**Response (201 Created):**
```json
{
    "success": true,
    "data": {
        "loan_id": 1,
        "reference_no": "LN-20260314120000-00001",
        "status": "PENDING",
        "submitted_at": "2026-03-14T10:30:00Z"
    },
    "message": "Loan application submitted successfully"
}
```

---

### GET /loans/:loan_id/payments
Get payments for a loan

**Request:**
```
GET /loans/1/payments
```

**Response (200 OK):**
```json
{
    "success": true,
    "data": [
        {
            "payment_id": 1,
            "amount": "5000.00",
            "payment_date": "2026-02-20",
            "method": "CASH",
            "or_no": "OR-001",
            "received_by_name": "John Smith",
            "created_at": "2026-02-20T14:30:00Z"
        },
        {
            "payment_id": 2,
            "amount": "5000.00",
            "payment_date": "2026-03-20",
            "method": "GCASH",
            "or_no": "OR-002",
            "gcash_reference_no": "GCASH-ABC123",
            "received_by_name": "Jane Doe",
            "created_at": "2026-03-20T10:15:00Z"
        }
    ]
}
```

---

## Documents/Requirements Endpoints

### GET /loans/:loan_id/requirements
Get all documents for a loan

**Request:**
```
GET /loans/1/requirements
```

**Response (200 OK):**
```json
{
    "success": true,
    "data": [
        {
            "requirement_id": 1,
            "requirement_code": "ID_FRONT",
            "requirement_name": "Valid ID - Front",
            "file_path": "LOAN_001_ID_FRONT.jpg",
            "uploaded_by_role": "CUSTOMER",
            "uploaded_by_name": "John Doe",
            "uploaded_at": "2026-02-01T11:00:00Z",
            "notes": "PhilSys ID"
        },
        {
            "requirement_id": 2,
            "requirement_code": "ID_BACK",
            "requirement_name": "Valid ID - Back",
            "file_path": "LOAN_001_ID_BACK.jpg",
            "uploaded_by_role": "CUSTOMER",
            "uploaded_by_name": "John Doe",
            "uploaded_at": "2026-02-01T11:02:00Z",
            "notes": null
        },
        {
            "requirement_id": 3,
            "requirement_code": "COLLATERAL_PROOF",
            "requirement_name": "Collateral Documentation",
            "file_path": "LOAN_001_COLLATERAL.pdf",
            "uploaded_by_role": "STAFF",
            "uploaded_by_name": "Admin User",
            "uploaded_at": "2026-02-05T15:30:00Z",
            "notes": "Verified and approved"
        }
    ]
}
```

---

### POST /loans/:loan_id/requirements
Upload a document

**Request (multipart/form-data):**
```
POST /loans/1/requirements

Form Data:
- requirement_code: "ID_FRONT"
- requirement_name: "Valid ID - Front"
- file: <image.jpg> (binary)
- notes: "PhilSys ID" (optional)
```

**Response (201 Created):**
```json
{
    "success": true,
    "data": {
        "requirement_id": 1,
        "requirement_code": "ID_FRONT",
        "requirement_name": "Valid ID - Front",
        "file_path": "LOAN_001_ID_FRONT.jpg",
        "uploaded_at": "2026-03-14T10:30:00Z"
    },
    "message": "Document uploaded successfully"
}
```

**Error Cases:**
- `400` - Invalid loan or missing fields
- `413` - File too large
- `415` - Invalid file type

---

### GET /loans/:loan_id/requirements/:requirement_id/download
Download a document

**Request:**
```
GET /loans/1/requirements/1/download
```

**Response (200 OK):**
```
[Binary file content]
Headers:
- Content-Type: image/jpeg
- Content-Disposition: attachment; filename="LOAN_001_ID_FRONT.jpg"
```

---

### DELETE /loans/:loan_id/requirements/:requirement_id
Delete a document (only before loan approval)

**Request:**
```
DELETE /loans/1/requirements/1
```

**Response (200 OK):**
```json
{
    "success": true,
    "message": "Document deleted successfully"
}
```

**Error Cases:**
- `400` - Cannot delete after approval
- `404` - Document not found

---

## Dashboard/Statistics Endpoints

### GET /dashboard
Get customer's dashboard summary

**Request:**
```
GET /dashboard
```

**Response (200 OK):**
```json
{
    "success": true,
    "data": {
        "total_loans": 3,
        "active_loans": 1,
        "pending_loans": 1,
        "closed_loans": 1,
        "total_borrowed": "150000.00",
        "total_paid": "35000.00",
        "total_remaining": "115000.00",
        "recent_loans": [
            {
                "loan_id": 1,
                "reference_no": "LN-20260314120000-00001",
                "status": "ACTIVE",
                "remaining_balance": "45000.00"
            }
        ]
    }
}
```

---

## Error Codes Reference

| Code | HTTP Status | Message |
|------|------------|---------|
| INVALID_CREDENTIALS | 401 | Invalid username or password |
| ACCOUNT_DISABLED | 403 | Your account has been disabled |
| TOKEN_EXPIRED | 401 | Authentication token has expired |
| TOKEN_INVALID | 401 | Invalid authentication token |
| PERMISSION_DENIED | 403 | You don't have permission for this action |
| RESOURCE_NOT_FOUND | 404 | Requested resource not found |
| VALIDATION_ERROR | 422 | Invalid input data |
| DUPLICATE_RESOURCE | 409 | Resource already exists |
| FILE_TOO_LARGE | 413 | File exceeds maximum size |
| INVALID_FILE_TYPE | 415 | File type not allowed |
| SERVER_ERROR | 500 | Internal server error |

---

## Rate Limiting

```
Rate Limit: 100 requests per minute per user
Rate Limit Headers:
- X-RateLimit-Limit: 100
- X-RateLimit-Remaining: 95
- X-RateLimit-Reset: 1615705200
```

---

## Data Types

### Date/Time Format
ISO 8601 format with timezone: `2026-03-14T10:30:00Z`

### Currency
Decimal with 2 places: `50000.00`

### Phone Number
Format: `09XXXXXXXXX` (Philippine format, flexible for international)

### Customer Number
Format: `CUST-001` (Alphanumeric, unique)

### Loan Reference Number
Format: `LN-20260314120000-00001` (Auto-generated, unique)

### OR Number (Official Receipt)
Format: `OR-0001` (Alphanumeric, unique)

---

## Implementation Notes

1. **Token Management**
   - Tokens should expire after 24 hours
   - Implement refresh token mechanism
   - Store tokens securely in mobile (Keystore/Secure Enclave)

2. **File Uploads**
   - Maximum file size: 10 MB
   - Allowed types: JPG, PNG, PDF
   - Filenames sanitized automatically

3. **Pagination**
   - Default limit: 20
   - Maximum limit: 100
   - Use offset-based pagination

4. **Error Handling**
   - All errors return proper HTTP status codes
   - Include error_code for programmatic handling
   - Include message for user display

5. **Caching**
   - Cache GET requests for 5 minutes
   - Invalidate on POST/PUT/DELETE
   - Use ETags for mobile optimization

---

**API Version**: 1.0  
**Last Updated**: March 14, 2026  
**Status**: Ready for Implementation
