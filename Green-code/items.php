<?php
// items.php - Item Master with Add + Edit functionality (No Header Warning)
include 'db.php';

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// --- Handle Add Item (POST) ---
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $purchase_rate = $_POST['purchase_rate'] ?? 0;
    $sale_rate = $_POST['sale_rate'] ?? 0;
    $expenses = $_POST['expenses'] ?? 0;

    if (!empty($name)) {
        $stmt = $conn->prepare("INSERT INTO items (name, description, unit, purchase_rate, sale_rate, expenses)
                                VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssddd", $name, $description, $unit, $purchase_rate, $sale_rate, $expenses);
        if ($stmt->execute()) {
            header("Location: items.php");
            exit;
        } else {
            $errorMsg = "Error adding item: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $errorMsg = "Name is required!";
    }
}

// --- Handle Edit Item (POST Update) ---
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $purchase_rate = $_POST['purchase_rate'] ?? 0;
    $sale_rate = $_POST['sale_rate'] ?? 0;
    $expenses = $_POST['expenses'] ?? 0;

    if (!empty($name)) {
        $stmt = $conn->prepare("UPDATE items 
                                SET name=?, description=?, unit=?, purchase_rate=?, sale_rate=?, expenses=? 
                                WHERE id=?");
        $stmt->bind_param("sssdddi", $name, $description, $unit, $purchase_rate, $sale_rate, $expenses, $id);
        if ($stmt->execute()) {
            header("Location: items.php");
            exit;
        } else {
            $errorMsg = "Error updating item: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $errorMsg = "Name is required!";
    }
}

// --- Fetch Item for Editing ---
$editItem = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM items WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $editItem = $result->fetch_assoc();
    $stmt->close();

    if (!$editItem) {
        $errorMsg = "Item not found.";
        $action = ''; // fallback to list view
    }
}

include 'header.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Item Master</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 0; }
        h2, h3 { text-align: center; color: #333; }
        .container { width: 80%; margin: 30px auto; background: #fff; padding: 20px;
                     border-radius: 12px; box-shadow: 0px 4px 12px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 10px 15px; margin: 5px 0; border: none;
               border-radius: 6px; cursor: pointer; font-size: 14px; text-decoration: none; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-warning { background-color: #ffc107; color: black; }
        .btn-close { float: right; background: transparent; border: none;
                     font-size: 20px; font-weight: bold; cursor: pointer; color: #666; }
        .btn-close:hover { color: red; }
        .text-danger { color: red; text-align: center; }
        form .mb-3 { margin-bottom: 15px; }
        label { font-weight: bold; }
        input, textarea { width: 100%; padding: 8px; border: 1px solid #ccc;
            border-radius: 6px; margin-top: 5px; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table th, table td { padding: 10px; text-align: center; border: 1px solid #ddd; }
        table th { background: #f0f0f0; }
        table tr:hover { background: #f9f9f9; }

        #closeitemmaster{
            margin-left : 90%;

        }
        </style>
</head>
<body>
    <a href="index.php" id="closeitemmaster" class="btn btn-danger">❌</a>
<div class="container">
    <h2>Item Master</h2>

    <?php if (!empty($errorMsg)) echo "<p class='text-danger'>$errorMsg</p>"; ?>
    
    <?php if ($action === 'add' || $action === 'edit'): ?>
        <!-- <button class="btn-close" onclick="window.location.href='items.php'">❌</button> -->
        <h3><?= $action === 'edit' ? "✏️ Edit Item" : "➕ Add New Item" ?></h3>
        <form method="POST" action="items.php?action=<?= $action ?><?= $id > 0 ? "&id={$id}" : "" ?>">
            <div class="mb-3">
                <label>Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($editItem['name'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label>Description</label>
                <textarea name="description"><?= htmlspecialchars($editItem['description'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label>Unit (e.g., kg, pcs)</label>
                <input type="text" name="unit" value="<?= htmlspecialchars($editItem['unit'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label>Purchase Rate</label>
                <input type="number" step="0.01" name="purchase_rate" value="<?= htmlspecialchars($editItem['purchase_rate'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label>Sale Rate</label>
                <input type="number" step="0.01" name="sale_rate" value="<?= htmlspecialchars($editItem['sale_rate'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label>Expenses</label>
                <input type="number" step="0.01" name="expenses" value="<?= htmlspecialchars($editItem['expenses'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary">💾 <?= $action === 'edit' ? "Update" : "Save" ?> Item</button>
            <a href="items.php" class="btn btn-secondary">❌ Cancel</a>
        </form>
    <?php else: ?>
        <a href="items.php?action=add" class="btn btn-primary">➕ Add New Item</a>
        <h3>Existing Items</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Unit</th>
                    <th>Purchase Rate</th>
                    <th>Sale Rate</th>
                    <th>Expenses</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result = $conn->query("SELECT * FROM items ORDER BY id DESC");
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                            <td>{$row['id']}</td>
                            <td>{$row['name']}</td>
                            <td>{$row['description']}</td>
                            <td>{$row['unit']}</td>
                            <td>{$row['purchase_rate']}</td>
                            <td>{$row['sale_rate']}</td>
                            <td>{$row['expenses']}</td>
                            <td>
                                <a href='items.php?action=edit&id={$row['id']}' class='btn btn-warning btn-sm'>✏️ Edit</a>
                            </td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='8'>No items found</td></tr>";
                }
                ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
<?php include 'footer.php'; ?>
