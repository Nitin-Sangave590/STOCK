<?php
ob_start();
include 'db.php';
include 'header.php';

$search = $_GET['search'] ?? '';
$account_type = $_GET['type'] ?? 'all';
$show_success_modal = false;
$success_message = "";

// ---------- ADD ACCOUNT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_account'])) {
    $name    = $conn->real_escape_string(trim($_POST['name']));
    $type    = $conn->real_escape_string(trim($_POST['type']));
    $address = $conn->real_escape_string(trim($_POST['address']));
    $phone   = $conn->real_escape_string(trim($_POST['phone']));
    $email   = $conn->real_escape_string(trim($_POST['email']));

    $sql = "INSERT INTO accounts (name, type, address, phone, email) 
            VALUES ('$name', '$type', '$address', '$phone', '$email')";
    if ($conn->query($sql)) {
        $show_success_modal = true;
        $success_message = "Account created successfully!";
    }
}

// ---------- EDIT ACCOUNT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_account'])) {
    $id      = (int)$_POST['id'];
    $name    = $conn->real_escape_string(trim($_POST['name']));
    $type    = $conn->real_escape_string(trim($_POST['type']));
    $address = $conn->real_escape_string(trim($_POST['address']));
    $phone   = $conn->real_escape_string(trim($_POST['phone']));
    $email   = $conn->real_escape_string(trim($_POST['email']));

    $sql = "UPDATE accounts SET 
                name='$name', type='$type', address='$address', 
                phone='$phone', email='$email' 
            WHERE id=$id";
    if ($conn->query($sql)) {
        $show_success_modal = true;
        $success_message = "Account updated successfully!";
    }
}

// ---------- DELETE ACCOUNT ----------
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $conn->query("DELETE FROM accounts WHERE id=$id");
    $show_success_modal = true;
    $success_message = "Account deleted successfully!";
}

// ---------- PAGINATION ----------
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// ---------- FETCH ACCOUNTS ----------
$sql = "SELECT * FROM accounts WHERE 1=1";
if ($account_type !== 'all') {
    $sql .= " AND type = '" . $conn->real_escape_string($account_type) . "'";
}
if ($search) {
    $search = $conn->real_escape_string($search);
    $sql .= " AND (name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
}
$total_sql = $sql;
$total_result = $conn->query($total_sql);
$total_records = $total_result->num_rows;
$total_pages = ceil($total_records / $records_per_page);

$sql .= " ORDER BY id DESC LIMIT $offset, $records_per_page";
$result = $conn->query($sql);
?>

<style>
body { background-color: #f8f9fa; }
.card-custom { border-radius: 12px; box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08); }
.table-hover tbody tr:hover { background-color: #f1f1f1; transform: scale(1.01); transition: 0.2s; }
.page-title { font-size: 1.8rem; font-weight: bold; margin-bottom: 20px; color: #0d6efd; text-align: center; }
.nav-link.active { background-color: #0d6efd !important; color: white !important; border-radius: 10px 10px 0 0; }
.top-controls { display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; align-items: center; }
.search-bar { display: flex; gap: 5px; max-width: 350px; flex: 1; }
</style>

<div class="container mt-4">
    <h2 class="page-title">📒 Account Master</h2>

    <div class="card card-custom p-3">
        <div class="top-controls mb-3">
            <ul class="nav nav-tabs flex-grow-1">
                <li class="nav-item">
                    <a class="nav-link <?php echo $account_type === 'all' ? 'active' : ''; ?>" href="accounts.php?type=all">All</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $account_type === 'customer' ? 'active' : ''; ?>" href="accounts.php?type=customer">Customers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $account_type === 'supplier' ? 'active' : ''; ?>" href="accounts.php?type=supplier">Suppliers</a>
                </li>
            </ul>

            <form class="search-bar" method="GET">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($account_type); ?>">
                <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">🔍</button>
            </form>

            <button class="btn btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#addAccountModal">➕ Add</button>
        </div>

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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= ucfirst(htmlspecialchars($row['type'])) ?></td>
                            <td><?= nl2br(htmlspecialchars($row['address'])) ?></td>
                            <td><?= htmlspecialchars($row['phone']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editModal<?= $row['id'] ?>">✏ Edit</button>
                                <a href="accounts.php?delete_id=<?= $row['id'] ?>&type=<?= $account_type ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this account?');">🗑 Delete</a>
                            </td>
                        </tr>

                        <!-- Edit Modal for each row -->
                        <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header bg-warning">
                                        <h5 class="modal-title">✏ Edit Account</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="edit_account" value="1">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <div class="mb-3">
                                                <label><strong>Name</strong></label>
                                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($row['name']) ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label><strong>Type</strong></label>
                                                <select name="type" class="form-select" required>
                                                    <option value="customer" <?= $row['type']=="customer"?"selected":"" ?>>Customer</option>
                                                    <option value="supplier" <?= $row['type']=="supplier"?"selected":"" ?>>Supplier</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label><strong>Address</strong></label>
                                                <textarea name="address" class="form-control"><?= htmlspecialchars($row['address']) ?></textarea>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label><strong>Phone</strong></label>
                                                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($row['phone']) ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label><strong>Email</strong></label>
                                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($row['email']) ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" class="btn btn-warning">💾 Update</button>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted">No accounts found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">➕ Add New Account</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
            <input type="hidden" name="add_account" value="1">
            <div class="mb-3">
                <label><strong>Name</strong></label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label><strong>Type</strong></label>
                <select name="type" class="form-select" required>
                    <option value="">-- Select --</option>
                    <option value="customer">Customer</option>
                    <option value="supplier">Supplier</option>
                </select>
            </div>
            <div class="mb-3">
                <label><strong>Address</strong></label>
                <textarea name="address" class="form-control"></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label><strong>Phone</strong></label>
                    <input type="text" name="phone" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label><strong>Email</strong></label>
                    <input type="email" name="email" class="form-control">
                </div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">💾 Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">✅ Success</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p><?= htmlspecialchars($success_message) ?></p>
      </div>
    </div>
  </div>
</div>

<?php if ($show_success_modal): ?>
<script>
document.addEventListener("DOMContentLoaded", function(){
    var myModal = new bootstrap.Modal(document.getElementById('successModal'));
    myModal.show();
});
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
