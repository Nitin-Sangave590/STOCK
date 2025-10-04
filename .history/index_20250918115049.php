<?php
include 'db.php';
include 'header.php';

// Default date range (last 7 days)
$start_date = date('Y-m-d', strtotime('-7 days'));
$end_date = date('Y-m-d');

// Handle date filter if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter'])) {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
}

// Check if connection is valid
if (!$conn) {
    die("Database connection failed.");
}

// Fetch purchase data with error handling
$stmt = $conn->prepare("SELECT DATE(purchase_date) as date, SUM(pd.quantity) as qty, SUM(pd.weight) as weight, SUM(pd.total) as gross_amount, p.total_amount as net_amount 
                        FROM purchases p 
                        JOIN purchase_details pd ON p.id = pd.purchase_id 
                        WHERE p.purchase_date BETWEEN ? AND ? 
                        GROUP BY DATE(purchase_date) 
                        ORDER BY date ASC");
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$purchase_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate purchase totals
$purchase_totals = [
    'qty' => array_sum(array_column($purchase_data, 'qty')),
    'weight' => array_sum(array_column($purchase_data, 'weight')),
    'net_amount' => array_sum(array_column($purchase_data, 'net_amount')),
    'gross_amount' => array_sum(array_column($purchase_data, 'gross_amount')),
    'grand_total' => array_sum(array_column($purchase_data, 'net_amount')) // Assuming net_amount is the final total
];

// Fetch sales data (assuming a sales table exists with similar structure)
$stmt = $conn->prepare("SELECT DATE(sale_date) as date, SUM(sd.quantity) as qty, SUM(sd.weight) as weight, SUM(sd.total) as amount 
                        FROM sales s 
                        JOIN sales_details sd ON s.id = sd.sale_id 
                        WHERE s.sale_date BETWEEN ? AND ? 
                        GROUP BY DATE(sale_date) 
                        ORDER BY date ASC");
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$sales_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate sales totals
$sales_totals = [
    'qty' => array_sum(array_column($sales_data, 'qty')),
    'weight' => array_sum(array_column($sales_data, 'weight')),
    'amount' => array_sum(array_column($sales_data, 'amount'))
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container mt-4">
    <h2 class="fw-bold mb-4">Dashboard</h2>

    <!-- Date Filter Form -->
    <form method="POST" class="mb-4">
        <div class="row g-2">
            <div class="col-auto">
                <label class="form-label">Start Date:</label>
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>" required>
            </div>
            <div class="col-auto">
                <label class="form-label">End Date:</label>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>" required>
            </div>
            <div class="col-auto align-self-end">
                <button type="submit" name="filter" class="btn btn-primary">Filter</button>
            </div>
        </div>
    </form>

    <!-- Graphs Side by Side -->
    <div class="row">
        <!-- Purchase Graph -->
        <div class="col-md-6">
            <h4>Purchase Trends</h4>
            <canvas id="purchaseChart"></canvas>
        </div>
        <!-- Sales Graph -->
        <div class="col-md-6">
            <h4>Sales Trends</h4>
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Totals Section -->
    <div class="row mt-4">
        <div class="col-md-6">
            <h4>Purchase Totals</h4>
            <table class="table table-bordered">
                <tbody>
                    <tr><td>Quantity</td><td><?= number_format($purchase_totals['qty'], 2) ?></td></tr>
                    <tr><td>Weight</td><td><?= number_format($purchase_totals['weight'], 2) ?></td></tr>
                    <tr><td>Net Amount</td><td><?= number_format($purchase_totals['net_amount'], 2) ?></td></tr>
                    <tr><td>Gross Amount</td><td><?= number_format($purchase_totals['gross_amount'], 2) ?></td></tr>
                    <tr><td><strong>Grand Total</strong></td><td><strong><?= number_format($purchase_totals['grand_total'], 2) ?></strong></td></tr>
                </tbody>
            </table>
        </div>
        <div class="col-md-6">
            <h4>Sales Totals</h4>
            <table class="table table-bordered">
                <tbody>
                    <tr><td>Quantity</td><td><?= number_format($sales_totals['qty'], 2) ?></td></tr>
                    <tr><td>Weight</td><td><?= number_format($sales_totals['weight'], 2) ?></td></tr>
                    <tr><td>Amount</td><td><?= number_format($sales_totals['amount'], 2) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Purchase Chart
    const purchaseCtx = document.getElementById('purchaseChart').getContext('2d');
    new Chart(purchaseCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($purchase_data, 'date')) ?>,
            datasets: [
                {
                    label: 'Quantity',
                    data: <?= json_encode(array_column($purchase_data, 'qty')) ?>,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    fill: false
                },
                {
                    label: 'Weight',
                    data: <?= json_encode(array_column($purchase_data, 'weight')) ?>,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    fill: false
                },
                {
                    label: 'Net Amount',
                    data: <?= json_encode(array_column($purchase_data, 'net_amount')) ?>,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    fill: false
                },
                {
                    label: 'Gross Amount',
                    data: <?= json_encode(array_column($purchase_data, 'gross_amount')) ?>,
                    borderColor: 'rgba(255, 206, 86, 1)',
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Sales Chart
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($sales_data, 'date')) ?>,
            datasets: [
                {
                    label: 'Quantity',
                    data: <?= json_encode(array_column($sales_data, 'qty')) ?>,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    fill: false
                },
                {
                    label: 'Weight',
                    data: <?= json_encode(array_column($sales_data, 'weight')) ?>,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    fill: false
                },
                {
                    label: 'Amount',
                    data: <?= json_encode(array_column($sales_data, 'amount')) ?>,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>

<?php include 'footer.php'; ?>