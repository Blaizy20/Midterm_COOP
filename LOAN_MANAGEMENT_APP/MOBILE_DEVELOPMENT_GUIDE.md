# Mobile App Development - Quick Start Guide

## Overview

This guide helps Kotlin/Android developers connect to the refactored Loan Management System backend to build the mobile application.

## Backend Services Available

The web backend provides 5 modular services ready for API integration:

### 1. AuthService - User Authentication
```
Purpose: Handle customer login, registration, password reset
Status: Ready for mobile API
Database: users table (role = 'CUSTOMER')
```

### 2. CustomerService - Customer Data
```
Purpose: Manage customer profiles, personal information
Status: Ready for mobile API
Database: customers table
```

### 3. LoanService - Loan Management
```
Purpose: Create loan applications, track status
Status: Ready for mobile API
Database: loans table
```

### 4. PaymentService - Payment Tracking
```
Purpose: View payment history, track balances
Status: Ready for mobile API
Database: payments table
```

### 5. RequirementService - Document Management
```
Purpose: Upload and manage loan documents
Status: Ready for mobile API
Database: requirements table
```

## API Gateway Status

### Currently Provided
- ✅ `/api/v1/config.php` - API utilities and helpers
- ✅ `/api/v1/auth.php` - Login/register/password reset endpoints
- ✅ `/api/v1/loans.php` - Loan application and tracking endpoints

### Still Need to Implement
- [ ] `/api/v1/customers.php` - Customer profile endpoints
- [ ] `/api/v1/payments.php` - Payment tracking endpoints
- [ ] `/api/v1/requirements.php` - Document upload endpoints
- [ ] Authentication middleware (JWT implementation)
- [ ] Rate limiting (Redis recommended)
- [ ] Response caching

## Database Overview

### Core Tables

**users** (Authentication)
```
user_id (PK) | username | password_hash | full_name | role | email | contact_no
- role options: ADMIN, MANAGER, CREDIT_INVESTIGATOR, LOAN_OFFICER, CASHIER, CUSTOMER
- CUSTOMER role = Mobile app users
```

**customers** (Profiles)
```
customer_id (PK) | user_id | customer_no | first_name | last_name | contact_no 
| email | province | city | barangay | street | created_at | is_active
```

**loans** (Applications)
```
loan_id (PK) | reference_no | customer_id | principal_amount | interest_rate 
| payment_term | term_months | total_payable | remaining_balance | status 
| submitted_at | ci_by | manager_by | loan_officer_id | activated_at | due_date
- status: PENDING, CI_REVIEWED, APPROVED, DENIED, ACTIVE, OVERDUE, CLOSED
```

**payments** (Payment History)
```
payment_id (PK) | loan_id | amount | payment_date | method | cheque_number 
| gcash_reference_no | or_no | loan_officer_id | received_by | created_at
- method: CASH, CHEQUE, DIGITAL, GCASH
```

**requirements** (Documents)
```
requirement_id (PK) | loan_id | requirement_code | requirement_name | file_path 
| uploaded_by_role | uploaded_by_user | uploaded_at | notes
- uploaded_by_role: CUSTOMER (from mobile) or STAFF (from web)
```

## Authentication Flow

### Step 1: Customer Registration (Mobile)
```
Mobile App → POST /api/v1/auth.php?action=register
  ├─ username: "john_doe"
  ├─ password: "SecurePass123!"
  ├─ email: "john@example.com"
  └─ contact_no: "09XXXXXXXXX"

Backend → Creates users table entry (role='CUSTOMER')
        → Creates customers table entry
        → Returns user_id, customer_id, customer_no

Database:
  users: [user_id=1, username='john_doe', role='CUSTOMER']
  customers: [customer_id=1, user_id=1, customer_no='CUST-000001']
```

### Step 2: Customer Login (Mobile)
```
Mobile App → POST /api/v1/auth.php?action=login
  ├─ username: "john_doe"
  └─ password: "SecurePass123!"

Backend → AuthService.authenticateCustomer()
       → Verify password
       → Generate JWT token (valid 24 hours)
       → Return token + user data

Mobile App → Stores token in Keystore/Secure Enclave
          → Includes token in Authorization header for all requests
```

