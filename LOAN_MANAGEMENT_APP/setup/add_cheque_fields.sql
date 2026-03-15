-- Migration: Add cheque fields to payments table
-- Run this in your MariaDB/MySQL client

ALTER TABLE payments ADD COLUMN cheque_number VARCHAR(50) NULL AFTER method,
                      ADD COLUMN cheque_date DATE NULL AFTER cheque_number,
                      ADD COLUMN bank_name VARCHAR(100) NULL AFTER cheque_date,
                      ADD COLUMN account_holder VARCHAR(100) NULL AFTER bank_name;
