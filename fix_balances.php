<?php
require_once __DIR__ . '/inc/functions.php';

// Require admin role
requireRole('admin');

try {
    $pdo = $auth->getPdo();
    
    // Create user_balances table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_balances (
            user_id INT PRIMARY KEY,
            balance DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");
    
    // Get all users
    $stmt = $pdo->query("SELECT id FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $updatedCount = 0;
    
    // Recalculate balance for each user
    foreach ($users as $userId) {
        $balance = recalculateBalance($userId);
        if ($balance > 0) {
            $updatedCount++;
        }
    }
    
    setFlash('success', "Successfully updated balances for {$updatedCount} users.");
    
} catch (Exception $e) {
    setFlash('error', 'Error fixing balances: ' . $e->getMessage());
}

// Redirect back to dashboard
redirect('/saving_ant/dashboard.php');
?>