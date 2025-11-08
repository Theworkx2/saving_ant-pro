<?php
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

// Function to get latest database snapshot
function getDatabaseSnapshot() {
    global $auth;
    $pdo = $auth->getPdo();
    
    $snapshot = [];
    
    // Get latest transactions
    $stmt = $pdo->query('
        SELECT t.*, u.full_name, u.username 
        FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        ORDER BY t.created_at DESC 
        LIMIT 10
    ');
    $snapshot['latest_transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total balances
    $stmt = $pdo->query('SELECT SUM(balance) as total_balance FROM user_balances');
    $snapshot['total_balance'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_balance'] ?? 0;
    
    // Get user count
    $stmt = $pdo->query('SELECT COUNT(*) as user_count FROM users');
    $snapshot['user_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['user_count'] ?? 0;
    
    // Get today's transactions
    $stmt = $pdo->query('
        SELECT COUNT(*) as today_count, 
               SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as today_deposits,
               SUM(CASE WHEN type = "withdrawal" THEN amount ELSE 0 END) as today_withdrawals
        FROM transactions 
        WHERE DATE(created_at) = CURDATE()
    ');
    $snapshot['today_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Add timestamp
    $snapshot['timestamp'] = date('Y-m-d H:i:s');
    
    return $snapshot;
}

// Check if request is for snapshot
if (isset($_GET['action']) && $_GET['action'] === 'get_snapshot') {
    echo json_encode(getDatabaseSnapshot());
    exit;
}

// Check if request is to save snapshot
if (isset($_GET['action']) && $_GET['action'] === 'save_snapshot') {
    $snapshot = getDatabaseSnapshot();
    
    // Save snapshot to a JSON file with timestamp
    $filename = __DIR__ . '/../data/snapshots/' . date('Y-m-d_H-i-s') . '.json';
    
    // Create directory if it doesn't exist
    if (!file_exists(__DIR__ . '/../data/snapshots')) {
        mkdir(__DIR__ . '/../data/snapshots', 0777, true);
    }
    
    // Save snapshot
    file_put_contents($filename, json_encode($snapshot, JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'message' => 'Snapshot saved', 'filename' => basename($filename)]);
    exit;
}
?>