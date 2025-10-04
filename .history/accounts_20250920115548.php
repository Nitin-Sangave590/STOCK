<?php
ob_start();
include 'db.php';
include 'header.php';

// Get action, search query, and account type
$action = $_GET['action'] ?? '';
$search = $_GET['search'] ?? '';
$account_type = $_GET['type'] ?? 'all';

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Handle form submission
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = $conn->real_escape_string(trim($_POST['name']));
    $type    = $conn->real_escape_string(trim($_POST['type']));
    $address = $conn->real_escape_string(trim($_POST['address']));
    $phone   = $conn->real_escape_string(trim($_POST['phone']));
    $email   = $conn->real_escape_string(trim($_POST['email']));

    $sql = "INSERT INTO accounts (name, type, address, phone, email) 
            VALUES ('$name', '$type', '$address', '$phone', '$email')";

    if ($conn->query($sql)) {
        header("Location: accounts.php?type=$type");
        exit;
    } else {
        echo "<div class='alert alert-danger container mt-3'>Error: " . $conn->error . "</div>";
    }
}

// Build query for accounts
$sql = "SELECT * FROM accounts WHERE 1=1";
if ($account_type !== 'all') {
    $sql .= " AND type = '" . $conn->real_escape_string($account_type) . "'";
}
if ($search) {
    $search = $conn->real_escape_string($search);
    $sql .= " AND (name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
}

// Get total records for pagination
$total_sql = $sql;
$total_result = $conn->query($total_sql);
$total_records = $total_result->num_rows;
$total_pages = ceil($total_records / $records_per_page);

// Add limit for pagination
$sql .= " ORDER BY id DESC LIMIT $offset, $records_per_page";
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
    <h2 class="page-title">📒 Account Master</h2>

    <?php if ($action === 'add'): ?>
        <div class="card-custom">
            <h4 class="mb-4 text-primary">➕ Add New Account</h4>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label"><strong>Name</strong></label>
                    <input type="text" name="name" class="form-control" required placeholder="Enter account name">
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Type</strong></label>
                    <select name="type" class="form-select" required>
                        <option value="">-- Select Account Type --</option>
                        <option value="customer">Customer</option>
                        <option value="supplier">Supplier</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Address</strong></label>
                    <textarea name="address" class="form-control" placeholder="Enter full address"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><strong>Phone</strong></label>
                        <input type="text" name="phone" class="form-control" placeholder="Enter phone number">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><strong>Email</strong></label>
                        <input type="email" name="email" class="form-control" placeholder="Enter email address">
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">💾 Save Account</button>
                    <a href="accounts.php?type=<?php echo $account_type; ?>" class="btn btn-secondary">❌ Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- Tabs for All, Customers, Suppliers -->
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link <?php echo $account_type === 'all' ? 'active' : ''; ?>" href="accounts.php?type=all">All Accounts</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $account_type === 'customer' ? 'active' : ''; ?>" href="accounts.php?type=customer">Customers</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $account_type === 'supplier' ? 'active' : ''; ?>" href="accounts.php?type=supplier">Suppliers</a>
            </li>
        </ul>

        <!-- Search Bar and Add Button -->
        <div class="d-flex justify-content-between mb-4">
            <form class="search-bar" method="GET">
                <div class="d-flex gap-2">
                    <select name="type" class="form-select" style="width: 150px;">
                        <option value="all" <?php echo $account_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="customer" <?php echo $account_type === 'customer' ? 'selected' : ''; ?>>Customer</option>
                        <option value="supplier" <?php echo $account_type === 'supplier' ? 'selected' : ''; ?>>Supplier</option>
                    </select>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, or phone" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">🔍 Search</button>
                </div>
            </form>
            <a href="accounts.php?action=add&type=<?php echo $account_type; ?>" class="btn btn-success">➕ Add New Account</a>
        </div>

        <!-- Accounts Table -->
        <div class="card-custom position-relative">
            <h4 class="text-dark mb-4">📋 <?php echo ucfirst($account_type === 'all' ? 'All Accounts' : $account_type . 's'); ?>
                <a href="index.php" id="closeaccountmas" class="btn btn-danger btn-sm">❌</a>
            </h4>
            <table class="table table-striped table-hover table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Address</th>
                        <th>Phone</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>
                                <td>{$row['id']}</td>
                                <td>" . htmlspecialchars($row['name']) . "</td>
                                <td>" . ucfirst(htmlspecialchars($row['type'])) . "</td>
                                <td>" . nl2br(htmlspecialchars($row['address'])) . "</td>
                                <td>" . htmlspecialchars($row['phone']) . "</td>
                                <td>" . htmlspecialchars($row['email']) . "</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center text-muted'>No accounts found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="accounts.php?type=<?php echo $account_type; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="accounts.php?type=<?php echo $account_type; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="accounts.php?type=<?php echo $account_type; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>