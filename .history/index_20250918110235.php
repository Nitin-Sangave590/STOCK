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
        .chart-container {
            position: relative;
            margin: 20px 0;
            height: 400px;
        }
        .card {
            border: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <h2 class="fw-bold mb-4 text-center">Dashboard</h2>

    <!-- Date Filter Form -->
    <form method="POST" class="mb-4">
        <div class="row g-2 justify-content-center">
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

    <!-- Purchase Trends -->
    <div class="card">
        <div class="card-header">
            <h4>Purchase Trends</h4>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="purchaseDateChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Purchase Product-wise -->
    <div class="card">
        <div class="card-header">
            <h4>Purchase by Product</h4>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="purchaseProductChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Sale Trends -->
    <div class="card">
        <div class="card-header">
            <h4>Sale Trends</h4>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="saleDateChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Sale Product-wise -->
    <div class="card">
        <div class="card-header">
            <h4>Sale by Product</h4>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="saleProductChart"></canvas>
            </div>
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