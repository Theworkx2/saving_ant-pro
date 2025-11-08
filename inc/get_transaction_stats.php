<?php
require_once 'functions.php';

// Require admin role
requireRole('admin');

// Get the number of days from the query parameter
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;

// Validate days parameter
if (!in_array($days, [7, 30, 90])) {
    $days = 30;
}

try {
    $pdo = $auth->getPdo();
    
    // Get transaction data for the specified period
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            type,
            COUNT(*) as count,
            SUM(amount) as total
        FROM transactions 
        WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
        GROUP BY DATE(created_at), type
        ORDER BY date
    ");
    
    $stmt->execute([$days]);
    $results = $stmt->fetchAll();
    
    // Initialize arrays for chart data
    $dates = [];
    $deposits = [];
    $withdrawals = [];
    
    // Create a date range
    $startDate = new DateTime("-{$days} days");
    $endDate = new DateTime();
    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($startDate, $interval, $endDate);
    
    // Initialize data arrays with zeros
    foreach ($dateRange as $date) {
        $dateStr = $date->format('Y-m-d');
        $dates[] = $date->format('M j'); // Format for display
        $depositData[$dateStr] = 0;
        $withdrawalData[$dateStr] = 0;
    }
    
    // Fill in actual data
    foreach ($results as $row) {
        $dateStr = $row['date'];
        if ($row['type'] === 'deposit') {
            $depositData[$dateStr] = $row['count'];
        } else {
            $withdrawalData[$dateStr] = $row['count'];
        }
    }
    
    // Convert to arrays for the chart
    $deposits = array_values($depositData);
    $withdrawals = array_values($withdrawalData);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'labels' => $dates,
        'deposits' => $deposits,
        'withdrawals' => $withdrawals
    ]);
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
}