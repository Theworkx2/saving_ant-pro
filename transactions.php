<?php
require_once __DIR__ . '/inc/functions.php';

// Require login for transactions page
requireAuth();

// Get current user data
$user = $auth->getUser();

// Pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get transactions for the current user
$transactions = getTransactions([
    'user_id' => $user['id'],
    'limit' => $perPage,
    'offset' => $offset
]);

// Get total number of transactions for pagination
$totalTransactions = getTransactionCount($user['id']);
$totalPages = ceil($totalTransactions / $perPage);

// Handle new transaction submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_transaction'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        setFlash('warning', 'Invalid request. Please try again.');
        redirect('transactions.php');
    }

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
        redirect('transactions.php');
    } else {
        setFlash('warning', 'Please provide valid transaction details.');
    }
}

// Get current balance
$currentBalance = getUserBalance($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Saving Ant</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/toast.css">
    <link rel="stylesheet" href="css/delete-dialog.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --dialog-bg: rgba(0, 0, 0, 0.5);
            --dialog-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --warning-color: #bf8c0c;
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
        .navbar {
            background: #fff;
            box-shadow: 0 1px 3px rgba(11,95,255,0.1);
            padding: 12px 24px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }
        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .logo {
            font-weight: 600;
            font-size: 20px;
            color: var(--dark);
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        .nav-links a {
            color: #18314d;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        .nav-links a:hover {
            color: var(--primary);
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
        .balance-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .balance-amount {
            font-size: 32px;
            font-weight: 600;
            color: var(--dark);
        }
        .balance-label {
            font-size: 14px;
            color: #6b7a93;
        }
        .transaction-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
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
        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .btn-primary {
            background: var(--primary);
            color: #fff;
        }
        .transactions-table {
            width: 100%;
            border-collapse: collapse;
        }
        .transactions-table th,
        .transactions-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e6eefb;
        }
        .transactions-table th {
            font-weight: 500;
            color: #6b7a93;
            font-size: 13px;
        }
        .transactions-table td {
            font-size: 14px;
        }
        .transaction-amount {
            font-weight: 500;
        }
        .amount-deposit {
            color: var(--success);
        }
        .amount-withdrawal {
            color: var(--danger);
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
        }
        .pagination a {
            padding: 8px 12px;
            border: 1px solid #e6eefb;
            border-radius: 6px;
            color: var(--dark);
            text-decoration: none;
            font-size: 14px;
        }
        .pagination a:hover {
            background: #fbfcff;
        }
        .pagination .active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        .alert-success { background: rgba(13,128,80,0.1); color: var(--success); }
        .alert-warning { background: rgba(191,140,12,0.1); color: var(--warning); }

        /* CRUD Styles */
        .btn-action {
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 4px;
            color: white;
        }
        .btn-action.edit {
            background: var(--primary);
        }
        .btn-action.delete {
            background: var(--danger);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            position: relative;
            background: white;
            margin: 10% auto;
            padding: 20px;
            width: 90%;
            max-width: 500px;
            border-radius: var(--card-radius);
        }
        .modal-close {
            position: absolute;
            right: 20px;
            top: 20px;
            cursor: pointer;
            font-size: 20px;
            color: #666;
        }

        @media (max-width: 768px) {
            .transaction-form {
                grid-template-columns: 1fr;
            }
            .navbar { padding: 12px 16px; }
            .container { padding: 84px 16px 24px; }
            .balance-amount { font-size: 24px; }
        }
        .payment-icon {
            display: flex;
            align-items: center;
        }
        .payment-icon img {
            transition: all 0.3s ease;
        }
    </style>
    <script>
        // Payment method configurations
        const paymentMethods = {
            'momo': {
                file: 'momo.png',
                name: 'MTN Mobile Money'
            },
            'airtel': {
                file: 'airtelmoney.jpg',
                name: 'Airtel Money'
            },
            'bank': {
                file: 'equity.png',
                name: 'Equity Bank'
            }
        };

        function updatePaymentIcon(method) {
            const iconElement = document.getElementById('paymentMethodIcon');
            const paymentInfo = paymentMethods[method] || paymentMethods['momo'];
            iconElement.src = `images/${paymentInfo.file}`;
            iconElement.alt = paymentInfo.name;
            console.log('Updated payment method:', method, 'Using file:', paymentInfo.file);
        }

        // Set initial payment icon when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const select = document.getElementById('payment_method');
            if (select) {
                updatePaymentIcon(select.value);
            }
        });
    </script>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <a href="dashboard.php" class="brand">
                <span class="logo">Saving Ant</span>
            </a>
            
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <?php if ($auth->hasRole('admin')): ?>
                    <a href="users.php">User Management</a>
                <?php endif; ?>
                <?php if ($auth->hasRole('admin') || $auth->hasRole('manager')): ?>
                    <a href="reports.php">Reports</a>
                <?php endif; ?>
                <a href="transactions.php">Transactions</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <div id="toastContainer" class="toast-container"></div>
        <?php if ($flash = getFlash()): ?>
            <script>
                window.addEventListener('DOMContentLoaded', () => {
                    showToast('<?= $flash['type'] ?>', '<?= htmlspecialchars(addslashes($flash['message'])) ?>');
                });
            </script>
        <?php endif; ?>

        <h1 class="page-title">Transactions</h1>

        <div class="card balance-card">
            <div>
                <div class="balance-label">Current Balance</div>
                <div class="balance-amount">RWF <?= number_format($currentBalance, 0) ?></div>
            </div>
            <button class="btn btn-primary" onclick="document.getElementById('newTransactionForm').style.display='block'">
                New Transaction
            </button>
        </div>

        <div class="card" id="newTransactionForm" style="display: none;">
            <h3 style="margin-bottom: 16px;">New Transaction</h3>
            <form method="POST" class="transaction-form">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                
                <div class="form-group">
                    <label for="type">Transaction Type</label>
                    <select name="type" id="type" class="form-control" required>
                        <option value="deposit">Deposit</option>
                        <option value="withdrawal">Withdrawal</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="amount">Amount (RWF)</label>
                    <input type="number" name="amount" id="amount" class="form-control" step="1" min="100" required>
                </div>

                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select name="payment_method" id="payment_method" class="form-control" required onchange="updatePaymentIcon(this.value)">
                        <option value="momo">MTN Mobile Money</option>
                        <option value="airtel">Airtel Money</option>
                        <option value="bank">Equity Bank</option>
                    </select>
                    <div class="payment-icon">
                        <img id="paymentMethodIcon" src="images/momo.png" alt="MTN Mobile Money" style="height: 30px; margin-top: 8px;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <input type="text" name="description" id="description" class="form-control" required>
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <button type="submit" name="submit_transaction" class="btn btn-primary">Submit Transaction</button>
                </div>
            </form>
        </div>

        <div class="card">
            <?php if ($auth->hasRole('admin')): ?>
            <div class="bulk-actions">
                <button id="deleteSelectedBtn" class="btn btn-danger" onclick="deleteSelected()" style="display: none;">
                    Delete Selected
                </button>
            </div>
            <?php endif; ?>

            <table class="transactions-table">
                <thead>
                    <tr>
                        <?php if ($auth->hasRole('admin')): ?>
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAll" onchange="handleSelectAll(this)">
                        </th>
                        <?php endif; ?>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Balance</th>
                        <th>Description</th>
                        <th>Payment Method</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr data-id="<?= $transaction['id'] ?>">
                            <?php if ($auth->hasRole('admin')): ?>
                            <td>
                                <input type="checkbox" class="transaction-checkbox" value="<?= $transaction['id'] ?>" onchange="handleCheckboxChange(this)">
                            </td>
                            <?php endif; ?>
                            <td><?= date('Y-m-d H:i', strtotime($transaction['created_at'])) ?></td>
                            <td><?= ucfirst(htmlspecialchars($transaction['type'])) ?></td>
                            <td class="transaction-amount amount-<?= $transaction['type'] ?>">
                                <?= $transaction['type'] === 'deposit' ? '+' : '-' ?>RWF <?= number_format($transaction['amount'], 0) ?>
                            </td>
                            <td>RWF <?= number_format($transaction['balance'], 0) ?></td>
                            <td><?= htmlspecialchars($transaction['description']) ?></td>
                            <td>
                                <?php
                                // Get the payment method from the transaction
                                $method = isset($transaction['payment_method']) ? strtolower($transaction['payment_method']) : 'momo';
                                
                                // Define payment method mappings
                                $paymentMethods = [
                                    'momo' => ['file' => 'momo.png', 'name' => 'MTN Mobile Money'],
                                    'airtel' => ['file' => 'airtelmoney.jpg', 'name' => 'Airtel Money'],
                                    'airtelmoney' => ['file' => 'airtelmoney.jpg', 'name' => 'Airtel Money'],
                                    'bank' => ['file' => 'equity.png', 'name' => 'Equity Bank'],
                                    'equity' => ['file' => 'equity.png', 'name' => 'Equity Bank']
                                ];
                                
                                // Get payment info with fallback to momo
                                $paymentInfo = $paymentMethods[$method] ?? $paymentMethods['momo'];
                                ?>
                                <img src="images/<?= htmlspecialchars($paymentInfo['file']) ?>" 
                                     alt="<?= htmlspecialchars($paymentInfo['name']) ?>" 
                                     style="height: 20px; vertical-align: middle;">
                                <?= htmlspecialchars($paymentInfo['name']) ?>
                            </td>
                            <?php if ($auth->hasRole('admin')): ?>
                            <td class="actions">
                                <button onclick="editTransaction(<?= $transaction['id'] ?>, <?= $transaction['amount'] ?>, '<?= htmlspecialchars($transaction['description']) ?>', '<?= htmlspecialchars($method) ?>', '<?= $transaction['type'] ?>')" class="btn-action edit">
                                    Edit
                                </button>
                                <form method="POST" action="manage_transactions.php" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="transaction_id" value="<?= $transaction['id'] ?>">
                                    <button type="submit" class="btn-action delete" onclick="return confirm('Are you sure you want to delete this transaction?')">
                                        Delete
                                    </button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 24px;">
                                No transactions found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>" <?= $i === $page ? 'class="active"' : '' ?>>
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Edit Transaction Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
            <h3 style="margin-bottom: 16px;">Edit Transaction</h3>
            <form method="POST" action="manage_transactions.php">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="transaction_id" id="edit_transaction_id">
                <input type="hidden" name="original_amount" id="edit_original_amount">
                <input type="hidden" name="transaction_type" id="edit_transaction_type">
                
                <div class="form-group">
                    <label for="edit_amount">Amount (RWF)</label>
                    <input type="number" name="amount" id="edit_amount" class="form-control" step="1" min="100" required>
                </div>

                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <input type="text" name="description" id="edit_description" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="edit_payment_method">Payment Method</label>
                    <select name="payment_method" id="edit_payment_method" class="form-control" required>
                        <option value="momo">MTN Mobile Money</option>
                        <option value="airtel">Airtel Money</option>
                        <option value="bank">Equity Bank</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Update Transaction</button>
            </form>
        </div>
    </div>

    <script>
        // Toast Notification System
        function showToast(type, message) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icon = type === 'success' ? 'check_circle' : 
                        type === 'warning' ? 'warning' : 
                        'error';
            
            toast.innerHTML = `
                <span class="material-icons toast-icon">${icon}</span>
                <div class="toast-message">${message}</div>
                <span class="material-icons toast-close" onclick="this.parentElement.remove()">close</span>
            `;
            
            container.appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-out forwards';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Handle select all checkbox
        function handleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.transaction-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
                const row = cb.closest('tr');
                if (row) {
                    row.classList.toggle('selected', checkbox.checked);
                }
            });
            updateDeleteButton();
        }

        // Handle individual checkbox changes
        function handleCheckboxChange(checkbox) {
            const row = checkbox.closest('tr');
            if (row) {
                row.classList.toggle('selected', checkbox.checked);
            }
            
            // Update select all checkbox state
            const allCheckboxes = document.querySelectorAll('.transaction-checkbox');
            const checkedCount = document.querySelectorAll('.transaction-checkbox:checked').length;
            const selectAllCheckbox = document.getElementById('selectAll');
            
            selectAllCheckbox.checked = checkedCount === allCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < allCheckboxes.length;
            
            updateDeleteButton();
        }

        // Update delete button visibility and text
        function updateDeleteButton() {
            const selectedCount = document.querySelectorAll('.transaction-checkbox:checked').length;
            const deleteBtn = document.getElementById('deleteSelectedBtn');
            
            if (selectedCount > 0) {
                deleteBtn.style.display = 'inline-block';
                deleteBtn.textContent = `Delete Selected (${selectedCount})`;
            } else {
                deleteBtn.style.display = 'none';
            }
        }

        // Delete selected transactions
        function confirmDelete(selectedIds) {
            // Create the form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'manage_transactions.php';
            
            // Add CSRF token
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?= generateCsrfToken() ?>';
            form.appendChild(csrfInput);
            
            // Add action type
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'bulk_delete';
            form.appendChild(actionInput);
            
            // Add selected IDs
            const idsInput = document.createElement('input');
            idsInput.type = 'hidden';
            idsInput.name = 'transaction_ids';
            idsInput.value = JSON.stringify(selectedIds);
            form.appendChild(idsInput);
            
            // Fade out selected rows
            selectedIds.forEach(id => {
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    row.style.backgroundColor = '#ffe6e6';
                    row.style.transition = 'background-color 0.3s';
                }
            });
            
            // Remove any existing dialogs
            document.querySelectorAll('.confirm-dialog').forEach(dialog => dialog.remove());
            
            // Submit form after brief delay to show feedback
            setTimeout(() => {
                document.body.appendChild(form);
                form.submit();
            }, 300);
        }

        function deleteSelected() {
            const selectedIds = Array.from(document.querySelectorAll('.transaction-checkbox:checked'))
                                   .map(checkbox => checkbox.value);
            
            if (selectedIds.length === 0) return;

            // Remove any existing dialogs first
            document.querySelectorAll('.confirm-dialog').forEach(dialog => dialog.remove());

            showConfirmDialog(
                'Delete Transactions',
                `Are you sure you want to delete ${selectedIds.length} transaction(s)?`,
                'This action cannot be undone.',
                () => confirmDelete(selectedIds)
            );
        }

        let activeDialog = null;

        function showConfirmDialog(title, message, warning, onConfirm) {
            // Remove any existing dialogs
            if (activeDialog) {
                activeDialog.remove();
            }

            const dialog = document.createElement('div');
            dialog.className = 'delete-dialog';
            dialog.innerHTML = `
                <div class="delete-dialog-content">
                    <h3>${title}</h3>
                    <p>${message}</p>
                    <div class="delete-warning">
                        <span class="material-icons">warning</span>
                        <span>${warning}</span>
                    </div>
                    <div class="delete-dialog-buttons">
                        <button class="btn" onclick="closeDialog()">Cancel</button>
                        <button class="btn btn-danger" onclick="handleConfirm()">Delete</button>
                    </div>
                </div>
            `;
            
            // Store reference to active dialog
            activeDialog = dialog;
            document.body.appendChild(dialog);

            // Define handlers in the current scope to access onConfirm
            window.closeDialog = function() {
                if (activeDialog) {
                    activeDialog.remove();
                    activeDialog = null;
                }
            };

            window.handleConfirm = function() {
                closeDialog();
                onConfirm();
            };
        }

        // Modal Functions
        function editTransaction(id, amount, description, paymentMethod, type) {
            document.getElementById('edit_transaction_id').value = id;
            document.getElementById('edit_amount').value = amount;
            document.getElementById('edit_original_amount').value = amount;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_payment_method').value = paymentMethod;
            document.getElementById('edit_transaction_type').value = type;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Initialize tooltips and event listeners
        document.addEventListener('DOMContentLoaded', function() {
            updateBulkActions();
        });
    </script>
</body>
</html>