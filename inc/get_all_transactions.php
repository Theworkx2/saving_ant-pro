<?php
function getAllTransactions($params = []) {
    global $auth;
    $pdo = $auth->getPdo();
    
    $query = '
        SELECT 
            t.*,
            u.full_name,
            u.username
        FROM transactions t
        JOIN users u ON t.user_id = u.id
    ';
    
    $whereConditions = [];
    $queryParams = [];
    
    // Add filter conditions
    if (!empty($params['user_id'])) {
        $whereConditions[] = 't.user_id = ?';
        $queryParams[] = $params['user_id'];
    }
    
    if (!empty($params['type'])) {
        $whereConditions[] = 't.type = ?';
        $queryParams[] = $params['type'];
    }
    
    if (!empty($params['payment_method'])) {
        $whereConditions[] = 't.payment_method = ?';
        $queryParams[] = $params['payment_method'];
    }
    
    if (!empty($whereConditions)) {
        $query .= ' WHERE ' . implode(' AND ', $whereConditions);
    }
    
    // Add ordering
    $query .= ' ORDER BY t.created_at DESC';
    
    // Add limit and offset for pagination
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

function getAllTransactionsCount($filters = []) {
    global $auth;
    $pdo = $auth->getPdo();
    
    $query = 'SELECT COUNT(*) FROM transactions t JOIN users u ON t.user_id = u.id';
    $whereConditions = [];
    $queryParams = [];
    
    if (!empty($filters['user_id'])) {
        $whereConditions[] = 't.user_id = ?';
        $queryParams[] = $filters['user_id'];
    }
    
    if (!empty($filters['type'])) {
        $whereConditions[] = 't.type = ?';
        $queryParams[] = $filters['type'];
    }
    
    if (!empty($filters['payment_method'])) {
        $whereConditions[] = 't.payment_method = ?';
        $queryParams[] = $filters['payment_method'];
    }
    
    if (!empty($whereConditions)) {
        $query .= ' WHERE ' . implode(' AND ', $whereConditions);
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($queryParams);
    return (int)$stmt->fetchColumn();
}
?>