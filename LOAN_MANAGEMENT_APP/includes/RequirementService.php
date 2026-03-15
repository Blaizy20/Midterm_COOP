<?php
/**
 * Requirement Service
 * 
 * Handles document/requirement uploads and management.
 * Tracks which role uploaded documents (CUSTOMER via mobile or STAFF via web).
 * Ready for future mobile API integration.
 */

require_once __DIR__ . '/pdo_db.php';

class RequirementService {
    private $db;
    private $uploadDir = __DIR__ . '/../uploads/requirements';
    
    public function __construct() {
        $this->db = Database::getInstance();
        
        // Ensure upload directory exists
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Get requirement by requirement_id
     */
    public function getRequirementById($requirementId) {
        try {
            $sql = "SELECT r.*, u.full_name as uploaded_by_name
                    FROM requirements r
                    LEFT JOIN users u ON u.user_id = r.uploaded_by_user
                    WHERE r.requirement_id = ?";
            
            return $this->db->queryOne($sql, [$requirementId]);
        } catch (Exception $e) {
            error_log("Get requirement error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all requirements for a loan
     */
    public function getLoanRequirements($loanId) {
        try {
            $sql = "SELECT r.*, u.full_name as uploaded_by_name
                    FROM requirements r
                    LEFT JOIN users u ON u.user_id = r.uploaded_by_user
                    WHERE r.loan_id = ?
                    ORDER BY r.uploaded_at DESC";
            
            return $this->db->queryAll($sql, [$loanId]);
        } catch (Exception $e) {
            error_log("Get loan requirements error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get requirements by code
     */
    public function getRequirementsByCode($loanId, $requirementCode) {
        try {
            $sql = "SELECT r.*, u.full_name as uploaded_by_name
                    FROM requirements r
                    LEFT JOIN users u ON u.user_id = r.uploaded_by_user
                    WHERE r.loan_id = ? AND r.requirement_code = ?
                    ORDER BY r.uploaded_at DESC";
            
            return $this->db->queryAll($sql, [$loanId, $requirementCode]);
        } catch (Exception $e) {
            error_log("Get requirements by code error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add a requirement/document
     * Typically called when staff uploads a document or mobile app sends requirements
     */
    public function addRequirement($loanId, $requirementCode, $requirementName, 
                                   $filePath, $uploadedByRole = 'STAFF', $uploadedByUser = null, $notes = null) {
        try {
            // Validate loan exists
            $sql = "SELECT loan_id FROM loans WHERE loan_id = ?";
            $loan = $this->db->queryOne($sql, [$loanId]);
            if (!$loan) {
                throw new Exception("Loan not found");
            }
            
            // Validate role
            $validRoles = ['CUSTOMER', 'STAFF'];
            if (!in_array($uploadedByRole, $validRoles, true)) {
                throw new Exception("Invalid upload role");
            }
            
            $sql = "INSERT INTO requirements 
                    (loan_id, requirement_code, requirement_name, file_path, 
                     uploaded_by_role, uploaded_by_user, uploaded_at, notes)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";
            
            $this->db->exec($sql, [
                $loanId,
                $requirementCode,
                $requirementName,
                $filePath,
                $uploadedByRole,
                $uploadedByUser,
                $notes
            ]);
            
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Add requirement error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update requirement
     */
    public function updateRequirement($requirementId, $data) {
        try {
            $updates = [];
            $params = [];
            $allowed = ['requirement_name', 'notes'];
            
            foreach ($allowed as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                return true;
            }
            
            $params[] = $requirementId;
            $sql = "UPDATE requirements SET " . implode(", ", $updates) . " WHERE requirement_id = ?";
            $this->db->exec($sql, $params);
            
            return true;
        } catch (Exception $e) {
            error_log("Update requirement error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Delete requirement
     */
    public function deleteRequirement($requirementId) {
        try {
            $requirement = $this->getRequirementById($requirementId);
            if (!$requirement) {
                throw new Exception("Requirement not found");
            }
            
            // Delete file if it exists
            $filePath = $this->uploadDir . '/' . basename($requirement['file_path']);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Delete database record
            $sql = "DELETE FROM requirements WHERE requirement_id = ?";
            $this->db->exec($sql, [$requirementId]);
            
            return true;
        } catch (Exception $e) {
            error_log("Delete requirement error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Count requirements for a loan
     */
    public function countLoanRequirements($loanId) {
        try {
            $sql = "SELECT COUNT(*) as count FROM requirements WHERE loan_id = ?";
            $result = $this->db->queryOne($sql, [$loanId]);
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            error_log("Count requirements error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get upload statistics
     */
    public function getUploadStats() {
        try {
            $sql = "SELECT 
                           uploaded_by_role,
                           COUNT(*) as count
                    FROM requirements
                    GROUP BY uploaded_by_role";
            
            return $this->db->queryAll($sql);
        } catch (Exception $e) {
            error_log("Get upload stats error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get full upload path for serving files
     */
    public function getUploadPath($filename) {
        $safeName = basename($filename);
        return $this->uploadDir . '/' . $safeName;
    }
    
    /**
     * Get upload URL (for web serving)
     */
    public function getUploadUrl($filename) {
        $safeName = basename($filename);
        return '/uploads/requirements/' . urlencode($safeName);
    }
}

?>
