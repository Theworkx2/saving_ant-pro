<?php
require_once 'functions.php';

// Require admin role
requireRole('admin');

// Get the search query
$query = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'suggestions'; // suggestions or full-search

// Initialize response array
$response = [
    'success' => false,
    'data' => [],
    'message' => ''
];

try {
    $pdo = $auth->getPdo();
    
    if ($type === 'suggestions') {
        // Quick search for suggestions
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.username,
                u.email,
                u.full_name,
                u.is_active,
                GROUP_CONCAT(r.name) as roles,
                (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.id) as transaction_count,
                (SELECT COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0)
                 FROM transactions t WHERE t.user_id = u.id) as balance,
                (SELECT created_at FROM transactions t WHERE t.user_id = u.id 
                 ORDER BY created_at DESC LIMIT 1) as last_transaction_date
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE 
                u.full_name LIKE ? OR 
                u.email LIKE ? OR 
                u.username LIKE ?
            GROUP BY u.id, u.username, u.email, u.full_name, u.is_active
            LIMIT 5
        ");
        
        $searchTerm = "%{$query}%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        
    } else {
        // Detailed search with transaction analysis
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.username,
                u.email,
                u.full_name,
                u.is_active,
                GROUP_CONCAT(r.name) as roles,
                (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.id) as transaction_count,
                (SELECT COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0)
                 FROM transactions t WHERE t.user_id = u.id) as balance,
                (SELECT created_at FROM transactions t WHERE t.user_id = u.id 
                 ORDER BY created_at DESC LIMIT 1) as last_transaction_date,
                (SELECT payment_method FROM transactions t WHERE t.user_id = u.id 
                 GROUP BY payment_method ORDER BY COUNT(*) DESC LIMIT 1) as preferred_payment,
                (SELECT AVG(amount) FROM transactions t WHERE t.user_id = u.id 
                 AND type = 'deposit') as avg_deposit,
                (SELECT AVG(amount) FROM transactions t WHERE t.user_id = u.id 
                 AND type = 'withdrawal') as avg_withdrawal
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE 
                u.full_name LIKE ? OR 
                u.email LIKE ? OR 
                u.username LIKE ?
            GROUP BY u.id, u.username, u.email, u.full_name, u.is_active
        ");
        
        $searchTerm = "%{$query}%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    }
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process each user's data
    foreach ($users as &$user) {
        // Convert roles from comma-separated string to array
        $user['roles'] = $user['roles'] ? explode(',', $user['roles']) : [];
        
        // Add insights based on user data
        $user['insights'] = [];
        
        if ($user['transaction_count'] > 0) {
            // Activity level
            if ($user['transaction_count'] > 50) {
                $user['insights'][] = "Very Active User";
            } elseif ($user['transaction_count'] > 20) {
                $user['insights'][] = "Active User";
            }
            
            // Balance insights
            if ($user['balance'] > 1000000) {
                $user['insights'][] = "High Balance";
            } elseif ($user['balance'] > 500000) {
                $user['insights'][] = "Medium Balance";
            }
            
            // Transaction patterns
            if (isset($user['avg_deposit']) && isset($user['avg_withdrawal'])) {
                if ($user['avg_deposit'] > $user['avg_withdrawal'] * 2) {
                    $user['insights'][] = "Strong Saver";
                }
                if ($user['avg_deposit'] < $user['avg_withdrawal']) {
                    $user['insights'][] = "Frequent Withdrawer";
                }
            }
        } else {
            $user['insights'][] = "New User";
        }
        
        // Format dates
        if ($user['last_transaction_date']) {
            $user['last_transaction_date'] = date('Y-m-d H:i:s', strtotime($user['last_transaction_date']));
        }
        
        // Format numbers
        $user['balance'] = number_format($user['balance'], 0);
        if (isset($user['avg_deposit'])) {
            $user['avg_deposit'] = number_format($user['avg_deposit'], 0);
        }
        if (isset($user['avg_withdrawal'])) {
            $user['avg_withdrawal'] = number_format($user['avg_withdrawal'], 0);
        }
    }
    
    $response['success'] = true;
    $response['data'] = $users;
    
} catch (Exception $e) {
    $response['message'] = 'Error processing search: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>