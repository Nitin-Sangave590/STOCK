<?php
ob_start();
include 'db.php';
include 'header.php';

// Get action, search query, and transaction type
$action = $_GET['action'] ?? '';
$search = $_GET['search'] ?? '';
$transaction_type = $_GET['type'] ?? 'all';

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Handle form submission for adding transaction
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $type    = $conn->real_escape_string(trim($_POST['type']));
    $account_id = $conn->real_escape_string(trim($_POST['account_id']));
    $date    = $conn->real_escape_string(trim($_POST['date']));
    $amount  = $conn->real_escape_string(trim($_POST['amount']));
    $description = $conn->real_escape_string(trim($_POST['description']));

    $table = ($type === 'purchase') ? 'purchases' : 'sales';
    $sql = "INSERT INTO $table (account_id, date, amount, description) 
            VALUES ('$account_id', '$date', '$amount', '$description')";

    if ($conn->query($sql)) {
        header("Location: combine_register.php?type=$transaction_type");
        exit;
    } else {
        echo "<div class='alert alert-danger container mt-3'>Error: " . $conn->error . "</div>";
    }
}

// Build query for transactions
$sql = "SELECT t.id, t.type, a.name as account_name, t.date, t.amount, t.description 
        FROM (
            SELECT id, 'purchase' as type, account_id, date, amount, description FROM purchases
            UNION ALL
            SELECT id, 'sale' as type, account_id, date, amount, description FROM sales
        ) t JOIN accounts a ON t.account_id = a.id WHERE 1=1";

if ($transaction_type !== 'all') {
    $sql .= " AND t.type = '" . $conn->real_escape_string($transaction_type) . "'";
}
if ($search) {
    $search = $conn->real_escape_string($search);
    $sql .= " AND (a.name LIKE '%$search%' OR t.description LIKE '%$search%' OR t.date LIKE '%$search%')";
}

// Get total records for pagination
$total_sql = $sql;
$total_result = $conn->query($total_sql);
$total_records = $total_result->num_rows;
$total_pages = ceil($total_records / $records_per_page);

// Add limit for pagination
$sql .= " ORDER BY t.date DESC LIMIT $offset, $records_per_page";
$result = $conn->query($sql);
?>

