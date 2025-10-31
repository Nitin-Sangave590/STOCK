<?php
include 'db.php';
include 'header.php';

// --- Date Filters (default: current year) ---
$from = $_GET['from'] ?? date('Y-01-01');
$to   = $_GET['to'] ?? date('Y-m-d');
?>

<style>
/* Print-specific styles */
@media print {
    .no-print {
        display: none !important;
    }
    body {
        font-family: Arial, sans-serif;
        font-size: 12px;
    }
    .table {
        width: 100%;
        border-collapse: collapse;
    }
    .table th, .table td {
        border: 1px solid #000;
        padding: 5px;
        text-align: left;
    }
    .table th {
        background-color: #f2f2f2;
    }
    .table tfoot {
        font-weight: bold;
        background-color: #e9ecef;
    }
    .container {
        margin: 0;
        width: 100%;
    }
    h2 {
        text-align: center;
        margin-bottom: 20px;
    }
}
</style>

<div class="container my-4">
    <h2 class="mb-4">Day-wise Purchase and Sale Report</h2>

    <!-- Filter Form -->
    <form class="row g-3 mb-4 no-print" method="GET">
        <div class="col-md-3">
            <label class="form-label">From Date</label>
            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">To Date</label>
            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>" required>
        </div>
        <div class="col-md-3 align-self-end">
            <button type="submit" class="btn btn-success">Filter</button>
        </div>
    </form>

    <!-- Print Button -->
    <div class="mb-4 no-print">
        <button onclick="window.print()" class="btn btn-primary">Print Preview</button>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Purchase Amount</th>
                    <th>Sale Amount</th>
                    <th>Net Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // SQL Query to aggregate purchase and sale totals by date
            $sql = "
                SELECT 
                    report_date,
                    COALESCE(SUM(purchase_amount), 0) AS purchase_amount,
                    COALESCE(SUM(sale_amount), 0) AS sale_amount
                FROM (
                    SELECT purchase_date AS report_date, SUM(total_amount) AS purchase_amount, 0 AS sale_amount
                    FROM purchases
                    WHERE purchase_date BETWEEN ? AND ?
                    GROUP BY purchase_date
                    UNION
                    SELECT date AS report_date, 0 AS purchase_amount, SUM(total_amount) AS sale_amount
                    FROM sales
                    WHERE date BETWEEN ? AND ?
                    GROUP BY date
                ) AS combined
                GROUP BY report_date
                ORDER BY report_date ASC
            ";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                echo "<tr><td colspan='5' class='text-danger text-center'>
                        SQL Prepare Error: " . htmlspecialchars($conn->error) . "
                      </td></tr>";
            } else {
                $stmt->bind_param("ssss", $from, $to, $from, $to);
                $stmt->execute();
                $result = $stmt->get_result();

                $total_purchase = 0;
                $total_sale = 0;
                $i = 1;

                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $net_amount = $row['sale_amount'] - $row['purchase_amount'];
                        echo "<tr>
                            <td>{$i}</td>
                            <td>" . htmlspecialchars($row['report_date']) . "</td>
                            <td>" . number_format($row['purchase_amount'], 2) . "</td>
                            <td>" . number_format($row['sale_amount'], 2) . "</td>
                            <td>" . number_format($net_amount, 2) . "</td>
                        </tr>";
                        $total_purchase += $row['purchase_amount'];
                        $total_sale += $row['sale_amount'];
                        $i++;
                    }
                } else {
                    echo "<tr><td colspan='5' class='text-center text-muted'>
                            No data found in this date range
                          </td></tr>";
                }
            }
            ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="2" class="text-end">Total</td>
                    <td><?= number_format($total_purchase ?? 0, 2) ?></td>
                    <td><?= number_format($total_sale ?? 0, 2) ?></td>
                    <td><?= number_format(($total_sale ?? 0) - ($total_purchase ?? 0), 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <!-- Display Date Range in Print View -->
    <div class="mt-3">
        <p><strong>Date Range:</strong> <?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?></p>
    </div>
</div>

<?php include 'footer.php'; ?>