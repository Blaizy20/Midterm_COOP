<?php
/**
 * Customer Service
 * 
 * Handles all customer-related data operations.
 * This service is designed to be used by both the legacy web system
 * and the future mobile application.
 * 
 * NOTE: This service is ready for future mobile API integration.
 * The web portal customer pages have been removed. Mobile apps should
 * call these methods via a REST API gateway (not yet implemented).
 */

require_once __DIR__ . '/pdo_db.php';

class CustomerService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get customer by customer_id
     */
    public function getCustomerById($customerId) {
        try {
            $sql = "SELECT * FROM customers WHERE customer_id = ? AND is_active = 1";
            return $this->db->queryOne($sql, [$customerId]);
        } catch (Exception $e) {
            error_log("Get customer error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get customer by user_id
     * Useful for web staff and mobile API lookups
     */
    public function getCustomerByUserId($userId) {
        try {
            $sql = "SELECT * FROM customers WHERE user_id = ? AND is_active = 1";
            return $this->db->queryOne($sql, [$userId]);
        } catch (Exception $e) {
            error_log("Get customer by user error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get customer by customer number
     */
    public function getCustomerByNumber($customerNo) {
        try {
            $sql = "SELECT * FROM customers WHERE customer_no = ? AND is_active = 1";
            return $this->db->queryOne($sql, [$customerNo]);
        } catch (Exception $e) {
            error_log("Get customer by number error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create a new customer record
     * Used by staff when creating customer manually, or by mobile API
     */
    public function createCustomer($data) {
        try {
            // Validate required fields
            $required = ['customer_no', 'first_name', 'last_name', 'contact_no'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            
            // Check if customer number already exists
            $existing = $this->getCustomerByNumber($data['customer_no']);
            if ($existing) {
                throw new Exception("Customer number already exists");
            }
            
            $sql = "INSERT INTO customers 
                    (user_id, customer_no, first_name, last_name, contact_no, email, 
                     province, city, barangay, street, created_at, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)";
            
            $userId = $data['user_id'] ?? null;
            $this->db->exec($sql, [
                $userId,
                $data['customer_no'],
                $data['first_name'],
                $data['last_name'],
                $data['contact_no'],
                $data['email'] ?? null,
                $data['province'] ?? null,
                $data['city'] ?? null,
                $data['barangay'] ?? null,
                $data['street'] ?? null
            ]);
            
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Create customer error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update customer information
     */
    public function updateCustomer($customerId, $data) {
        try {
            $updates = [];
            $params = [];
            $allowed = ['first_name', 'last_name', 'contact_no', 'email', 
                       'province', 'city', 'barangay', 'street'];
            
            foreach ($allowed as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                return true;
            }
            
            $params[] = $customerId;
            $sql = "UPDATE customers SET " . implode(", ", $updates) . " WHERE customer_id = ?";
            
            $this->db->exec($sql, $params);
            return true;
            
        } catch (Exception $e) {
            error_log("Update customer error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get all customers (for staff view)
     * Can optionally filter by active status
     */
    public function getAllCustomers($activeOnly = true, $limit = 100, $offset = 0) {
        try {
            $where = $activeOnly ? "WHERE is_active = 1" : "";
            $sql = "SELECT * FROM customers $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
            return $this->db->queryAll($sql, [$limit, $offset]);
        } catch (Exception $e) {
            error_log("Get all customers error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Search customers by name or number
     */
    public function searchCustomers($query, $limit = 20) {
        try {
            $searchTerm = "%$query%";
            $sql = "SELECT * FROM customers 
                    WHERE (customer_no LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)
                    AND is_active = 1
                    ORDER BY created_at DESC
                    LIMIT ?";
            
            return $this->db->queryAll($sql, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
        } catch (Exception $e) {
            error_log("Search customers error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Count total active customers
     */
    public function countCustomers($activeOnly = true) {
        try {
            $where = $activeOnly ? "WHERE is_active = 1" : "";
            $sql = "SELECT COUNT(*) as count FROM customers $where";
            $result = $this->db->queryOne($sql);
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            error_log("Count customers error: " . $e->getMessage());
            return 0;
        }
    }
}

?>