### Step 3: API Requests with Authentication
```
Mobile App → GET /api/v1/loans.php
  ├─ Headers: Authorization: Bearer {token}
  └─ Returns: Customer's loans list

Mobile App → POST /api/v1/loans.php?action=apply
  ├─ Headers: Authorization: Bearer {token}
  ├─ Body: {principal_amount, interest_rate, payment_term, ...}
  └─ Returns: New loan reference number
```

### Step 4: Token Refresh (Optional)
```
Mobile App → POST /api/v1/auth.php?action=refresh-token
  ├─ old_token: {old_jwt}
  └─ Returns: New token (valid another 24 hours)
```

## Implementing Customer Endpoints

### Example: Create `/api/v1/customers.php`

```php
<?php
// /api/v1/customers.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../includes/CustomerService.php';

// Require authentication
$user = require_auth();

$method = $_SERVER['REQUEST_METHOD'];

// GET /api/v1/customers.php
if ($method === 'GET') {
    try {
        $customerService = new CustomerService();
        $customer = $customerService->getCustomerByUserId($user['user_id']);
        
        if (!$customer) {
            api_error('Customer not found', 'CUSTOMER_NOT_FOUND', 404);
        }
        
        api_response(true, $customer, 'Customer profile retrieved');
        
    } catch (Exception $e) {
        log_error('Get customer error', ['error' => $e->getMessage()]);
        api_error('Failed to retrieve profile', 'CUSTOMER_ERROR', 500);
    }
}

// PUT /api/v1/customers.php
else if ($method === 'PUT') {
    try {
        $data = get_json_input();
        $customerService = new CustomerService();
        
        $customer = $customerService->getCustomerByUserId($user['user_id']);
        if (!$customer) {
            api_error('Customer not found', 'CUSTOMER_NOT_FOUND', 404);
        }
        
        // Update profile
        $success = $customerService->updateCustomer($customer['customer_id'], $data);
        
        if ($success) {
            $updated = $customerService->getCustomerById($customer['customer_id']);
            api_response(true, $updated, 'Profile updated successfully');
        }
        
    } catch (Exception $e) {
        log_error('Update customer error', ['error' => $e->getMessage()]);
        api_error('Failed to update profile', 'CUSTOMER_ERROR', 500);
    }
}

else {
    api_error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

?>
```

## Implementing Payment Endpoints

### Example: Create `/api/v1/payments.php`

```php
<?php
// /api/v1/payments.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../includes/PaymentService.php';
require_once __DIR__ . '/../../includes/LoanService.php';
require_once __DIR__ . '/../../includes/CustomerService.php';

// Require authentication
$user = require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$loan_id = $_GET['loan_id'] ?? null;

// GET /api/v1/payments.php?loan_id=1
if ($method === 'GET' && $loan_id) {
    try {
        $loanService = new LoanService();
        $customerService = new CustomerService();
        $paymentService = new PaymentService();
        
        // Verify customer owns this loan
        $customer = $customerService->getCustomerByUserId($user['user_id']);
        $loan = $loanService->getLoanById($loan_id);
        
        if (!$loan || $loan['customer_id'] != $customer['customer_id']) {
            api_error('Loan not found or access denied', 'LOAN_NOT_FOUND', 404);
        }
        
        $payments = $paymentService->getLoanPayments($loan_id);
        
        api_response(true, [
            'loan_details' => [
                'reference_no' => $loan['reference_no'],
                'principal_amount' => $loan['principal_amount'],
                'total_payable' => $loan['total_payable'],
                'remaining_balance' => $loan['remaining_balance'],
                'status' => $loan['status']
            ],
            'payments' => $payments
        ], 'Payment history retrieved');
        
    } catch (Exception $e) {
        log_error('Get payments error', ['error' => $e->getMessage()]);
        api_error('Failed to retrieve payments', 'PAYMENT_ERROR', 500);
    }
}

else {
    api_error('Invalid parameters', 'INVALID_PARAMETERS', 400);
}

?>
```

## Document Upload Implementation

### Example: Create `/api/v1/requirements.php`

