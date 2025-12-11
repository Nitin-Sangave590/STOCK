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
    <h2 class="mb-4">Purchase Register</h2>

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
                    <th>Patti No</th> 
                    <th>Supplier</th>
                    <th>Item</th>
                    <th>Weight</th>
                    <th>Rate</th>
                    <th>Hamali</th>
                    <th>Freight</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // Query to fetch purchase details
            $sql = "
                SELECT 
                    p.id,
                    p.invoice_number,
                    p.purchase_date,
                    acc.name AS supplier,
                    i.name AS item,
                    pd.weight,
                    pd.rate,
                    p.hamali,
                    p.freight,
                    p.total_amount
                FROM purchases p
                INNER JOIN accounts acc 
                    ON p.supplier_id = acc.id
                INNER JOIN purchase_details pd 
                    ON p.id = pd.purchase_id
                INNER JOIN items i 
                    ON pd.item_id = i.id
                WHERE p.purchase_date BETWEEN ? AND ?
                ORDER BY p.purchase_date ASC, p.id ASC, p.invoice_number
            ";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                echo "<tr><td colspan='10' class='text-danger text-center'>
                        SQL Prepare Error: " . htmlspecialchars($conn->error) . "
                      </td></tr>";
            } else {
                $stmt->bind_param("ss", $from, $to);
                $stmt->execute();
                $result = $stmt->get_result();

                $total_weight = 0;
                $total_hamali = 0;
                $total_freight = 0;
                $total_amount = 0;
                $total_rate = 0;
                $count = 0;
                $i = 1;
                $processed_invoices = []; // Track processed invoice numbers

                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $invoice_number = $row['invoice_number'];

                        echo "<tr>
                            <td>{$i}</td>
                            <td>" . htmlspecialchars($row['purchase_date']) . "</td>
                            <td>" . htmlspecialchars($invoice_number) . "</td>
                            <td>" . htmlspecialchars($row['supplier']) . "</td>
                            <td>" . htmlspecialchars($row['item']) . "</td>
                            <td>" . number_format($row['weight'], 2) . "</td>
                            <td>" . number_format($row['rate'], 2) . "</td>
                            <td>" . number_format($row['hamali'], 2) . "</td>
                            <td>" . number_format($row['freight'], 2) . "</td>
                            <td>" . number_format($row['total_amount'], 2) . "</td>
                        </tr>";

                        // Accumulate totals
                        $total_weight += $row['weight'];
                        $total_rate += $row['rate'];
                        $count++;
                        // $total_amount += $row['total_amount'];

                        // Only add hamali and freight once per invoice number
                        if (!in_array($invoice_number, $processed_invoices)) {
                            $total_hamali += $row['hamali'];
                            $total_freight += $row['freight'];
                            $total_amount += $row ['total_amount'];
                            $processed_invoices[] = $invoice_number;
                        }

                        $i++;
                    }
                } else {
                    echo "<tr><td colspan='10' class='text-center text-muted'>
                            No purchases found in this date range
                          </td></tr>";
                }

                $avg_rate = ($count > 0) ? $total_rate / $count : 0;
                $stmt->close();
            }
            ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="5" class="text-end">Total</td>
                    <td><?= number_format($total_weight, 2) ?></td>
                    <td><?= number_format($avg_rate, 2) ?></td>
                    <td><?= number_format($total_hamali, 2) ?></td>
                    <td><?= number_format($total_freight, 2) ?></td>
                    <td><?= number_format($total_amount, 2) ?></td>
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