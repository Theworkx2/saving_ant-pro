<?php
require_once __DIR__ . '/inc/functions.php';

// Require admin access
requireAuth();
if (!$auth->hasRole('admin')) {
    setFlash('warning', 'Only administrators can access this page.');
    redirect('dashboard.php');
}

try {
    $pdo = $auth->getPdo();
    $pdo->beginTransaction();
    
    // 1. Create missing balance records for all users
    $pdo->query("
        INSERT IGNORE INTO user_balances (user_id, balance)
        SELECT id, 0.00 FROM users
        WHERE id NOT IN (SELECT user_id FROM user_balances)
    ");
    
    // 2. Reset any negative balances to 0
    $pdo->query("
        UPDATE user_balances 
        SET balance = 0.00 
        WHERE balance < 0
    ");
    
    // 3. Get all users
    $stmt = $pdo->query("SELECT id FROM users WHERE is_active = 1");
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 4. Recalculate balance for each user
    foreach ($userIds as $userId) {
        // Get all transactions for this user, ordered by date
        $stmt = $pdo->prepare("
            SELECT id, type, amount, created_at
            FROM transactions
            WHERE user_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$userId]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate running balance
        $balance = 0;
        foreach ($transactions as $transaction) {
            if ($transaction['type'] === 'deposit') {
                $balance += $transaction['amount'];
            } else if ($transaction['type'] === 'withdrawal') {
                $balance -= $transaction['amount'];
            }
            
            // Update running balance for this transaction
            $stmt = $pdo->prepare("UPDATE transactions SET balance = ? WHERE id = ?");
            $stmt->execute([$balance, $transaction['id']]);
        }
        
        // Update final balance for this user
        $stmt = $pdo->prepare("UPDATE user_balances SET balance = ? WHERE user_id = ?");
        $stmt->execute([$balance, $userId]);
    }
    
    $pdo->commit();
    setFlash('success', 'All balances have been fixed and recalculated successfully.');
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('warning', 'Error fixing balances: ' . $e->getMessage());
}

redirect('transactions.php');
?>