```php
<?php
// /api/v1/requirements.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../includes/RequirementService.php';
require_once __DIR__ . '/../../includes/LoanService.php';
require_once __DIR__ . '/../../includes/CustomerService.php';

$user = require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$loan_id = $_GET['loan_id'] ?? null;

// GET /api/v1/requirements.php?loan_id=1
if ($method === 'GET' && $loan_id) {
    try {
        $requirementService = new RequirementService();
        $loanService = new LoanService();
        $customerService = new CustomerService();
        
        // Verify access
        $customer = $customerService->getCustomerByUserId($user['user_id']);
        $loan = $loanService->getLoanById($loan_id);
        
        if (!$loan || $loan['customer_id'] != $customer['customer_id']) {
            api_error('Loan not found', 'LOAN_NOT_FOUND', 404);
        }
        
        $requirements = $requirementService->getLoanRequirements($loan_id);
        api_response(true, $requirements, 'Documents retrieved');
        
    } catch (Exception $e) {
        log_error('Get requirements error', ['error' => $e->getMessage()]);
        api_error('Failed to retrieve documents', 'REQUIREMENT_ERROR', 500);
    }
}

// POST /api/v1/requirements.php?loan_id=1
// Upload document (multipart/form-data)
else if ($method === 'POST' && $loan_id) {
    try {
        $requirementService = new RequirementService();
        $loanService = new LoanService();
        $customerService = new CustomerService();
        
        // Verify loan access
        $customer = $customerService->getCustomerByUserId($user['user_id']);
        $loan = $loanService->getLoanById($loan_id);
        
        if (!$loan || $loan['customer_id'] != $customer['customer_id']) {
            api_error('Loan not found', 'LOAN_NOT_FOUND', 404);
        }
        
        // Get form data
        $code = $_POST['requirement_code'] ?? null;
        $name = $_POST['requirement_name'] ?? null;
        
        if (!$code || !$name || !isset($_FILES['file'])) {
            api_error('Missing required fields', 'VALIDATION_ERROR', 422);
        }
        
        // Validate file
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            api_error('File upload failed', 'UPLOAD_ERROR', 400);
        }
        
        if ($file['size'] > 10 * 1024 * 1024) { // 10 MB
            api_error('File too large', 'FILE_TOO_LARGE', 413);
        }
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file['type'], $allowed_types)) {
            api_error('File type not allowed', 'INVALID_FILE_TYPE', 415);
        }
        
        // Save file
        $filename = 'LOAN_' . $loan_id . '_' . date('YmdHis') . '_' . basename($file['name']);
        $filepath = $requirementService->getUploadPath($filename);
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            api_error('Failed to save file', 'FILE_ERROR', 500);
        }
        
        // Record in database
        $requirementId = $requirementService->addRequirement(
            $loan_id,
            $code,
            $name,
            $filename,
            'CUSTOMER', // Mark as mobile upload
            $user['user_id']
        );
        
        api_response(true, [
            'requirement_id' => $requirementId,
            'file_path' => $filename,
            'uploaded_at' => date('Y-m-d H:i:s')
        ], 'Document uploaded successfully', 201);
        
    } catch (Exception $e) {
        log_error('Upload document error', ['error' => $e->getMessage()]);
        api_error('Failed to upload document', 'REQUIREMENT_ERROR', 500);
    }
}

else {
    api_error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

?>
```

## Kotlin/Android Integration Example

### Retrofit Service Interface

```kotlin
interface LoanAPIService {
    
    @POST("auth.php?action=register")
    suspend fun register(@Body data: RegisterRequest): ApiResponse<AuthResponse>
    
    @POST("auth.php?action=login")
    suspend fun login(@Body data: LoginRequest): ApiResponse<AuthResponse>
    
    @GET("loans.php")
    suspend fun getLoans(
        @Header("Authorization") token: String
    ): ApiResponse<LoansResponse>
    
    @POST("loans.php?action=apply")
    suspend fun applyLoan(
        @Header("Authorization") token: String,
        @Body data: LoanApplicationRequest
    ): ApiResponse<LoanResponse>
    
    @GET("loans.php")
    suspend fun getLoanPayments(
        @Header("Authorization") token: String,
        @Query("loan_id") loanId: Int,
        @Query("action") action: String = "payments"
    ): ApiResponse<PaymentsResponse>
    
    @Multipart
    @POST("requirements.php")
    suspend fun uploadDocument(
        @Header("Authorization") token: String,
        @Query("loan_id") loanId: Int,
        @Part("requirement_code") code: RequestBody,
        @Part("requirement_name") name: RequestBody,
        @Part file: MultipartBody.Part
    ): ApiResponse<RequirementResponse>
}
```

