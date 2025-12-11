<?php
include 'db.php';
include 'header.php';

// --- Date Filters (default: current year) ---
$from = $_GET['from'] ?? date('Y-01-01');
$to   = $_GET['to'] ?? date('Y-m-d');
?>

<div class="container my-4">
    <h2 class="mb-4">Combine Register</h2>

    <!-- Filter Form -->
    <form class="row g-3 mb-4" method="GET">
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

    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>P/B No</th>
                    <th>Supplier/Customer</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Weight</th>
                    <th>Rate</th>
                    <th>Hamali</th>
                    <th>Freight</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // --- Purchase Register Query ---
            $sql_purchase = "
                SELECT 
                    p.id,
                    p.invoice_number,
                    p.purchase_date AS date,
                    'Purchase' AS type,
                    acc.name AS supplier_customer,
                    GROUP_CONCAT(i.name) AS item,
                    SUM(pd.quantity) AS quantity,
                    SUM(pd.weight) AS weight,
                    AVG(pd.rate) AS rate,
                    p.hamali,
                    p.freight,
                    p.total_amount
                FROM purchases p
                INNER JOIN accounts acc ON p.supplier_id = acc.id
                INNER JOIN purchase_details pd ON p.id = pd.purchase_id
                INNER JOIN items i ON pd.item_id = i.id
                WHERE p.purchase_date BETWEEN ? AND ?
                GROUP BY p.id, p.invoice_number, p.purchase_date, acc.name, p.hamali, p.freight, p.total_amount
                ORDER BY p.purchase_date ASC, p.id ASC
            ";

            // --- Sales Register Query ---
            $sql_sales = "
                SELECT 
                    s.id,
                    s.invoice_number,
                    s.date,
                    'Sale' AS type,
                    c.name AS supplier_customer,
                    GROUP_CONCAT(i.name) AS item,
                    SUM(sd.quantity) AS quantity,
                    SUM(sd.weight) AS weight,
                    AVG(sd.rate) AS rate,
                    NULL AS hamali,
                    NULL AS freight,
                    s.total_amount
                FROM sales s
                INNER JOIN accounts c ON s.customer_id = c.id
                INNER JOIN sale_details sd ON s.id = sd.sale_id
                INNER JOIN items i ON sd.item_id = i.id
                WHERE s.date BETWEEN ? AND ?
                GROUP BY s.id, s.invoice_number, s.date, c.name, s.total_amount
                ORDER BY s.date ASC, s.id ASC
            ";

            // Prepare and execute queries
            $stmt_purchase = $conn->prepare($sql_purchase);
            $stmt_purchase->bind_param("ss", $from, $to);
            $stmt_purchase->execute();
            $result_purchase = $stmt_purchase->get_result();

            $stmt_sales = $conn->prepare($sql_sales);
            $stmt_sales->bind_param("ss", $from, $to);
            $stmt_sales->execute();
            $result_sales = $stmt_sales->get_result();

            // Merging results
            $combined_results = [];
            $total_purchase_qty = 0;
            $total_sales_qty = 0;
            $total_purchase_weight = 0;
            $total_sales_weight = 0;
            $total_purchase_hamali = 0;
            $total_sales_hamali = 0;
            $total_purchase_freight = 0;
            $total_sales_freight = 0;
            $total_purchase_amount = 0;
            $total_sales_amount = 0;

            while ($row = $result_purchase->fetch_assoc()) {
                $combined_results[] = $row;
                $total_purchase_qty += $row['quantity'];
                $total_purchase_weight += $row['weight'];
                $total_purchase_hamali += $row['hamali'] ?? 0;
                $total_purchase_freight += $row['freight'] ?? 0;
                $total_purchase_amount += $row['total_amount'];
            }

            while ($row = $result_sales->fetch_assoc()) {
                $combined_results[] = $row;
                $total_sales_qty += $row['quantity'];
                $total_sales_weight += $row['weight'];
                $total_sales_hamali += $row['hamali'] ?? 0;
                $total_sales_freight += $row['freight'] ?? 0;
                $total_sales_amount += $row['total_amount'];
            }

            // Sorting by date and invoice ID
            usort($combined_results, function($a, $b) {
                return strtotime($a['date']) - strtotime($b['date']) ?: $a['id'] - $b['id'];
            });

            $i = 1;

            if (count($combined_results) > 0) {
                foreach ($combined_results as $row) {
                    echo "<tr>
                        <td>{$i}</td>
                        <td>" . htmlspecialchars($row['date']) . "</td>
                        <td>" . htmlspecialchars($row['type']) . "</td>
                        <td>" . htmlspecialchars($row['invoice_number']) . "</td>
                        <td>" . htmlspecialchars($row['supplier_customer']) . "</td>
                        <td>" . htmlspecialchars($row['item']) . "</td>
                        <td>" . number_format($row['quantity'], 2) . "</td>
                        <td>" . number_format($row['weight'], 2) . "</td>
                        <td>" . number_format($row['rate'], 2) . "</td>
                        <td>" . number_format($row['hamali'] ?? 0, 2) . "</td>
                        <td>" . number_format($row['freight'] ?? 0, 2) . "</td>
                        <td>" . number_format($row['total_amount'], 2) . "</td>
                    </tr>";
                    $i++;
                }
            } else {
                echo "<tr><td colspan='12' class='text-center text-muted'>
                        No records found in this date range
                      </td></tr>";
            }

            // Close statements
            $stmt_purchase->close();
            $stmt_sales->close();
            ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="6" class="text-end">Total Purchase</td>
                    <td><?= number_format($total_purchase_qty, 2) ?></td>
                    <td><?= number_format($total_purchase_weight, 2) ?></td>
                    <td></td>
                    <td><?= number_format($total_purchase_hamali, 2) ?></td>
                    <td><?= number_format($total_purchase_freight, 2) ?></td>
                    <td><?= number_format($total_purchase_amount, 2) ?></td>
                </tr>
                <tr class="fw-bold">
                    <td colspan="6" class="text-end">Total Sales</td>
                    <td><?= number_format($total_sales_qty, 2) ?></td>
                    <td><?= number_format($total_sales_weight, 2) ?></td>
                    <td></td>
                    <td><?= number_format($total_sales_hamali, 2) ?></td>
                    <td><?= number_format($total_sales_freight, 2) ?></td>
                    <td><?= number_format($total_sales_amount, 2) ?></td>
                </tr>
                <tr class="fw-bold">
                    <td colspan="6" class="text-end">Remaining</td>
                    <td><?= number_format($total_sales_qty - $total_purchase_qty, 2) ?></td>
                    <td><?= number_format($total_sales_weight - $total_purchase_weight, 2) ?></td>
                    <td></td>
                    <td><?= number_format($total_sales_hamali - $total_purchase_hamali, 2) ?></td>
                    <td><?= number_format($total_sales_freight - $total_purchase_freight, 2) ?></td>
                    <td><?= number_format($total_sales_amount - $total_purchase_amount, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>