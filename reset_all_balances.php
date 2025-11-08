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
    
    // Start transaction
    $pdo->beginTransaction();
    
    // 1. First, clear all balances
    $pdo->query("TRUNCATE TABLE user_balances");
    
    // 2. Reset all transaction balances to NULL
    $pdo->query("UPDATE transactions SET balance = NULL");
    
    // 3. Get all users
    $stmt = $pdo->query("SELECT DISTINCT user_id FROM transactions");
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($userIds as $userId) {
        // 4. Get all transactions for this user, ordered by date
        $stmt = $pdo->prepare("
            SELECT id, type, amount, created_at
            FROM transactions
            WHERE user_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$userId]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 5. Recalculate running balance
        $balance = 0;
        foreach ($transactions as $transaction) {
            if ($transaction['type'] === 'deposit') {
                $balance += $transaction['amount'];
            } else if ($transaction['type'] === 'withdrawal') {
                if ($balance >= $transaction['amount']) {
                    $balance -= $transaction['amount'];
                } else {
                    // If trying to withdraw more than available, adjust the transaction
                    $actualWithdrawal = $balance;
                    $balance = 0;
                    
                    // Update the transaction to only withdraw what was available
                    $stmt = $pdo->prepare("
                        UPDATE transactions 
                        SET amount = ?, 
                            description = CONCAT(description, ' (Adjusted from RWF ', ?, ' due to insufficient funds)')
                        WHERE id = ?
                    ");
                    $stmt->execute([$actualWithdrawal, $transaction['amount'], $transaction['id']]);
                }
            }
            
            // Update the running balance for this transaction
            $stmt = $pdo->prepare("UPDATE transactions SET balance = ? WHERE id = ?");
            $stmt->execute([$balance, $transaction['id']]);
        }
        
        // 6. Update or insert final balance for this user
        $stmt = $pdo->prepare("
            INSERT INTO user_balances (user_id, balance) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE balance = ?
        ");
        $stmt->execute([$userId, $balance, $balance]);
    }
    
    $pdo->commit();
    setFlash('success', 'All balances have been reset and recalculated successfully.');
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('warning', 'Error resetting balances: ' . $e->getMessage());
}

redirect('transactions.php');
?>