<style>
    body {
        background-color: #f5f6fa;
        font-family: 'Inter', sans-serif;
        color: #2d3748;
    }
    .container {
        max-width: 1200px;
        margin: 2rem auto;
    }
    .card-custom {
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        padding: 2rem;
        transition: transform 0.3s ease;
    }
    .card-custom:hover {
        transform: translateY(-4px);
    }
    .page-title {
        font-size: 2rem;
        font-weight: 700;
        color: #2b6cb0;
        text-align: center;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .nav-tabs {
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 1.5rem;
    }
    .nav-tabs .nav-link {
        color: #4a5568;
        font-weight: 500;
        padding: 0.75rem 1.5rem;
        border-radius: 8px 8px 0 0;
        transition: all 0.3s ease;
    }
    .nav-tabs .nav-link:hover {
        background-color: #edf2f7;
    }
    .nav-tabs .nav-link.active {
        background-color: #2b6cb0;
        color: white;
        border: none;
    }
    .search-bar {
        display: flex;
        gap: 0.5rem;
        max-width: 500px;
    }
    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 0.75rem;
        transition: border-color 0.3s ease;
    }
    .form-control:focus, .form-select:focus {
        border-color: #2b6cb0;
        box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.1);
    }
    .btn {
        border-radius: 8px;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    .btn-primary {
        background-color: #2b6cb0;
        border-color: #2b6cb0;
    }
    .btn-primary:hover {
        background-color: #2c5282;
        border-color: #2c5282;
    }
    .btn-secondary {
        background-color: #718096;
        border-color: #718096;
    }
    .btn-secondary:hover {
        background-color: #5a6b85;
        border-color: #5a6b85;
    }
    .btn-success {
        background-color: #38a169;
        border-color: #38a169;
    }
    .btn-success:hover {
        background-color: #2f855a;
        border-color: #2f855a;
    }
    .table {
        border-radius: 8px;
        overflow: hidden;
    }
    .table thead {
        background-color: #2b6cb0;
        color: white;
    }
    .table-hover tbody tr {
        transition: all 0.3s ease;
    }
    .table-hover tbody tr:hover {
        background-color: #edf2f7;
        transform: translateX(4px);
    }
    .pagination .page-link {
        border-radius: 6px;
        margin: 0 4px;
        color: #2b6cb0;
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    .pagination .page-link:hover {
        background-color: #2b6cb0;
        color: white;
        border-color: #2b6cb0;
    }
    .pagination .page-item.active .page-link {
        background-color: #2b6cb0;
        border-color: #2b6cb0;
        color: white;
    }
    .pagination .page-item.disabled .page-link {
        color: #a0aec0;
        cursor: not-allowed;
    }
    #closeaccountmas {
        position: absolute;
        right: 1rem;
        top: 1rem;
    }
</style>

<div class="container">
    <h2 class="page-title">📊 Combined Register</h2>

    <?php if ($action === 'add'): ?>
        <div class="card-custom">
            <h4 class="mb-4 text-primary">➕ Add New Transaction</h4>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label"><strong>Type</strong></label>
                    <select name="type" class="form-select" required>
                        <option value="">-- Select Transaction Type --</option>
                        <option value="purchase">Purchase</option>
                        <option value="sale">Sale</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Account</strong></label>
                    <select name="account_id" class="form-select" required>
                        <option value="">-- Select Account --</option>
                        <?php
                        $account_sql = "SELECT id, name, type FROM accounts ORDER BY name";
                        $account_result = $conn->query($account_sql);
                        while ($acc = $account_result->fetch_assoc()) {
                            echo "<option value='{$acc['id']}'>{$acc['name']} ({$acc['type']})</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Date</strong></label>
                    <input type="date" name="date" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Amount</strong></label>
                    <input type="number" step="0.01" name="amount" class="form-control" required placeholder="Enter amount">
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Description</strong></label>
                    <textarea name="description" class="form-control" placeholder="Enter description"></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">💾 Save Transaction</button>
                    <a href="combine_register.php?type=<?php echo $transaction_type; ?>" class="btn btn-secondary">❌ Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- Tabs for All, Purchases, Sales -->
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link <?php echo $transaction_type === 'all' ? 'active' : ''; ?>" href="combine_register.php?type=all">All Transactions</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $transaction_type === 'purchase' ? 'active' : ''; ?>" href="combine_register.php?type=purchase">Purchases</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $transaction_type === 'sale' ? 'active' : ''; ?>" href="combine_register.php?type=sale">Sales</a>
            </li>
        </ul>

        <!-- Search Bar and Add Button -->
        <div class="d-flex justify-content-between mb-4">
            <form class="search-bar" method="GET">
                <div class="d-flex gap-2">
                    <select name="type" class="form-select" style="width: 150px;">
                        <option value="all" <?php echo $transaction_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="purchase" <?php echo $transaction_type === 'purchase' ? 'selected' : ''; ?>>Purchase</option>
                        <option value="sale" <?php echo $transaction_type === 'sale' ? 'selected' : ''; ?>>Sale</option>
                    </select>
                    <input type="text" name="search" class="form-control" placeholder="Search by account, description, or date" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">🔍 Search</button>
                </div>
            </form>
            <a href="combine_register.php?action=add&type=<?php echo $transaction_type; ?>" class="btn btn-success">➕ Add New Transaction</a>
        </div>

        <!-- Transactions Table -->
        <div class="card-custom position-relative">
            <h4 class="text-dark mb-4">📋 <?php echo ucfirst($transaction_type === 'all' ? 'All Transactions' : $transaction_type . 's'); ?>
                <a href="index.php" id="closeaccountmas" class="btn btn-danger btn-sm">❌</a>
            </h4>
            <table class="table table-striped table-hover table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Account</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>
                                <td>{$row['id']}</td>
                                <td>" . ucfirst(htmlspecialchars($row['type'])) . "</td>
                                <td>" . htmlspecialchars($row['account_name']) . "</td>
                                <td>" . htmlspecialchars($row['date']) . "</td>
                                <td>" . number_format($row['amount'], 2) . "</td>
                                <td>" . nl2br(htmlspecialchars($row['description'])) . "</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center text-muted'>No transactions found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="combine_register.php?type=<?php echo $transaction_type; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="combine_register.php?type=<?php echo $transaction_type; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="combine_register.php?type=<?php echo $transaction_type; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>