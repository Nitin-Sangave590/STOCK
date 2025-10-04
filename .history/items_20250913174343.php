<?php
// items.php - Item Master
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';

if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    // Safely fetch POST values
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $unit = $_POST['unit'] ?? '';
    $purchase_rate = $_POST['purchase_rate'] ?? 0;
    $sale_rate = $_POST['sale_rate'] ?? 0;
    $expenses = $_POST['expenses'] ?? 0;

    // Insert into database
    $sql = "INSERT INTO items (name, description, unit, purchase_rate, sale_rate, expenses) 
            VALUES ('$name', '$description', '$unit', $purchase_rate, $sale_rate, $expenses)";
    $conn->query($sql);
    header("Location: items.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Item Master</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9f9f9;
            margin: 0;
            padding: 0;
        }
        h2, h3 {
            text-align: center;
            color: #333;
        }
        .container {
            width: 80%;
            margin: 30px auto;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            margin: 5px 0;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        form .mb-3 {
            margin-bottom: 15px;
        }
        label {
            font-weight: bold;
        }
        input, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table th, table td {
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
        }
        table th {
            background: #f0f0f0;
        }
        table tr:hover {
            background: #f9f9f9;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Item Master</h2>
    <a href="items.php?action=add" class="btn btn-primary">➕ Add New Item</a>

    <?php if ($action == 'add'): ?>
    <form method="POST" class="mb-4">
        <div class="mb-3">
            <label>Name</label>
            <input type="text" name="name" required>
        </div>
        <div class="mb-3">
            <label>Description</label>
            <textarea name="description"></textarea>
        </div>
        <div class="mb-3">
            <label>Unit (e.g., kg, pcs)</label>
            <input type="text" name="unit">
        </div>
        <div class="mb-3">
            <label>Purchase Rate</label>
            <input type="number" step="0.01" name="purchase_rate">
        </div>
        <div class="mb-3">
            <label>Sale Rate</label>
            <input type="number" step="0.01" name="sale_rate">
        </div>
        <div class="mb-3">
            <label>Expenses</label>
            <input type="number" step="0.01" name="expenses">
        </div>
        <button type="submit" class="btn btn-primary">💾 Save Item</button>
        <a href="items.php" class="btn btn-secondary">❌ Cancel</a>
    </form>
    <?php else: ?>
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
</div>
</body>
</html>
<?php include 'footer.php'; ?>
