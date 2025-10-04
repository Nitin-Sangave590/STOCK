<?php
// items.php - Item Master
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';

if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $unit = $_POST['unit'];
    $purchase_rate = $_POST['purchase_rate'];
    $sale_rate = $_POST['sale_rate'];
    $expenses = $_POST['expenses'];

    $sql = "INSERT INTO items (name, description, unit, purchase_rate, sale_rate, expenses) VALUES ('$name', '$description', '$unit', $purchase_rate, $sale_rate, $expenses)";
    $conn->query($sql);
    header("Location: items.php");
    exit;
}
?>
<h2>Item Master</h2>
<a href="items.php?action=add" class="btn btn-primary mb-3">Add New Item</a>

<?php if ($action == 'add'): ?>
<form method="POST" class="mb-4">
    <div class="mb-3">
        <label>Name</label>
        <input type="text" name="name" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Description</label>
        <textarea name="description" class="form-control"></textarea>
    </div>
    <div class="mb-3">
        <label>Unit (e.g., kg, pcs)</label>
        <input type="text" name="unit" class="form-control">
    </div>
    <div class="mb-3">
        <label>Purchase Rate</label>
        <input type="number" step="0.01" name="purchase_rate" class="form-control">
    </div>
    <div class="mb-3">
        <label>Sale Rate</label>
        <input type="number" step="0.01" name="sale_rate" class="form-control">
    </div>
    <div class="mb-3">
        <label>Expenses</label>
        <input type="number" step="0.01" name="expenses" class="form-control">
    </div>
    <button type="submit" class="btn btn-primary">Save Item</button>
    <a href="items.php" class="btn btn-secondary">Cancel</a>
</form>
<?php else: ?>
<h3>Existing Items</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Description</th>
            <th>Unit</th>
            <th>Purchase Rate</th>
            <th>Sale Rate</th>
            <th>Expenses</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $result = $conn->query("SELECT * FROM items");
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['name']}</td>
                <td>{$row['description']}</td>
                <td>{$row['unit']}</td>
                <td>{$row['purchase_rate']}</td>
                <td>{$row['sale_rate']}</td>
                <td>{$row['expenses']}</td>
            </tr>";
        }
        ?>
    </tbody>
</table>
<?php endif; ?>
<?php include 'footer.php'; ?>