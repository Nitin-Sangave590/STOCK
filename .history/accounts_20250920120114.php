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
    background-color: #f8f9fa;
}
.card-custom {
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
}
.table-hover tbody tr:hover {
    background-color: #f1f1f1;
    transform: scale(1.01);
    transition: 0.2s;
}
.page-title {
    font-size: 1.8rem;
    font-weight: bold;
    margin-bottom: 20px;
    color: #0d6efd;
    text-align: center;
}
.nav-link.active {
    background-color: #0d6efd !important;
    color: white !important;
    border-radius: 10px 10px 0 0;
}
.top-controls {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: space-between;
    align-items: center;
}
.search-bar {
    display: flex;
    gap: 5px;
    max-width: 350px;
    flex: 1;
}
</style>

<div class="container mt-4">
    <h2 class="page-title">📒 Account Master</h2>

    <?php if ($action === 'add'): ?>
        <div class="card card-custom p-4 bg-white">
            <h4 class="mb-3 text-primary">➕ Add New Account</h4>
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
                <button type="submit" class="btn btn-primary">💾 Save Account</button>
                <a href="accounts.php?type=<?php echo $account_type; ?>" class="btn btn-secondary">❌ Cancel</a>
            </form>
        </div>
    <?php else: ?>

        <!-- Combined Tabs + Search + Add Button -->
       

        <!-- Accounts Table -->
        <div class="card card-custom p-3">
            <h4 class="text-dark mb-3">
                📋 <?php echo ucfirst($account_type === 'all' ? 'All Accounts' : $account_type . 's'); ?>
                <a href="index.php" class="btn btn-danger float-end">❌</a>
            </h4>

            <table class="table table-striped table-hover table-bordered align-middle">
                <thead class="table-dark">
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
                    <ul class="pagination justify-content-center mt-3">
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
