<?php
require_once __DIR__ . '/inc/functions.php';

// Require login and ensure user has appropriate permissions
requireAuth();
$user = $auth->getUser();

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        setFlash('warning', 'Invalid request. Please try again.');
        redirect('transactions.php');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
            $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
            $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
            $paymentMethod = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);

            if ($amount && $type) {
                $result = createTransaction([
                    'user_id' => $user['id'],
                    'type' => $type,
                    'amount' => $amount,
                    'description' => $description,
                    'payment_method' => $paymentMethod
                ]);

                setFlash($result['success'] ? 'success' : 'warning', $result['message']);
            } else {
                setFlash('warning', 'Please provide valid transaction details.');
            }
            break;

        case 'update':
            if ($auth->hasRole('admin')) {
                $transactionId = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
                $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
                $paymentMethod = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
                $newAmount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
                $type = filter_input(INPUT_POST, 'transaction_type', FILTER_SANITIZE_STRING);

                if ($transactionId && $newAmount) {
                    try {
                        $pdo = $auth->getPdo();
                        $pdo->beginTransaction();

                        // Get current balance before update
                        $currentBalance = getUserBalance($user['id']);

                        // Update transaction
                        $stmt = $pdo->prepare('UPDATE transactions SET description = ?, payment_method = ?, amount = ? WHERE id = ? AND user_id = ?');
                        $stmt->execute([$description, $paymentMethod, $newAmount, $transactionId, $user['id']]);

                        // Recalculate all balances after this transaction
                        $stmt = $pdo->prepare('
                            SELECT id, type, amount
                            FROM transactions 
                            WHERE user_id = ? 
                            ORDER BY created_at ASC
                        ');
                        $stmt->execute([$user['id']]);
                        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $runningBalance = 0;
                        foreach ($transactions as $trans) {
                            $runningBalance += $trans['type'] === 'deposit' ? $trans['amount'] : -$trans['amount'];
                            
                            // Update the balance for this transaction
                            $stmt = $pdo->prepare('UPDATE transactions SET balance = ? WHERE id = ?');
                            $stmt->execute([$runningBalance, $trans['id']]);
                        }

                        // Update final balance in user_balances
                        $stmt = $pdo->prepare('
                            INSERT INTO user_balances (user_id, balance) 
                            VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE balance = ?
                        ');
                        $stmt->execute([$user['id'], $runningBalance, $runningBalance]);
                        
                        $pdo->commit();
                        setFlash('success', 'Transaction updated successfully.');
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        setFlash('warning', 'Failed to update transaction: ' . $e->getMessage());
                    }
                }
            } else {
                setFlash('warning', 'You do not have permission to update transactions.');
            }
            break;

        case 'bulk_delete':
            if ($auth->hasRole('admin')) {
                $transactionIds = json_decode($_POST['transaction_ids'] ?? '[]', true);
                
                if (!empty($transactionIds)) {
                    try {
                        $pdo = $auth->getPdo();
                        $pdo->beginTransaction();
                        
                        $placeholders = str_repeat('?,', count($transactionIds) - 1) . '?';
                        $params = array_merge($transactionIds, [$user['id']]);
                        
                        // Get all transactions to be deleted
                        $stmt = $pdo->prepare("SELECT id, type, amount FROM transactions WHERE id IN ($placeholders) AND user_id = ?");
                        $stmt->execute($params);
                        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($transactions)) {
                            // Calculate total balance adjustment
                            $balanceAdjustment = 0;
                            foreach ($transactions as $transaction) {
                                $balanceAdjustment += $transaction['type'] === 'deposit' ? 
                                    -$transaction['amount'] : $transaction['amount'];
                            }
                            
                            // Update user balance
                            $stmt = $pdo->prepare('UPDATE user_balances SET balance = balance + ? WHERE user_id = ?');
                            $stmt->execute([$balanceAdjustment, $user['id']]);
                            
                            // Delete transactions
                            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id IN ($placeholders) AND user_id = ?");
                            $stmt->execute($params);
                            
                            $pdo->commit();
                            setFlash('success', count($transactions) . ' transaction(s) deleted successfully.');
                        } else {
                            throw new Exception('No transactions found to delete.');
                        }
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        setFlash('warning', 'Failed to delete transactions: ' . $e->getMessage());
                    }
                }
            } else {
                setFlash('warning', 'You do not have permission to delete transactions.');
            }
            break;
            
        case 'delete':
            if ($auth->hasRole('admin')) {
                $transactionId = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
                
                if (!$transactionId) {
                    setFlash('warning', 'Invalid transaction ID.');
                    break;
                }

                try {
                    $pdo = $auth->getPdo();
                    
                    // Start transaction
                    $pdo->beginTransaction();
                    
                    // Get transaction details first
                    $stmt = $pdo->prepare('SELECT type, amount, user_id FROM transactions WHERE id = ?');
                    $stmt->execute([$transactionId]);
                    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$transaction) {
                        throw new Exception('Transaction not found.');
                    }

                    // Calculate balance adjustment
                    $balanceAdjustment = $transaction['type'] === 'deposit' ? -$transaction['amount'] : $transaction['amount'];
                    
                    // Delete transaction first
                    $stmt = $pdo->prepare('DELETE FROM transactions WHERE id = ?');
                    $stmt->execute([$transactionId]);
                    
                    if ($stmt->rowCount() === 0) {
                        throw new Exception('Failed to delete transaction.');
                    }

                    // Update user balance
                    $stmt = $pdo->prepare('UPDATE user_balances SET balance = balance + ? WHERE user_id = ?');
                    $stmt->execute([$balanceAdjustment, $transaction['user_id']]);
                    
                    // Recalculate balances for all subsequent transactions
                    $stmt = $pdo->prepare('
                        SELECT id, type, amount 
                        FROM transactions 
                        WHERE user_id = ? AND created_at >= (
                            SELECT created_at FROM transactions WHERE id = ?
                        )
                        ORDER BY created_at ASC
                    ');
                    $stmt->execute([$transaction['user_id'], $transactionId]);
                    $subsequentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $runningBalance = getUserBalance($transaction['user_id']);
                    foreach ($subsequentTransactions as $trans) {
                        $runningBalance += $trans['type'] === 'deposit' ? $trans['amount'] : -$trans['amount'];
                        
                        // Update the balance for this transaction
                        $stmt = $pdo->prepare('UPDATE transactions SET balance = ? WHERE id = ?');
                        $stmt->execute([$runningBalance, $trans['id']]);
                    }
                    
                    $pdo->commit();
                    setFlash('success', 'Transaction deleted successfully.');
                } catch (Exception $e) {
                    if ($pdo && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Transaction deletion error: ' . $e->getMessage());
                    setFlash('warning', 'Failed to delete transaction: ' . $e->getMessage());
                }
            } else {
                setFlash('warning', 'You do not have permission to delete transactions.');
            }
            break;
    }
}

// Redirect back to transactions page
redirect('transactions.php');
?>