### ViewModel Usage

```kotlin
class LoanViewModel(private val apiService: LoanAPIService) : ViewModel() {
    
    fun login(username: String, password: String) = viewModelScope.launch {
        try {
            val response = apiService.login(LoginRequest(username, password))
            if (response.success) {
                // Save token securely
                securePreferences.saveToken(response.data?.token)
                
                // Load customer data
                loadCustomerLoans(response.data?.user?.customer_id)
            }
        } catch (e: Exception) {
            // Handle error
        }
    }
    
    fun applyForLoan(amount: Double, term: Int) = viewModelScope.launch {
        try {
            val token = securePreferences.getToken()
            val request = LoanApplicationRequest(
                principal_amount = amount,
                interest_rate = 12.5,
                payment_term = "monthly",
                term_months = term
            )
            
            val response = apiService.applyLoan("Bearer $token", request)
            if (response.success) {
                // Navigate to document upload
                navigateToDocumentUpload(response.data?.loan_id)
            }
        } catch (e: Exception) {
            // Handle error
        }
    }
    
    fun uploadDocument(loanId: Int, file: File) = viewModelScope.launch {
        try {
            val token = securePreferences.getToken()
            val filePart = MultipartBody.Part.createFormData(
                "file",
                file.name,
                file.asRequestBody("image/jpeg".toMediaType())
            )
            
            val response = apiService.uploadDocument(
                "Bearer $token",
                loanId,
                "ID_FRONT".toRequestBody(),
                "Valid ID - Front".toRequestBody(),
                filePart
            )
            
            if (response.success) {
                // Document uploaded successfully
            }
        } catch (e: Exception) {
            // Handle error
        }
    }
}
```

## Testing the API

### Using curl

```bash
# Register
curl -X POST http://localhost/api/v1/auth.php?action=register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser",
    "password": "TestPass123!",
    "email": "test@example.com",
    "contact_no": "09123456789",
    "first_name": "John",
    "last_name": "Doe"
  }'

# Login
curl -X POST http://localhost/api/v1/auth.php?action=login \
  -H "Content-Type: application/json" \
  -d '{"username": "testuser", "password": "TestPass123!"}'

# Get loans
TOKEN="your_token_here"
curl -X GET http://localhost/api/v1/loans.php \
  -H "Authorization: Bearer $TOKEN"

# Apply for loan
curl -X POST http://localhost/api/v1/loans.php?action=apply \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "principal_amount": 50000,
    "interest_rate": 12.5,
    "payment_term": "monthly",
    "term_months": 12
  }'
```

### Using Postman

1. Create new Collection: "Loan API"
2. Create requests:
   - POST /auth.php?action=register
   - POST /auth.php?action=login
   - GET /loans.php
   - POST /loans.php?action=apply
   - GET /requirements.php?loan_id=1

3. Set up environment variables:
   - base_url: http://localhost
   - token: [from login response]

## Common Errors & Solutions

**Error: 401 Unauthorized**
- Cause: Missing or invalid token
- Solution: Ensure token is included in Authorization header

**Error: 404 Not Found**
- Cause: Endpoint doesn't exist or wrong method
- Solution: Check endpoint URL and HTTP method

**Error: 422 Validation Error**
- Cause: Invalid or missing required fields
- Solution: Check request body matches API spec

**Error: 500 Server Error**
- Cause: Backend error
- Solution: Check server logs in `/logs/api_errors.log`

## Next Steps

1. ✅ Backend services ready
2. ✅ Sample API endpoints provided
3. ⏭️ Complete remaining API endpoints (customers, payments, requirements)
4. ⏭️ Implement JWT token authentication properly
5. ⏭️ Set up Redis for rate limiting
6. ⏭️ Configure HTTPS/SSL
7. ⏭️ Deploy to production
8. ⏭️ Begin mobile app development

## Resources

- **API_INTEGRATION_GUIDE.md** - Detailed API documentation
- **MOBILE_API_SPECS.md** - Complete endpoint specifications
- **Code Comments** - Every service class documented

---

**Status**: Backend Ready for Mobile Development ✅  
**Last Updated**: March 14, 2026  
**Next Phase**: Mobile App Development (Kotlin/Android)
