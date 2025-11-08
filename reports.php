<?php
require_once __DIR__ . '/inc/functions.php';

// Require login and admin/manager role
requireAuth();
if (!$auth->hasRole('admin') && !$auth->hasRole('manager')) {
    setFlash('warning', 'You do not have permission to access reports.');
    redirect('dashboard.php');
}

// Get current user data
$user = $auth->getUser();

// Get filter parameters
$startDate = filter_input(INPUT_GET, 'start_date') ?? '2020-01-01'; // Default to a past date to show all transactions
$endDate = filter_input(INPUT_GET, 'end_date') ?? date('Y-m-d'); // Default to today
$paymentMethod = filter_input(INPUT_GET, 'payment_method');

// Initialize reports data
try {
    $pdo = $auth->getPdo();
    
    // Total transactions summary
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_count,
            SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
            SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) as total_withdrawals
        FROM transactions 
        WHERE user_id = ? AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        " . ($paymentMethod ? "AND payment_method = ?" : "")
    );
    
    $params = [$user['id'], $startDate, $endDate];
    if ($paymentMethod) {
        $params[] = $paymentMethod;
    }
    
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Payment methods breakdown
    $stmt = $pdo->prepare("
        SELECT 
            payment_method,
            COUNT(*) as transaction_count,
            SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
            SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) as total_withdrawals
        FROM transactions 
        WHERE user_id = ? AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY payment_method
        ORDER BY transaction_count DESC
    ");
    
    $stmt->execute([$user['id'], $startDate, $endDate]);
    $paymentMethodStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Daily transactions
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END) as daily_deposits,
            SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) as daily_withdrawals,
            COUNT(*) as transaction_count
        FROM transactions 
        WHERE user_id = ? AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        " . ($paymentMethod ? "AND payment_method = ?" : "") . "
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    
    $stmt->execute($params);
    $dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    setFlash('warning', 'Error generating reports: ' . $e->getMessage());
    $summary = $paymentMethodStats = $dailyStats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Saving Ant</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/toast.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary: #0B5FFF;
            --dark: #06326B;
            --bg: #EAF3FF;
            --success: #0d8050;
            --warning: #bf8c0c;
            --danger: #db3737;
            --card-radius: 12px;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: #0b2240;
            line-height: 1.5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 92px 24px 40px;
        }
        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 24px;
        }
        .card {
            background: #fff;
            border-radius: var(--card-radius);
            padding: 24px;
            box-shadow: 0 4px 12px rgba(11,95,255,0.05);
            margin-bottom: 24px;
        }
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e6eefb;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(11,95,255,0.05);
        }
        .stat-label {
            font-size: 14px;
            color: #6b7a93;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
        }
        .stat-value.positive {
            color: var(--success);
        }
        .stat-value.negative {
            color: var(--danger);
        }
        .chart-container {
            margin-top: 24px;
            height: 300px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e6eefb;
        }
        .data-table th {
            font-weight: 500;
            color: #6b7a93;
            font-size: 13px;
        }
        .data-table td {
            font-size: 14px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: opacity 0.2s;
            background: var(--primary);
            color: #fff;
        }
        .btn:hover {
            opacity: 0.9;
        }
        @media (max-width: 768px) {
            .container { padding: 84px 16px 24px; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
    <!-- Include Chart.js for visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include_once __DIR__ . '/inc/sidebar.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Financial Reports</h1>

        <!-- Filters -->
        <div class="card">
            <form method="GET" class="filters">
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" 
                           value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" 
                           value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select name="payment_method" id="payment_method" class="form-control">
                        <option value="">All Methods</option>
                        <option value="momo" <?= $paymentMethod === 'momo' ? 'selected' : '' ?>>MTN Mobile Money</option>
                        <option value="airtel" <?= $paymentMethod === 'airtel' ? 'selected' : '' ?>>Airtel Money</option>
                        <option value="bank" <?= $paymentMethod === 'bank' ? 'selected' : '' ?>>Equity Bank</option>
                    </select>
                </div>
                <div class="form-group" style="display: flex; align-items: end;">
                    <button type="submit" class="btn">Apply Filters</button>
                </div>
            </form>
        </div>

        <!-- Summary Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Transactions</div>
                <div class="stat-value"><?= number_format($summary['total_count'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Deposits</div>
                <div class="stat-value positive">RWF <?= number_format($summary['total_deposits'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Withdrawals</div>
                <div class="stat-value negative">RWF <?= number_format($summary['total_withdrawals'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Net Flow</div>
                <div class="stat-value <?= (($summary['total_deposits'] ?? 0) - ($summary['total_withdrawals'] ?? 0)) >= 0 ? 'positive' : 'negative' ?>">
                    RWF <?= number_format(($summary['total_deposits'] ?? 0) - ($summary['total_withdrawals'] ?? 0)) ?>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="card">
            <h3>Transaction Trends</h3>
            <div class="chart-container">
                <canvas id="transactionChart"></canvas>
            </div>
        </div>

        <!-- Payment Methods Breakdown -->
        <div class="card">
            <h3>Payment Methods Analysis</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Payment Method</th>
                            <th>Transactions</th>
                            <th>Total Deposits</th>
                            <th>Total Withdrawals</th>
                            <th>Net Flow</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentMethodStats as $stat): ?>
                            <tr>
                                <td>
                                    <?php
                                    $paymentImages = [
                                        'momo' => 'momo.png',
                                        'airtel' => 'airtel.png',
                                        'bank' => 'equity.png'
                                    ];
                                    $imageName = $paymentImages[strtolower($stat['payment_method'])] ?? 'momo.png';
                                    ?>
                                    <img src="images/<?= $imageName ?>" 
                                         alt="<?= htmlspecialchars($stat['payment_method']) ?>"
                                         style="height: 20px; vertical-align: middle; margin-right: 8px;">
                                    <?= ucfirst(htmlspecialchars($stat['payment_method'])) ?>
                                </td>
                                <td><?= number_format($stat['transaction_count']) ?></td>
                                <td class="positive">RWF <?= number_format($stat['total_deposits']) ?></td>
                                <td class="negative">RWF <?= number_format($stat['total_withdrawals']) ?></td>
                                <td class="<?= ($stat['total_deposits'] - $stat['total_withdrawals']) >= 0 ? 'positive' : 'negative' ?>">
                                    RWF <?= number_format($stat['total_deposits'] - $stat['total_withdrawals']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Daily Transactions -->
        <div class="card">
            <h3>Daily Transaction History</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Transactions</th>
                            <th>Deposits</th>
                            <th>Withdrawals</th>
                            <th>Net Flow</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dailyStats as $day): ?>
                            <tr>
                                <td><?= date('Y-m-d', strtotime($day['date'])) ?></td>
                                <td><?= number_format($day['transaction_count']) ?></td>
                                <td class="positive">RWF <?= number_format($day['daily_deposits']) ?></td>
                                <td class="negative">RWF <?= number_format($day['daily_withdrawals']) ?></td>
                                <td class="<?= ($day['daily_deposits'] - $day['daily_withdrawals']) >= 0 ? 'positive' : 'negative' ?>">
                                    RWF <?= number_format($day['daily_deposits'] - $day['daily_withdrawals']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Prepare data for the chart
        const dates = <?= json_encode(array_column(array_reverse($dailyStats), 'date')) ?>;
        const deposits = <?= json_encode(array_column(array_reverse($dailyStats), 'daily_deposits')) ?>;
        const withdrawals = <?= json_encode(array_column(array_reverse($dailyStats), 'daily_withdrawals')) ?>;

        // Create the chart
        const ctx = document.getElementById('transactionChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Deposits',
                        data: deposits,
                        borderColor: '#0d8050',
                        backgroundColor: 'rgba(13,128,80,0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Withdrawals',
                        data: withdrawals,
                        borderColor: '#db3737',
                        backgroundColor: 'rgba(219,55,55,0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'RWF ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>