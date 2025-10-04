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
                    i.name AS item,
                    pd.quantity,
                    pd.weight,
                    pd.rate,
                    p.hamali,
                    p.freight,
                    pd.quantity * pd.rate AS item_total
                FROM purchases p
                INNER JOIN accounts acc ON p.supplier_id = acc.id
                INNER JOIN purchase_details pd ON p.id = pd.purchase_id
                INNER JOIN items i ON pd.item_id = i.id
                WHERE p.purchase_date BETWEEN ? AND ?
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
                    i.name AS item,
                    sd.quantity,
                    sd.weight,
                    sd.rate,
                    NULL AS hamali,
                    NULL AS freight,
                    sd.quantity * sd.rate AS item_total
                FROM sales s
                INNER JOIN accounts c ON s.customer_id = c.id
                INNER JOIN sale_details sd ON s.id = sd.sale_id
                INNER JOIN items i ON sd.item_id = i.id
                WHERE s.date BETWEEN ? AND ?
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

            // Aggregate results to avoid duplicate invoice totals
            $combined_results = [];
            $purchase_totals = ['amount' => 0, 'weight' => 0, 'hamali' => 0, 'freight' => 0, 'quantity' => 0];
            $sales_totals = ['amount' => 0, 'weight' => 0, 'quantity' => 0];
            $invoices_processed = [];

            // Process purchases
            while ($row = $result_purchase->fetch_assoc()) {
                $combined_results[] = $row;
                $invoice_id = 'p_' . $row['id'];
                if (!isset($invoices_processed[$invoice_id])) {
                    $purchase_totals['amount'] += $row['item_total'] + ($row['hamali'] ?? 0) + ($row['freight'] ?? 0);
                    $purchase_totals['hamali'] += $row['hamali'] ?? 0;
                    $purchase_totals['freight'] += $row['freight'] ?? 0;
                    $invoices_processed[$invoice_id] = true;
                }
                $purchase_totals['weight'] += $row['weight'];
                $purchase_totals['quantity'] += $row['quantity'];
            }

            // Process sales
            while ($row = $result_sales->fetch_assoc()) {
                $combined_results[] = $row;
                $invoice_id = 's_' . $row['id'];
                if (!isset($invoices_processed[$invoice_id])) {
                    $sales_totals['amount'] += $row['item_total'];
                    $invoices_processed[$invoice_id] = true;
                }
                $sales_totals['weight'] += $row['weight'];
                $sales_totals['quantity'] += $row['quantity'];
            }

            // Sort combined results by date and ID
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
                        <td>" . number_format($row['item_total'], 2) . "</td>
                    </tr>";
                    $i++;
                }
            } else {
                echo "<tr><td colspan='12' class='text-center text-muted'>
                        No records found in this date range
                      </td></tr>";
            }
            ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="6" class="text-end">Total Purchase</td>
                    <td><?= number_format($purchase_totals['quantity'], 2) ?></td>
                    <td><?= number_format($purchase_totals['weight'], 2) ?></td>
                    <td></td>
                    <td><?= number_format($purchase_totals['hamali'], 2) ?></td>
                    <td><?= number_format($purchase_totals['freight'], 2) ?></td>
                    <td><?= number_format($purchase_totals['amount'], 2) ?></td>
                </tr>
                <tr class="fw-bold">
                    <td colspan="6" class="text-end">Total Sales</td>
                    <td><?= number_format($sales_totals['quantity'], 2) ?></td>
                    <td><?= number_format($sales_totals['weight'], 2) ?></td>
                    <td></td>
                    <td>0.00</td>
                    <td>0.00</td>
                    <td><?= number_format($sales_totals['amount'], 2) ?></td>
                </tr>
                <tr class="fw-bold">
                    <td colspan="6" class="text-end">Remaining Amount</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td><?= number_format($sales_totals['amount'] - $purchase_totals['amount'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>