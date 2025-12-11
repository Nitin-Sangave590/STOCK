<?php
// check_stock.php - Check Stock Module with Date Filter and Totals
include 'db.php';
include 'header.php';

// Verify database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Fetch items for dropdown with error handling
$stmt = $conn->prepare("SELECT id, name FROM items ORDER BY name ASC");
if ($stmt === false) {
    die("Failed to prepare statement for items: " . $conn->error);
}
if (!$stmt->execute()) {
    die("Failed to execute items query: " . $stmt->error);
}
$items_result = $stmt->get_result();
if ($items_result === false) {
    // Fallback to bind_result if get_result is not available
    $stmt->store_result();
    $items = [];
    $stmt->bind_result($id, $name);
    while ($stmt->fetch()) {
        $items[] = ['id' => $id, 'name' => $name];
    }
} else {
    $items = $items_result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();

// Get selected item and date filters from GET parameters
$selected_item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
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
        .footer-row { font-weight: bold; background-color: #f8f9fa; }
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

    <!-- Item and Date Filter -->
    <div class="card shadow p-3 mb-4">
        <form method="GET" action="check_stock.php" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Select Product</label>
                <select name="item_id" class="form-select">
                    <option value="0" <?= $selected_item_id == 0 ? 'selected' : '' ?>>All Products</option>
                    <?php
                    foreach ($items as $row) {
                        $selected = $selected_item_id == $row['id'] ? 'selected' : '';
                        echo "<option value='{$row['id']}' $selected>" . htmlspecialchars($row['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <div class="col-md-12 mt-2">
                <button type="submit" class="btn btn-primary">Apply Filter</button>
                <a href="check_stock.php" class="btn btn-secondary">Clear Filter</a>
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
                        <th>Purchase Amount</th>
                        <th>Purchase Rate</th>
                        <th>Last Avg Purchase Rate</th>
                        <th>Sale Qty</th>
                        <th>Sale Weight</th>
                        <th>Sale Amount</th>
                        <th>Sale Rate</th>
                        <th>Last Avg Sale Rate</th>
                        <th>Remaining Qty</th>
                        <th>Remaining Weight</th>
                        <th>Remaining Invested Amount</th>
                        <th>Avg Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Initialize totals
                    $totals = [
                        'total_purch_qty' => 0,
                        'total_purch_weight' => 0,
                        'total_purch_amount' => 0,
                        'avg_purch_rate' => 0,
                        'last_avg_purch_rate' => 0,
                        'total_sold_qty' => 0,
                        'total_sold_weight' => 0,
                        'total_sale_amount' => 0,
                        'avg_sale_rate' => 0,
                        'last_avg_sale_rate' => 0,
                        'remaining_qty' => 0,
                        'remaining_weight' => 0,
                        'remaining_invested_amount' => 0,
                        'avg_rate' => 0
                    ];  
                    $row_count = 0;

                    // Build the query with date filters
                    $query = "
                        SELECT 
                            i.id, 
                            i.name, 
                            COALESCE(SUM(pd.quantity), 0) AS total_purch_qty,
                            COALESCE(SUM(pd.weight), 0) AS total_purch_weight,
                            COALESCE(SUM(pd.total), 0) AS total_purch_amount,
                            COALESCE(SUM(pd.total) / NULLIF(SUM(pd.weight), 0), 0) AS avg_purch_rate,
                            COALESCE((SELECT AVG(rate) FROM purchase_details pd2 INNER JOIN purchases p2 ON pd2.purchase_id = p2.id WHERE pd2.item_id = i.id AND p2.purchase_date <= ? ORDER BY pd2.id DESC LIMIT 1), 0) AS last_avg_purch_rate,
                            COALESCE(SUM(sd.quantity), 0) AS total_sold_qty,
                            COALESCE(SUM(sd.weight), 0) AS total_sold_weight,
                            COALESCE(SUM(sd.total), 0) AS total_sale_amount,
                            COALESCE(SUM(sd.total) / NULLIF(SUM(sd.weight), 0), 0) AS avg_sale_rate,
                            COALESCE((SELECT AVG(rate) FROM sale_details sd2 INNER JOIN sales s2 ON sd2.sale_id = s2.id WHERE sd2.item_id = i.id AND s2.date <= ? ORDER BY sd2.id DESC LIMIT 1), 0) AS last_avg_sale_rate,
                            i.stock AS remaining_qty,
                            (COALESCE(SUM(pd.weight), 0) - COALESCE(SUM(sd.weight), 0)) AS remaining_weight,
                            (COALESCE(SUM(pd.total), 0) - COALESCE(SUM(sd.total), 0)) AS remaining_invested_amount
                        FROM items i
                        LEFT JOIN purchase_details pd ON i.id = pd.item_id
                        LEFT JOIN purchases p ON pd.purchase_id = p.id
                        LEFT JOIN sale_details sd ON i.id = sd.item_id
                        LEFT JOIN sales s ON sd.sale_id = s.id
                    ";

                    // Add conditions based on filters
                    $conditions = [];
                    $params = [$to_date ? $to_date : date('Y-m-d'), $to_date ? $to_date : date('Y-m-d')];
                    $param_types = "ss";

                    if ($selected_item_id > 0) {
                        $conditions[] = "i.id = ?";
                        $params[] = $selected_item_id;
                        $param_types .= "i";
                    }

                    if ($from_date && $to_date) {
                        $conditions[] = "(p.purchase_date BETWEEN ? AND ? OR p.purchase_date IS NULL)";
                        $conditions[] = "(s.date BETWEEN ? AND ? OR s.date IS NULL)";
                        $params[] = $from_date;
                        $params[] = $to_date;
                        $params[] = $from_date;
                        $params[] = $to_date;
                        $param_types .= "ssss";
                    } elseif ($from_date) {
                        $conditions[] = "(p.purchase_date >= ? OR p.purchase_date IS NULL)";
                        $conditions[] = "(s.date >= ? OR s.date IS NULL)";
                        $params[] = $from_date;
                        $params[] = $from_date;
                        $param_types .= "ss";
                    } elseif ($to_date) {
                        $conditions[] = "(p.purchase_date <= ? OR p.purchase_date IS NULL)";
                        $conditions[] = "(s.date <= ? OR s.date IS NULL)";
                        $params[] = $to_date;
                        $params[] = $to_date;
                        $param_types .= "ss";
                    }

                    // Add conditions to query
                    if (!empty($conditions)) {
                        $query .= " WHERE " . implode(" AND ", $conditions);
                    }

                    $query .= " GROUP BY i.id, i.name, i.stock ORDER BY i.name ASC";

                    // Prepare and execute the query
                    $stmt = $conn->prepare($query);
                    if ($stmt === false) {
                        die("Failed to prepare statement for stock summary: " . $conn->error);
                    }

                    if (!empty($params)) {
                        $param_refs = [];
                        foreach ($params as $key => $value) {
                            $param_refs[$key] = &$params[$key];
                        }
                        call_user_func_array([$stmt, 'bind_param'], array_merge([$param_types], $param_refs));
                    }

                    if (!$stmt->execute()) {
                        die("Failed to execute stock summary query: " . $stmt->error);
                    }
                    $result = $stmt->get_result();
                    if ($result === false) {
                        die("Failed to get stock summary result: " . $stmt->error);
                    }

                    while ($row = $result->fetch_assoc()) {
                        $avg_rate = $row['remaining_weight'] > 0 ? ($row['remaining_invested_amount'] / $row['remaining_weight']) : 0;
                        $row_count++;

                        // Accumulate totals
                        $totals['total_purch_qty'] += $row['total_purch_qty'];
                        $totals['total_purch_weight'] += $row['total_purch_weight'];
                        $totals['total_purch_amount'] += $row['total_purch_amount'];
                        $totals['avg_purch_rate'] += $row['avg_purch_rate'];
                        $totals['last_avg_purch_rate'] += $row['last_avg_purch_rate'];
                        $totals['total_sold_qty'] += $row['total_sold_qty'];
                        $totals['total_sold_weight'] += $row['total_sold_weight'];
                        $totals['total_sale_amount'] += $row['total_sale_amount'];
                        $totals['avg_sale_rate'] += $row['avg_sale_rate'];
                        $totals['last_avg_sale_rate'] += $row['last_avg_sale_rate'];
                        $totals['remaining_qty'] += $row['remaining_qty'];
                        $totals['remaining_weight'] += $row['remaining_weight'];
                        $totals['remaining_invested_amount'] += $row['remaining_invested_amount'];
                        $totals['avg_rate'] += $avg_rate;

                        echo "<tr>
                            <td>" . htmlspecialchars($row['name']) . "</td>
                            <td>" . number_format($row['total_purch_qty'], 2) . "</td>
                            <td>" . number_format($row['total_purch_weight'], 2) . "</td>
                            <td>" . number_format($row['total_purch_amount'], 2) . "</td>
                            <td>" . number_format($row['avg_purch_rate'], 2) . "</td>
                            <td>" . number_format($row['last_avg_purch_rate'], 2) . "</td>
                            <td>" . number_format($row['total_sold_qty'], 2) . "</td>
                            <td>" . number_format($row['total_sold_weight'], 2) . "</td>
                            <td>" . number_format($row['total_sale_amount'], 2) . "</td>
                            <td>" . number_format($row['avg_sale_rate'], 2) . "</td>
                            <td>" . number_format($row['last_avg_sale_rate'], 2) . "</td>
                            <td><strong>" . number_format($row['remaining_qty'], 2) . "</strong></td>
                            <td><strong>" . number_format($row['remaining_weight'], 2) . "</strong></td>
                            <td><strong>" . number_format($row['remaining_invested_amount'], 2) . "</strong></td>
                            <td>" . number_format($avg_rate, 2) . "</td>
                        </tr>";
                    }
                    $stmt->close();

                    // Calculate average rates for totals
                    $totals['avg_purch_rate'] = $row_count > 0 ? $totals['avg_purch_rate'] / $row_count : 0;
                    $totals['last_avg_purch_rate'] = $row_count > 0 ? $totals['last_avg_purch_rate'] / $row_count : 0;
                    $totals['avg_sale_rate'] = $row_count > 0 ? $totals['avg_sale_rate'] / $row_count : 0;
                    $totals['last_avg_sale_rate'] = $row_count > 0 ? $totals['last_avg_sale_rate'] / $row_count : 0;
                    $totals['avg_rate'] = $totals['remaining_weight'] > 0 ? $totals['remaining_invested_amount'] / $totals['remaining_weight'] : 0;
                    ?>
                </tbody>
                <tfoot>
                    <tr class="footer-row">
                        <td><strong>Total</strong></td>
                        <td><strong><?= number_format($totals['total_purch_qty'], 2) ?></strong></td>
                        <td><strong><?= number_format($totals['total_purch_weight'], 2) ?></strong></td>
                        <td><strong><?= number_format($totals['total_purch_amount'], 2) ?></strong></td>
                        <td><strong><?= number_format($totals['avg_purch_rate'], 2) ?></strong></td>
                        <td><strong><?= number_format($totals['last_avg_purch_rate'], 2) ?></strong></td>
                        <td><strong><?= number_format($totals['total_sold_qty'], 2) ?></strong></td>
                        <td><strong><?= number_format($totals['total_sold_weight'], 2) ?></strong></td>
                        <td><strong><?= number_format($totals['total_sale_amount'], 2) ?></strong></td>
                        <td><strong><?= number_format($totals['avg_sale_rate'], 2) ?></strong></td>
                        <td><strong><?= number_format($totals['last_avg_sale_rate'], 2) ?></strong></td>
                        <td><strong><?= number_format($totals['remaining_qty'], 2) ?></strong></td>
                        <td><strong><?= number_format($totals['remaining_weight'], 2) ?></strong></td>
                        <td><strong><?= number_format($totals['remaining_invested_amount'], 2) ?></strong></td>
                        <td><strong><?= number_format($totals['avg_rate'], 2) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>