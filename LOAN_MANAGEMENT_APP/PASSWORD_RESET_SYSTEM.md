# Password Reset System - Development Setup

## Overview
The password reset system has been configured for your development environment. Since SMTP is not available on local XAMPP, reset links are saved to a log file instead of being emailed.

## How It Works

### Step 1: Staff Requests Password Reset
1. Staff member goes to `/staff/forgot_password.php`
2. Enters their registered email address
3. System generates a secure reset token and saves the reset link to `/logs/reset_links.txt`

### Step 2: Admin Retrieves Reset Link
1. Admin goes to `/staff/view_reset_links.php`
2. Finds the most recent entry for the staff member
3. Copies the reset link from the log

### Step 3: Staff Uses Reset Link
1. Admin shares the reset link with the staff member
2. Staff member opens the link in their browser
3. Enters a new password (must be 8+ characters with uppercase, lowercase, number, and special character)
4. Password is updated in the database

## Security Features
- **Reset tokens**: Unique 64-character hex tokens generated using cryptographically secure random bytes
- **Token expiration**: Links expire after 1 hour
- **Password validation**: Enforces strong password requirements:
  - Minimum 8 characters
  - At least 1 uppercase letter
  - At least 1 lowercase letter
  - At least 1 number
  - At least 1 special character (!@#$%^&*)
- **Email requirement**: All staff accounts require a valid email address

## Files Modified
- `/staff/forgot_password.php` - Password reset form and processing
- `/staff/registration.php` - Staff management with required email field
- `/staff/view_reset_links.php` - Admin panel to view generated reset links
- `/setup/migrate_password_reset.php` - Database schema migration
- `/logs/reset_links.txt` - Log file where reset links are stored (auto-created)

## Testing the System

### Register a Staff Member
1. Go to `/staff/registration.php` (as admin)
2. Create a new staff account with a valid email address

### Test Password Reset
1. Go to `/staff/forgot_password.php`
2. Enter the staff member's email
3. System displays: "Password reset link has been generated. ✓ Reset link saved and ready to use."
4. Go to `/staff/view_reset_links.php` to see the reset link
5. Copy the reset link and test it in a browser
6. Enter a strong new password and confirm

## For Production Deployment
When moving to production with real email:
1. Replace the `save_reset_link_to_file()` function with actual email sending
2. Configure Gmail SMTP with proper app password
3. Or use a service like SendGrid, Mailgun, or AWS SES
4. Update `send_email_via_gmail()` function to send actual emails instead of saving to file

## Database Schema
The `users` table has two new columns:
- `reset_token` (VARCHAR(255)) - Stores the reset token
- `reset_token_expiry` (DATETIME) - Token expiration timestamp

## Files Structure
```
logs/
  reset_links.txt          # Auto-created, stores reset links
  password_resets.txt      # Log of completed resets

staff/
  forgot_password.php      # Password reset page
  view_reset_links.php     # Admin panel to view reset links
  registration.php         # Staff management

setup/
  migrate_password_reset.php  # Database migration script
```
