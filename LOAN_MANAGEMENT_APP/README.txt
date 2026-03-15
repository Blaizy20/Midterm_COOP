# CredenceLend (Final Working Build)

## Requirements
- XAMPP (Apache + MariaDB/MySQL)
- PHP 7.4+ (XAMPP default is OK)
- MariaDB 10.4+ / MySQL 5.7+

## Install (Client / Panel)
1. Copy folder `LOAN_MANAGEMENT` into:
   `C:\xampp\htdocs\LOAN_MANAGEMENT`

2. Start **Apache** and **MySQL** in XAMPP Control Panel.

3. Run database setup (one time, safe to re-run):
   - Open in browser:
     `http://localhost/LOAN_MANAGEMENT/
setup/setup_db.php`

4. Open the system:
   - `http://localhost/LOAN_MANAGEMENT/
`

## Demo Accounts (Seeded)
Staff / Admin (use Staff Login):
- admin / Password123!
- manager / Password123!
- ci / Password123!
- cashier / Password123!
- loanofficer / Password123!

Customer:
- register through Customer Portal.

## Portals
- Customer Portal:
  - Register/Login
  - Apply Loan (uploads Valid ID front/back required + optional docs)
  - Track Application (by Reference No)

- Staff Portal:
  - Role-based access (menus hidden if not allowed)
  - CI + Manager only can update loan status
  - Cashier is payments-only

## Notes
- Uploads are stored in: `uploads/requirements/`
  Ensure the folder is writable (XAMPP usually OK).

## Common Issues
### Database setup errors (tablespace / FK / duplicates)
Use the setup page again:
`/setup/setup_db.php` resets tables safely.

If MySQL has leftover DB files (rare):
- Drop database `loan_management` in phpMyAdmin
- Re-run setup_db.php

### Multiple XAMPP folders
Always make sure you copied the system into the SAME XAMPP that is running Apache/MySQL.


ENTRY POINTS (Option A)
- Customer: http://localhost/LOAN_MANAGEMENT/customer/login.php
- Staff/Admin: http://localhost/LOAN_MANAGEMENT/staff/login.php
