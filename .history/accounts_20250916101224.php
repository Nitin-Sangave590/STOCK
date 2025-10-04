<?php
// accounts.php - Account Master
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Securely handle form data
    $name    = $conn->real_escape_string(trim($_POST['name']));
    $type    = $conn->real_escape_string(trim($_POST['type']));
    $address = $conn->real_escape_string(trim($_POST['address']));
    $phone   = $conn->real_escape_string(trim($_POST['phone']));
    $email   = $conn->real_escape_string(trim($_POST['email']));

    $sql = "INSERT INTO accounts (name, type, address, phone, email) 
            VALUES ('$name', '$type', '$address', '$phone', '$email')";

    if ($conn->query($sql)) {
        header("Location: accounts.php");
        exit;
    } else {
        echo "<div class='alert alert-danger container mt-3'>Error: " . $conn->error . "</div>";
    }
}
?>

<!-- ✅ Custom CSS -->
<style>
    body {
        background-color: #f8f9fa;
    }
    .card-custom {
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
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

                <button type="submit" class="btn btn-primary">
                    💾 Save Account
                </button>
                <a href="accounts.php" class="btn btn-secondary">
                    ❌ Cancel
                </a>
            </form>
        </div>
    <?php else: ?>
        <a href="accounts.php?action=add" class="btn btn-success mb-3 shadow-sm">
            ➕ Add New Account
        </a>

        <div class="card card-custom p-3">
            <h4 class="text-dark mb-3">📋 Existing Accounts <a href="index.php" class="btn btn-secondary margin-left:80">❌</a></h4>
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
                    $result = $conn->query("SELECT * FROM accounts ORDER BY id DESC");
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
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
