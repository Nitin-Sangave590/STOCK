<?php
// accounts.php - Account Master
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';

if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $type = $_POST['type'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];

    $sql = "INSERT INTO accounts (name, type, address, phone, email) VALUES ('$name', '$type', '$address', '$phone', '$email')";
    $conn->query($sql);
    header("Location: accounts.php");
    exit;
}

?>
<h2>Account Master</h2>
<a href="accounts.php?action=add" class="btn btn-primary mb-3">Add New Account</a>

<?php if ($action == 'add'): ?>
<form method="POST" class="mb-4">
    <div class="mb-3">
        <label>Name</label>
        <input type="text" name="name" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Type</label>
        <select name="type" class="form-select" required>
            <option value="customer">Customer</option>
            <option value="supplier">Supplier</option>
        </select>
    </div>
    <div class="mb-3">
        <label>Address</label>
        <textarea name="address" class="form-control"></textarea>
    </div>
    <div class="mb-3">
        <label>Phone</label>
        <input type="text" name="phone" class="form-control">
    </div>
    <div class="mb-3">
        <label>Email</label>
        <input type="email" name="email" class="form-control">
    </div>
    <button type="submit" class="btn btn-primary">Save Account</button>
    <a href="accounts.php" class="btn btn-secondary">Cancel</a>
</form>
<?php else: ?>
<h3>Existing Accounts</h3>
<table class="table table-striped">
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
        $result = $conn->query("SELECT * FROM accounts");
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['name']}</td>
                <td>{$row['type']}</td>
                <td>{$row['address']}</td>
                <td>{$row['phone']}</td>
            </tr>";
        }
        ?>
    </tbody>
</table>
<?php endif; ?>
<?php include 'footer.php'; ?>