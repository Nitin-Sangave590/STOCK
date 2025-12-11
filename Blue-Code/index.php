<?php
include 'db.php';
include 'header.php';

// Default date range (last 7 days)
$start_date = date('Y-m-d', strtotime('-7 days')); // September 11, 2025
$end_date = date('Y-m-d'); // September 18, 2025, 11:52 AM IST

// Handle date filter if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter'])) {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
}

// Check if connection is valid
if (!$conn) {
    die("Database connection failed.");
}

// Fetch purchase data by date
$stmt = $conn->prepare("SELECT DATE(purchase_date) as date, SUM(pd.quantity) as qty, SUM(pd.weight) as weight, SUM(pd.total) as amount 
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
$purchase_date_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch purchase data product-wise
$stmt = $conn->prepare("SELECT i.name, SUM(pd.quantity) as qty, SUM(pd.weight) as weight, SUM(pd.total) as amount 
                        FROM purchases p 
                        JOIN purchase_details pd ON p.id = pd.purchase_id 
                        JOIN items i ON pd.item_id = i.id 
                        WHERE p.purchase_date BETWEEN ? AND ? 
                        GROUP BY i.name 
                        ORDER BY i.name ASC");
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$purchase_product_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch sale data by date
$stmt = $conn->prepare("SELECT DATE(date) as date, SUM(sd.quantity) as qty, SUM(sd.weight) as weight, SUM(sd.total) as amount 
                        FROM sales s 
                        JOIN sale_details sd ON s.id = sd.sale_id 
                        WHERE s.date BETWEEN ? AND ? 
                        GROUP BY DATE(date) 
                        ORDER BY date ASC");
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$sale_date_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch sale data product-wise
$stmt = $conn->prepare("SELECT i.name, SUM(sd.quantity) as qty, SUM(sd.weight) as weight, SUM(sd.total) as amount 
                        FROM sales s 
                        JOIN sale_details sd ON s.id = sd.sale_id 
                        JOIN items i ON sd.item_id = i.id 
                        WHERE s.date BETWEEN ? AND ? 
                        GROUP BY i.name 
                        ORDER BY i.name ASC");
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$sale_product_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f0f4f8, #d9e2ec);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        .container {
            max-width: 1400px;
            margin-top: 20px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .filter-form {
            background: #ffffff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        .card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background: linear-gradient(90deg, #3498db, #2980b9);
            color: #fff;
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .chart-container {
            position: relative;
            margin: 20px 0;
            height: 400px;
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .daywise-table {
            overflow-x: auto;
            background: #fff;
            border-radius: 10px;
            padding: 15px;
        }
        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .table thead th {
            background: #34495e;
            color: #fff;
            padding: 12px;
            text-align: center;
            border-bottom: 2px solid #2c3e50;
        }
        .table tbody td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #eee;
            transition: background 0.3s ease;
        }
        .table tbody tr:hover {
            background: #f9f9f9;
        }
        .btn-primary {
            background: #3498db;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            transition: background 0.3s ease;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        #detail{
                    margin-left: 39%;
                }
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            .chart-container {
                height: 300px;
            }
            .filter-form .col-auto {
                width: 100%;
                margin-bottom: 10px;
            }
            
    </style>
</head>
<body>
<div class="container">
    <h4 id="detail" class="fw-bold">!! श्री शिवाय नमस्तुभ्यम् !! </h4>
    <h2 class="fw-bold">Bussiness Graph</h2>

    <!-- Date Filter Form -->
    <div class="filter-form">
        <form method="POST" class="row g-2 justify-content-center">
            <div class="col-auto">
                <label class="form-label" style="color: #2c3e50;">From Date:</label>
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>" required>
            </div>
            <div class="col-auto">
                <label class="form-label" style="color: #2c3e50;">To Date:</label>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>" required>
            </div>
            <div class="col-auto align-self-end">
                <button type="submit" name="filter" class="btn btn-primary">Apply Filter</button>
            </div>
        </div>
    </div>

    <!-- Purchase Trends -->
    <div class="card">
        <div class="card-header">Purchase Trends</div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="purchaseDateChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Purchase Product-wise -->
    <div class="card">
        <div class="card-header">Purchase by Product</div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="purchaseProductChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Sale Trends -->
    <div class="card">
        <div class="card-header">Sale Trends</div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="saleDateChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Sale Product-wise -->
    <div class="card">
        <div class="card-header">Sale by Product</div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="saleProductChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Day-wise Details Module -->
    <div class="card">
        <div class="card-header">Day-wise Details</div>
        <div class="card-body daywise-table">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Purchase Qty</th>
                        <th>Purchase Weight</th>
                        <th>Purchase Amount</th>
                        <th>Sale Qty</th>
                        <th>Sale Weight</th>
                        <th>Sale Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Merge purchase and sale data by date
                    $purchase_map = array_column($purchase_date_data, null, 'date');
                    $sale_map = array_column($sale_date_data, null, 'date');
                    $all_dates = array_unique(array_merge(array_keys($purchase_map), array_keys($sale_map)));
                    sort($all_dates);

                    foreach ($all_dates as $date) {
                        $purchase = $purchase_map[$date] ?? ['qty' => 0, 'weight' => 0, 'amount' => 0];
                        $sale = $sale_map[$date] ?? ['qty' => 0, 'weight' => 0, 'amount' => 0];
                        echo "<tr>
                            <td>" . htmlspecialchars($date) . "</td>
                            <td>" . number_format($purchase['qty'], 2) . "</td>
                            <td>" . number_format($purchase['weight'], 2) . "</td>
                            <td>" . number_format($purchase['amount'], 2) . "</td>
                            <td>" . number_format($sale['qty'], 2) . "</td>
                            <td>" . number_format($sale['weight'], 2) . "</td>
                            <td>" . number_format($sale['amount'], 2) . "</td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Purchase Date Chart
    const purchaseDateCtx = document.getElementById('purchaseDateChart').getContext('2d');
    new Chart(purchaseDateCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($purchase_date_data, 'date')) ?>,
            datasets: [
                {
                    label: 'Quantity',
                    data: <?= json_encode(array_column($purchase_date_data, 'qty')) ?>,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    fill: false,
                    tension: 0.4
                },
                {
                    label: 'Weight',
                    data: <?= json_encode(array_column($purchase_date_data, 'weight')) ?>,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    fill: false,
                    tension: 0.4
                },
                {
                    label: 'Amount',
                    data: <?= json_encode(array_column($purchase_date_data, 'amount')) ?>,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    fill: false,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Value' }
                },
                x: {
                    title: { display: true, text: 'Date' }
                }
            },
            plugins: {
                legend: { position: 'top' },
                tooltip: { mode: 'index', intersect: false }
            }
        }
    });

    // Purchase Product Chart
    const purchaseProductCtx = document.getElementById('purchaseProductChart').getContext('2d');
    new Chart(purchaseProductCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($purchase_product_data, 'name')) ?>,
            datasets: [
                {
                    label: 'Quantity',
                    data: <?= json_encode(array_column($purchase_product_data, 'qty')) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Weight',
                    data: <?= json_encode(array_column($purchase_product_data, 'weight')) ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.6)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Amount',
                    data: <?= json_encode(array_column($purchase_product_data, 'amount')) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Value' }
                },
                x: {
                    title: { display: true, text: 'Product' }
                }
            },
            plugins: {
                legend: { position: 'top' },
                tooltip: { mode: 'index', intersect: false }
            }
        }
    });

    // Sale Date Chart
    const saleDateCtx = document.getElementById('saleDateChart').getContext('2d');
    new Chart(saleDateCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($sale_date_data, 'date')) ?>,
            datasets: [
                {
                    label: 'Quantity',
                    data: <?= json_encode(array_column($sale_date_data, 'qty')) ?>,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    fill: false,
                    tension: 0.4
                },
                {
                    label: 'Weight',
                    data: <?= json_encode(array_column($sale_date_data, 'weight')) ?>,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    fill: false,
                    tension: 0.4
                },
                {
                    label: 'Amount',
                    data: <?= json_encode(array_column($sale_date_data, 'amount')) ?>,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    fill: false,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Value' }
                },
                x: {
                    title: { display: true, text: 'Date' }
                }
            },
            plugins: {
                legend: { position: 'top' },
                tooltip: { mode: 'index', intersect: false }
            }
        }
    });

    // Sale Product Chart
    const saleProductCtx = document.getElementById('saleProductChart').getContext('2d');
    new Chart(saleProductCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($sale_product_data, 'name')) ?>,
            datasets: [
                {
                    label: 'Quantity',
                    data: <?= json_encode(array_column($sale_product_data, 'qty')) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Weight',
                    data: <?= json_encode(array_column($sale_product_data, 'weight')) ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.6)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Amount',
                    data: <?= json_encode(array_column($sale_product_data, 'amount')) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Value' }
                },
                x: {
                    title: { display: true, text: 'Product' }
                }
            },
            plugins: {
                legend: { position: 'top' },
                tooltip: { mode: 'index', intersect: false }
            }
        }
    });
});
</script>

<?php include 'footer.php'; ?>