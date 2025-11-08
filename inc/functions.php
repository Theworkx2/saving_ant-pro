<?php
// Common functions and constants
require_once __DIR__ . '/auth.php';

// Initialize auth
$auth = new Auth();

// Transaction functions
function createTransaction(array $data): array {
    global $auth;
    
    try {
        $pdo = $auth->getPdo();
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Verify sufficient balance for withdrawals
        $currentBalance = getUserBalance($data['user_id']);
        $amount = floatval($data['amount']);
        
        if ($data['type'] === 'withdrawal') {
            if ($amount > $currentBalance) {
                throw new Exception('Cannot withdraw RWF ' . number_format($amount) . '. Available balance: RWF ' . number_format($currentBalance));
            }
            // Ensure balance never goes below 0
            if ($currentBalance - $amount < 0) {
                $amount = $currentBalance; // Only allow withdrawing what's available
            }
        }
        
        // Calculate new balance based on all transactions including this one
        $newBalance = $currentBalance + ($data['type'] === 'deposit' ? $amount : -$amount);
        
        // Check if payment_method column exists
        $columns = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'payment_method'")->fetchAll();
        $hasPaymentMethod = !empty($columns);

        // Create transaction record
        if ($hasPaymentMethod) {
            $stmt = $pdo->prepare('INSERT INTO transactions 
                (user_id, type, amount, description, balance, payment_method) 
                VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $data['user_id'],
                $data['type'],
                $amount,
                $data['description'],
                $newBalance,
                $data['payment_method'] ?? 'momo'
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO transactions 
                (user_id, type, amount, description, balance) 
                VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $data['user_id'],
                $data['type'],
                $amount,
                $data['description'],
                $newBalance
            ]);
        }
        
        // Update user balance
        $stmt = $pdo->prepare('INSERT INTO user_balances (user_id, balance) 
            VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = ?');
        $stmt->execute([$data['user_id'], $newBalance, $newBalance]);
        
        // Commit transaction
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => ucfirst($data['type']) . ' processed successfully',
            'balance' => $newBalance
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

function recalculateBalance(int $userId): float {
    global $auth;
    $pdo = $auth->getPdo();
    
    // Calculate total from all transactions
    $stmt = $pdo->prepare('
        SELECT 
            COALESCE(SUM(CASE WHEN type = "deposit" THEN amount ELSE -amount END), 0) as balance
        FROM transactions 
        WHERE user_id = ?
    ');
    $stmt->execute([$userId]);
    $balance = floatval($stmt->fetchColumn());
    
    // Update the user_balances table
    $stmt = $pdo->prepare('
        INSERT INTO user_balances (user_id, balance) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE balance = ?
    ');
    $stmt->execute([$userId, $balance, $balance]);
    
    return $balance;
}

function getUserBalance(int $userId): float {
    global $auth;
    $pdo = $auth->getPdo();
    
    // First try to get from user_balances
    $stmt = $pdo->prepare('SELECT balance FROM user_balances WHERE user_id = ?');
    $stmt->execute([$userId]);
    $balance = $stmt->fetchColumn();
    
    // If no balance found or balance is null or negative, recalculate
    if ($balance === false || $balance === null || $balance < 0) {
        $balance = recalculateBalance($userId);
    }
    
    // Ensure balance is never negative
    return max(0, floatval($balance));
}

function getTransactions(array $params): array {
    global $auth;
    $pdo = $auth->getPdo();
    
    $query = 'SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC';
    $queryParams = [$params['user_id']];
    
    if (isset($params['limit'])) {
        $query .= ' LIMIT ?';
        $queryParams[] = $params['limit'];
        
        if (isset($params['offset'])) {
            $query .= ' OFFSET ?';
            $queryParams[] = $params['offset'];
        }
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($queryParams);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTransactionCount(int $userId): int {
    global $auth;
    $pdo = $auth->getPdo();
    
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE user_id = ?');
    $stmt->execute([$userId]);
    return intval($stmt->fetchColumn());
}

// CSRF protection
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(?string $token): bool {
    return $token && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Flash messages
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Redirect helper
function redirect(string $path): void {
    header("Location: $path");
    exit;
}

// Require authentication
function requireLogin(): void {
    global $auth;
    if (!$auth->isLoggedIn()) {
        setFlash('error', 'Please login to access this page');
        redirect('/saving_ant/index.php');
    }
}

// Alias for requireLogin for consistency
function requireAuth(): void {
    requireLogin();
}

// Require specific role
function requireRole(string $role): void {
    global $auth;
    requireLogin();
    if (!$auth->hasRole($role)) {
        setFlash('error', 'You do not have permission to access this page');
        redirect('/saving_ant/dashboard.php');
    }
}

// Check if user has a specific role
function hasRole($user, string $role): bool {
    return is_array($user['roles']) && in_array($role, $user['roles']);
}