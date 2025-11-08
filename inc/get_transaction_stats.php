<?php

function getTransactionStats($userId = null) {
    global $auth;
    $pdo = $auth->getPdo();
    
    $stats = [];
    
    // Total Platform Balance for admins, or user balance for regular users
    if ($auth->hasRole('admin') && !$userId) {
        $stmt = $pdo->query('SELECT SUM(balance) as total FROM user_balances');
        $stats['total_balance'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } else {
        $stmt = $pdo->prepare('SELECT balance FROM user_balances WHERE user_id = ?');
        $stmt->execute([$userId ?? $auth->getUserId()]);
        $stats['total_balance'] = $stmt->fetch(PDO::FETCH_ASSOC)['balance'] ?? 0;
    }
    
    // Active Users Count (for admins)
    if ($auth->hasRole('admin')) {
        $stmt = $pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1');
        $stats['active_users'] = $stmt->fetchColumn();
    }
    
    // Today's Transactions
    $today = date('Y-m-d');
    $sql = 'SELECT 
                COUNT(*) as total_count,
                SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as total_deposits,
                SUM(CASE WHEN type = "withdrawal" THEN amount ELSE 0 END) as total_withdrawals
            FROM transactions 
            WHERE DATE(created_at) = ?';
            
    if ($userId) {
        $sql .= ' AND user_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$today, $userId]);
    } else if (!$auth->hasRole('admin')) {
        $sql .= ' AND user_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$today, $auth->getUserId()]);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$today]);
    }
    
    $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['today'] = [
        'count' => $todayStats['total_count'] ?? 0,
        'deposits' => $todayStats['total_deposits'] ?? 0,
        'withdrawals' => $todayStats['total_withdrawals'] ?? 0
    ];
    
    // Recent Transactions
    $sql = 'SELECT t.*, u.full_name, u.username 
            FROM transactions t 
            JOIN users u ON t.user_id = u.id ';
            
    if ($userId) {
        $sql .= ' WHERE t.user_id = ?';
        $sql .= ' ORDER BY t.created_at DESC LIMIT 5';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
    } else if (!$auth->hasRole('admin')) {
        $sql .= ' WHERE t.user_id = ?';
        $sql .= ' ORDER BY t.created_at DESC LIMIT 5';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$auth->getUserId()]);
    } else {
        $sql .= ' ORDER BY t.created_at DESC LIMIT 5';
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    
    $stats['recent_transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

?>