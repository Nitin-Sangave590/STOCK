<?php
// check_stock.php - Check Stock Module
include 'db.php';
include 'header.php';

// Fetch items for dropdown
$stmt = $conn->prepare("SELECT id, name FROM items ORDER BY name ASC");
if ($stmt === false) {
    die("Failed to prepare statement for items: " . $conn->error);
}
$stmt->execute();
$items = $stmt->get_result();
$stmt->close();

// Get selected item from dropdown (if any)
$selected_item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-responsive { overflow-x: auto; }
        .table th, .table td { vertical-align: middle; }
        #cancel { margin-left: 90%; margin-bottom: -2%; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold">📊 Check Stock</h2>
        <a href="index.php" id="cancel" class="btn btn-danger">❌ Close</a>
    </div>

    <!-- Item Filter -->
    <div class="card shadow p-3 mb-4">
        <form method="GET" action="check_stock.php" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Select Product</label>
                <select name="item_id" class="form-select" onchange="this.form.submit()">
                    <option value="0" <?= $selected_item_id == 0 ? 'selected' : '' ?>>All Products</option>
                    <?php
                    $items->data_seek(0);
                    while ($row = $items->fetch_assoc()) {
                        $selected = $selected_item_id == $row['id'] ? 'selected' : '';
                        echo "<option value='{$row['id']}' $selected>" . htmlspecialchars($row['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
        </form>
    </div>

    <!-- Stock Summary Table -->
    <div class="card shadow p-3">
        <h4 class="fw-bold mb-3">Stock Summary</h4>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Item</th>
                        <th>Purchase Qty</th>
                        <th>Purchase Weight</th>
                        <th>Purchase Rate</th>
                        <th>Last Avg Purchase Rate</th>
                        <th>Sale Qty</th>
                        <th>Sale Weight</th>
                        <th>Sale Rate</th>
                        <th>Last Avg Sale Rate</th>
                        <th>Remaining Qty</th>
                        <th>Remaining Weight</th>
                        <th>Invested Amount</th>
                        <th>Avg Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Build the query based on whether a specific item is selected
                    $query = "
                        SELECT 
                            i.id, 
                            i.name, 
                            COALESCE(SUM(pd.quantity), 0) AS total_purch_qty,
                            COALESCE(SUM(pd.weight), 0) AS total_purch_weight,
                            COALESCE(SUM(pd.total) / NULLIF(SUM(pd.weight), 0), 0) AS avg_purch_rate,
                            COALESCE((SELECT AVG(rate) FROM purchase_details pd2 WHERE pd2.item_id = i.id ORDER BY pd2.id DESC LIMIT 1), 0) AS last_avg_purch_rate,
                            COALESCE(SUM(sd.quantity), 0) AS total_sold_qty,
                            COALESCE(SUM(sd.weight), 0) AS total_sold_weight,
                            COALESCE(SUM(sd.total) / NULLIF(SUM(sd.weight), 0), 0) AS avg_sale_rate,
                            COALESCE((SELECT AVG(rate) FROM sale_details sd2 WHERE sd2.item_id = i.id ORDER BY sd2.id DESC LIMIT 1), 0) AS last_avg_sale_rate,
                            i.stock AS remaining_qty,
                            (COALESCE(SUM(pd.weight), 0) - COALESCE(SUM(sd.weight), 0)) AS remaining_weight,
                            COALESCE(SUM(pd.total), 0) AS invested_amount
                        FROM items i
                        LEFT JOIN purchase_details pd ON i.id = pd.item_id
                        LEFT JOIN sale_details sd ON i.id = sd.item_id
                    ";
                    if ($selected_item_id > 0) {
                        $query .= " WHERE i.id = ?";
                    }
                    $query .= " GROUP BY i.id, i.name, i.stock ORDER BY i.name ASC";

                    $stmt = $conn->prepare($query);
                    if ($stmt === false) {
                        die("Failed to prepare statement for stock summary: " . $conn->error);
                    }
                    if ($selected_item_id > 0) {
                        $stmt->bind_param("i", $selected_item_id);
                    }
                    $stmt->execute();
                    $result = $stmt->get_result();

                    while ($row = $result->fetch_assoc()) {
                        $avg_rate = $row['remaining_weight'] > 0 ? ($row['invested_amount'] / $row['remaining_weight']) : 0;
                        echo "<tr>
                            <td>" . htmlspecialchars($row['name']) . "</td>
                            <td>" . number_format($row['total_purch_qty'], 2) . "</td>
                            <td>" . number_format($row['total_purch_weight'], 2) . "</td>
                            <td>" . number_format($row['avg_purch_rate'], 2) . "</td>
                            <td>" . number_format($row['last_avg_purch_rate'], 2) . "</td>
                            <td>" . number_format($row['total_sold_qty'], 2) . "</td>
                            <td>" . number_format($row['total_sold_weight'], 2) . "</td>
                            <td>" . number_format($row['avg_sale_rate'], 2) . "</td>
                            <td>" . number_format($row['last_avg_sale_rate'], 2) . "</td>
                            <td><strong>" . number_format($row['remaining_qty'], 2) . "</strong></td>
                            <td><strong>" . number_format($row['remaining_weight'], 2) . "</strong></td>
                            <td>" . number_format($row['invested_amount'], 2) . "</td>
                            <td>" . number_format($avg_rate, 2) . "</td>
                        </tr>";
                    }
                    $stmt->close();
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>