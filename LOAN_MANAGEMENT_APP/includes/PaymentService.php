<?php
/**
 * Payment Service
 * 
 * Handles all payment-related operations including recording payments,
 * generating payment receipts, and payment tracking.
 * Used by staff portal and ready for mobile API integration.
 */

require_once __DIR__ . '/pdo_db.php';

class PaymentService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get payment by payment_id
     */
    public function getPaymentById($paymentId) {
        try {
            $sql = "SELECT p.*, 
                           l.reference_no, l.principal_amount, l.remaining_balance,
                           c.customer_no, c.first_name, c.last_name,
                           lo.full_name as loan_officer_name,
                           rb.full_name as received_by_name
                    FROM payments p
                    LEFT JOIN loans l ON l.loan_id = p.loan_id
                    LEFT JOIN customers c ON c.customer_id = l.customer_id
                    LEFT JOIN users lo ON lo.user_id = p.loan_officer_id
                    LEFT JOIN users rb ON rb.user_id = p.received_by
                    WHERE p.payment_id = ?";
            
            return $this->db->queryOne($sql, [$paymentId]);
        } catch (Exception $e) {
            error_log("Get payment error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get payment by OR number
     */
    public function getPaymentByORNo($orNo) {
        try {
            $sql = "SELECT p.*, l.reference_no, c.customer_no, c.first_name, c.last_name
                    FROM payments p
                    LEFT JOIN loans l ON l.loan_id = p.loan_id
                    LEFT JOIN customers c ON c.customer_id = l.customer_id
                    WHERE p.or_no = ?";
            
            return $this->db->queryOne($sql, [$orNo]);
        } catch (Exception $e) {
            error_log("Get payment by OR error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Record a new payment
     * Can be called from staff portal or future mobile API
     */
    public function recordPayment($loanId, $data) {
        try {
            $required = ['amount', 'payment_date', 'or_no'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            
            // Validate loan exists
            $sql = "SELECT loan_id, remaining_balance FROM loans WHERE loan_id = ?";
            $loan = $this->db->queryOne($sql, [$loanId]);
            if (!$loan) {
                throw new Exception("Loan not found");
            }
            
            $amount = floatval($data['amount']);
            if ($amount <= 0) {
                throw new Exception("Invalid payment amount");
            }
            
            // Check if OR number already exists
            $existingPayment = $this->getPaymentByORNo($data['or_no']);
            if ($existingPayment) {
                throw new Exception("OR number already exists");
            }
            
            // Determine payment method details
            $method = strtoupper($data['method'] ?? 'CASH');
            $methodDetails = [];
            
            if ($method === 'CHEQUE') {
                $methodDetails = [
                    'cheque_number' => $data['cheque_number'] ?? null,
                    'cheque_date' => $data['cheque_date'] ?? null,
                    'bank_name' => $data['bank_name'] ?? null,
                    'account_holder' => $data['account_holder'] ?? null,
                    'bank_reference_no' => $data['bank_reference_no'] ?? null
                ];
            } elseif ($method === 'GCASH' || $method === 'DIGITAL') {
                $methodDetails = [
                    'gcash_reference_no' => $data['gcash_reference_no'] ?? null,
                    'bank_reference_no' => $data['bank_reference_no'] ?? null
                ];
            }
            
            // Insert payment record
            $sql = "INSERT INTO payments 
                    (loan_id, amount, payment_date, method, cheque_number, cheque_date, 
                     bank_name, account_holder, bank_reference_no, gcash_reference_no, 
                     or_no, loan_officer_id, received_by, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $this->db->exec($sql, [
                $loanId,
                $amount,
                $data['payment_date'],
                $method,
                $methodDetails['cheque_number'] ?? null,
                $methodDetails['cheque_date'] ?? null,
                $methodDetails['bank_name'] ?? null,
                $methodDetails['account_holder'] ?? null,
                $methodDetails['bank_reference_no'] ?? null,
                $methodDetails['gcash_reference_no'] ?? null,
                $data['or_no'],
                $data['loan_officer_id'] ?? null,
                $data['received_by'] ?? null,
                $data['notes'] ?? null
            ]);
            
            $paymentId = $this->db->lastInsertId();
            
            // Update loan remaining balance
            $newBalance = max(0, $loan['remaining_balance'] - $amount);
            $newStatus = ($newBalance <= 0) ? 'CLOSED' : 'ACTIVE';
            
            $sql = "UPDATE loans SET remaining_balance = ?, status = ? WHERE loan_id = ?";
            $this->db->exec($sql, [$newBalance, $newStatus, $loanId]);
            
            return [
                'payment_id' => $paymentId,
                'new_balance' => $newBalance,
                'loan_status' => $newStatus
            ];
            
        } catch (Exception $e) {
            error_log("Record payment error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Edit an existing payment
     */
    public function editPayment($paymentId, $data) {
        try {
            $payment = $this->getPaymentById($paymentId);
            if (!$payment) {
                throw new Exception("Payment not found");
            }
            
            $updates = [];
            $params = [];
            $allowed = ['method', 'cheque_number', 'cheque_date', 'bank_name', 
                       'account_holder', 'bank_reference_no', 'gcash_reference_no', 'notes'];
            
            // If amount is being changed, we need to adjust loan balance
            $amountChanged = false;
            $oldAmount = $payment['amount'];
            $newAmount = $oldAmount;
            
            if (isset($data['amount'])) {
                $newAmount = floatval($data['amount']);
                if ($newAmount <= 0) {
                    throw new Exception("Invalid payment amount");
                }
                $updates[] = "amount = ?";
                $params[] = $newAmount;
                $amountChanged = true;
            }
            
            foreach ($allowed as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                return true;
            }
            
            // Begin transaction
            $this->db->beginTransaction();
            
            try {
                $params[] = $paymentId;
                $sql = "UPDATE payments SET " . implode(", ", $updates) . " WHERE payment_id = ?";
                $this->db->exec($sql, $params);
                
                // If amount changed, adjust loan balance
                if ($amountChanged) {
                    $difference = $newAmount - $oldAmount;
                    $sql = "UPDATE loans SET remaining_balance = remaining_balance - ? WHERE loan_id = ?";
                    $this->db->exec($sql, [$difference, $payment['loan_id']]);
                }
                
                $this->db->commit();
                return true;
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("Edit payment error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get all payments for a loan
     */
    public function getLoanPayments($loanId) {
        try {
            $sql = "SELECT p.*, lo.full_name as loan_officer_name, rb.full_name as received_by_name
                    FROM payments p
                    LEFT JOIN users lo ON lo.user_id = p.loan_officer_id
                    LEFT JOIN users rb ON rb.user_id = p.received_by
                    WHERE p.loan_id = ?
                    ORDER BY p.payment_date DESC";
            
            return $this->db->queryAll($sql, [$loanId]);
        } catch (Exception $e) {
            error_log("Get loan payments error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all payments (for staff view)
     */
    public function getAllPayments($limit = 100, $offset = 0) {
        try {
            $sql = "SELECT p.*, 
                           l.reference_no, c.customer_no, c.first_name, c.last_name,
                           lo.full_name as loan_officer_name
                    FROM payments p
                    LEFT JOIN loans l ON l.loan_id = p.loan_id
                    LEFT JOIN customers c ON c.customer_id = l.customer_id
                    LEFT JOIN users lo ON lo.user_id = p.loan_officer_id
                    ORDER BY p.payment_date DESC
                    LIMIT ? OFFSET ?";
            
            return $this->db->queryAll($sql, [$limit, $offset]);
        } catch (Exception $e) {
            error_log("Get all payments error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get payments by date range
     */
    public function getPaymentsByDateRange($startDate, $endDate) {
        try {
            $sql = "SELECT p.*, 
                           l.reference_no, c.customer_no, c.first_name, c.last_name,
                           lo.full_name as loan_officer_name
                    FROM payments p
                    LEFT JOIN loans l ON l.loan_id = p.loan_id
                    LEFT JOIN customers c ON c.customer_id = l.customer_id
                    LEFT JOIN users lo ON lo.user_id = p.loan_officer_id
                    WHERE DATE(p.payment_date) >= ? AND DATE(p.payment_date) <= ?
                    ORDER BY p.payment_date DESC";
            
            return $this->db->queryAll($sql, [$startDate, $endDate]);
        } catch (Exception $e) {
            error_log("Get payments by date error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get payment summary statistics
     */
    public function getPaymentSummaryStats() {
        try {
            $sql = "SELECT 
                           COUNT(*) as total_payments,
                           SUM(amount) as total_amount,
                           AVG(amount) as avg_amount,
                           MAX(amount) as max_amount,
                           MIN(amount) as min_amount,
                           COUNT(DISTINCT loan_id) as loans_with_payments
                    FROM payments";
            
            return $this->db->queryOne($sql);
        } catch (Exception $e) {
            error_log("Get payment stats error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get payment summary by method
     */
    public function getPaymentSummaryByMethod() {
        try {
            $sql = "SELECT method, COUNT(*) as count, SUM(amount) as total_amount
                    FROM payments
                    GROUP BY method
                    ORDER BY total_amount DESC";
            
            return $this->db->queryAll($sql);
        } catch (Exception $e) {
            error_log("Get payment summary by method error: " . $e->getMessage());
            return [];
        }
    }
}

?>
