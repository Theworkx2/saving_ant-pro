<?php
require_once 'functions.php';

// Require admin role
requireRole('admin');

// Get the search query and type
$query = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'suggestions';

// Initialize response array
$response = [
    'success' => false,
    'data' => [],
    'message' => ''
];

try {
    $pdo = $auth->getPdo();
    
    // Build the base query with common user data
    $baseQuery = "
        SELECT 
            u.id,
            u.username,
            u.email,
            u.full_name,
            u.is_active,
            u.created_at as registration_date,
            GROUP_CONCAT(DISTINCT r.name) as roles,
            COUNT(DISTINCT t.id) as transaction_count,
            COALESCE(SUM(CASE WHEN t.type = 'deposit' THEN t.amount ELSE -t.amount END), 0) as balance,
            MAX(t.created_at) as last_transaction_date,
            (
                SELECT payment_method 
                FROM transactions 
                WHERE user_id = u.id 
                GROUP BY payment_method 
                ORDER BY COUNT(*) DESC 
                LIMIT 1
            ) as preferred_payment,
            COALESCE(AVG(CASE WHEN t.type = 'deposit' THEN t.amount END), 0) as avg_deposit,
            COALESCE(AVG(CASE WHEN t.type = 'withdrawal' THEN t.amount END), 0) as avg_withdrawal,
            COUNT(DISTINCT CASE WHEN t.type = 'deposit' THEN t.id END) as deposit_count,
            COUNT(DISTINCT CASE WHEN t.type = 'withdrawal' THEN t.id END) as withdrawal_count,
            COALESCE(MAX(CASE WHEN t.type = 'deposit' THEN t.amount END), 0) as largest_deposit,
            COALESCE(MAX(CASE WHEN t.type = 'withdrawal' THEN t.amount END), 0) as largest_withdrawal
    ";

    // Add monthly statistics for full search
    if ($type === 'full-search') {
        $baseQuery .= ",
            (
                SELECT COUNT(*) 
                FROM transactions t2 
                WHERE t2.user_id = u.id 
                AND t2.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ) as transactions_last_30_days,
            (
                SELECT COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0)
                FROM transactions t2 
                WHERE t2.user_id = u.id 
                AND t2.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ) as balance_change_30_days
        ";
    }

    $baseQuery .= "
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        LEFT JOIN transactions t ON u.id = t.user_id
        WHERE 1=1
    ";

    // Add search conditions
    $searchConditions = [];
    $params = [];

    // Parse the search query for advanced filters
    if (preg_match('/balance\s*(>|<|>=|<=|=)\s*(\d+)/', $query, $matches)) {
        $operator = $matches[1];
        $amount = $matches[2];
        $searchConditions[] = "(SELECT COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0) FROM transactions WHERE user_id = u.id) $operator ?";
        $params[] = $amount;
    }

    // Search by payment method
    if (stripos($query, 'momo') !== false || stripos($query, 'bank') !== false) {
        $searchConditions[] = "EXISTS (SELECT 1 FROM transactions WHERE user_id = u.id AND LOWER(payment_method) LIKE ?)";
        $params[] = '%' . strtolower($query) . '%';
    }

    // Search by role
    if (stripos($query, 'admin') !== false || stripos($query, 'manager') !== false || stripos($query, 'user') !== false) {
        $searchConditions[] = "EXISTS (SELECT 1 FROM user_roles ur2 JOIN roles r2 ON ur2.role_id = r2.id WHERE ur2.user_id = u.id AND LOWER(r2.name) LIKE ?)";
        $params[] = '%' . strtolower($query) . '%';
    }

    // Search by activity status
    if (stripos($query, 'active') !== false) {
        $searchConditions[] = "u.is_active = 1";
    } else if (stripos($query, 'inactive') !== false) {
        $searchConditions[] = "u.is_active = 0";
    }

    // Basic text search
    $searchConditions[] = "(
        u.full_name LIKE ? OR 
        u.email LIKE ? OR 
        u.username LIKE ?
    )";
    $searchTerm = "%{$query}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);

    if (!empty($searchConditions)) {
        $baseQuery .= " AND (" . implode(" OR ", $searchConditions) . ")";
    }

    // Group by and limit
    $baseQuery .= "
        GROUP BY u.id, u.username, u.email, u.full_name, u.is_active, u.created_at
    ";

    if ($type === 'suggestions') {
        $baseQuery .= " LIMIT 5";
    }

    $stmt = $pdo->prepare($baseQuery);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process and enhance user data
    foreach ($users as &$user) {
        // Convert roles from comma-separated string to array
        $user['roles'] = $user['roles'] ? explode(',', $user['roles']) : [];
        
        // Generate AI-powered insights
        $user['insights'] = generateUserInsights($user);
        
        // Add transaction patterns
        if ($type === 'full-search') {
            $user['patterns'] = analyzeTransactionPatterns($user);
        }

        // Format numbers and dates
        $user = formatUserData($user);
    }

    $response['success'] = true;
    $response['data'] = $users;
    
} catch (Exception $e) {
    $response['message'] = 'Error processing search: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);

/**
 * Generate insights based on user data
 */
function generateUserInsights($user) {
    $insights = [];
    
    // Activity level insights
    if ($user['transaction_count'] > 50) {
        $insights[] = ['text' => 'Very Active User', 'type' => 'positive'];
    } elseif ($user['transaction_count'] > 20) {
        $insights[] = ['text' => 'Active User', 'type' => 'positive'];
    } elseif ($user['transaction_count'] === 0) {
        $insights[] = ['text' => 'New User', 'type' => 'neutral'];
    }

    // Balance insights
    if ($user['balance'] > 1000000) {
        $insights[] = ['text' => 'High Balance', 'type' => 'positive'];
    } elseif ($user['balance'] > 500000) {
        $insights[] = ['text' => 'Medium Balance', 'type' => 'neutral'];
    } elseif ($user['balance'] < 0) {
        $insights[] = ['text' => 'Negative Balance', 'type' => 'negative'];
    }

    // Transaction patterns
    if ($user['deposit_count'] > 0 && $user['withdrawal_count'] > 0) {
        $ratio = $user['deposit_count'] / $user['withdrawal_count'];
        if ($ratio > 2) {
            $insights[] = ['text' => 'Strong Saver', 'type' => 'positive'];
        } elseif ($ratio < 0.5) {
            $insights[] = ['text' => 'Frequent Withdrawer', 'type' => 'warning'];
        }
    }

    // Large transaction insights
    if ($user['largest_deposit'] > 1000000) {
        $insights[] = ['text' => 'Large Deposits', 'type' => 'positive'];
    }
    if ($user['largest_withdrawal'] > 500000) {
        $insights[] = ['text' => 'Large Withdrawals', 'type' => 'warning'];
    }

    return $insights;
}

/**
 * Analyze transaction patterns for full search
 */
function analyzeTransactionPatterns($user) {
    $patterns = [];

    // Monthly activity trend
    if (isset($user['transactions_last_30_days'])) {
        $monthlyAvg = $user['transaction_count'] / (max(1, (time() - strtotime($user['registration_date'])) / (30 * 24 * 60 * 60)));
        $recentActivity = $user['transactions_last_30_days'];
        
        if ($recentActivity > $monthlyAvg * 1.5) {
            $patterns['activity_trend'] = ['direction' => 'up', 'text' => 'Increasing Activity'];
        } elseif ($recentActivity < $monthlyAvg * 0.5) {
            $patterns['activity_trend'] = ['direction' => 'down', 'text' => 'Decreasing Activity'];
        }
    }

    // Preferred timing analysis
    // Add logic for analyzing transaction timing patterns

    // Payment method consistency
    if ($user['preferred_payment']) {
        $patterns['payment_preference'] = ['method' => $user['preferred_payment'], 'text' => 'Preferred Payment Method'];
    }

    return $patterns;
}

/**
 * Format user data for display
 */
function formatUserData($user) {
    // Format numbers
    $user['balance'] = number_format($user['balance'], 0);
    $user['avg_deposit'] = number_format($user['avg_deposit'], 0);
    $user['avg_withdrawal'] = number_format($user['avg_withdrawal'], 0);
    $user['largest_deposit'] = number_format($user['largest_deposit'], 0);
    $user['largest_withdrawal'] = number_format($user['largest_withdrawal'], 0);

    // Format dates
    if ($user['last_transaction_date']) {
        $user['last_transaction_date'] = date('Y-m-d H:i:s', strtotime($user['last_transaction_date']));
    }
    if ($user['registration_date']) {
        $user['registration_date'] = date('Y-m-d H:i:s', strtotime($user['registration_date']));
    }

    return $user;
}
?>