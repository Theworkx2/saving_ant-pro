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
    
    // First, reset any negative balances to 0
    $stmt = $pdo->query("
        UPDATE user_balances 
        SET balance = 0 
        WHERE balance < 0
    ");

    // Get all transactions ordered by date
    $stmt = $pdo->query("
        SELECT id, user_id, type, amount, created_at
        FROM transactions
        ORDER BY created_at ASC
    ");
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recalculate balances
    $userBalances = [];
    $updates = [];
    
    foreach ($transactions as $transaction) {
        $userId = $transaction['user_id'];
        if (!isset($userBalances[$userId])) {
            $userBalances[$userId] = 0;
        }
        
        // Calculate new balance
        if ($transaction['type'] === 'deposit') {
            $userBalances[$userId] += $transaction['amount'];
        } else if ($transaction['type'] === 'withdrawal') {
            // If this withdrawal would exceed available balance
            if ($userBalances[$userId] < $transaction['amount']) {
                // Only withdraw what's available
                $actualWithdrawal = $userBalances[$userId];
                $userBalances[$userId] = 0; // Set to 0 instead of going negative
                
                // Mark transaction for adjustment
                $updates[] = [
                    'id' => $transaction['id'],
                    'type' => 'withdrawal',
                    'original_amount' => $transaction['amount'],
                    'adjusted_amount' => $actualWithdrawal,
                    'user_id' => $userId
                ];
                
                // Update the transaction amount to what was actually available
                $stmt = $pdo->prepare("UPDATE transactions SET amount = ? WHERE id = ?");
                $stmt->execute([$actualWithdrawal, $transaction['id']]);
            } else {
                $userBalances[$userId] -= $transaction['amount'];
            }
        }
        
        // Update transaction balance
        $stmt = $pdo->prepare("UPDATE transactions SET balance = ? WHERE id = ?");
        $stmt->execute([$userBalances[$userId], $transaction['id']]);
    }
    
    // Update user balances table
    foreach ($userBalances as $userId => $balance) {
        $stmt = $pdo->prepare("
            INSERT INTO user_balances (user_id, balance)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE balance = ?
        ");
        $stmt->execute([$userId, $balance, $balance]);
    }
    
    // Log any changes made
    if (!empty($updates)) {
        $log = fopen(__DIR__ . '/balance_fixes.log', 'a');
        foreach ($updates as $update) {
            fwrite($log, date('Y-m-d H:i:s') . " - Transaction ID: {$update['id']} - Changed from {$update['original_type']} to {$update['type']} - Amount: {$update['amount']} - User ID: {$update['user_id']}\n");
        }
        fclose($log);
    }
    
    $pdo->commit();
    setFlash('success', 'Balance recalculation completed successfully. ' . 
             (count($updates) > 0 ? count($updates) . ' transactions were adjusted.' : 'No adjustments were needed.'));
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('warning', 'Error fixing balances: ' . $e->getMessage());
}

redirect('transactions.php');
?>