<?php
include 'db.php';
include 'header.php';

$from = $_GET['from'] ?? date('Y-01-01');
$to   = $_GET['to'] ?? date('Y-m-d');
?>

<div class="container my-4">
    <h2 class="mb-4">Purchase Register</h2>

    <form class="row g-3 mb-4" method="GET">
        <div class="col-md-3">
            <label class="form-label">From Date</label>
            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">To Date</label>
            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
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
                    <th>Bill No</th>
                    <th>Supplier</th>
                    <th>Item</th>
                    <th>Weight</th>
                    <th>Rate</th>
                    <th>Hamali</th>
                    <th>Bhade</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // $sql = "
            //     SELECT p.id, p.invoice_number, p.purchase_date, i.name AS item, 
            //            p.weight, p.rate, p.hamali, p.freight, p.total_amount, 
            //            a.name AS supplier
            //     FROM purchases p
            //     JOIN accounts a ON p.supplier_id = a.id
            //     JOIN items i ON p.item_id = i.sku  -- Adjust 'item_id' and 'sku' based on your actual column names
            //     JOIN purchase_details a ON p.weight = i.sku 
            //     WHERE p.purchase_date BETWEEN ? AND ?
            //     ORDER BY p.purchase_date ASC
            // ";
            $sql = "
                SELECT 
                    p.id, 
                    p.invoice_number, 
                    p.purchase_date, 
                    i.name AS item, 
                    pd.weight, 
                    pd.rate, 
                    p.hamali, 
                    p.freight, 
                    p.total_amount, 
                    acc.name AS supplier
                FROM purchases p
                JOIN accounts acc 
                    ON p.supplier_id = acc.id
                JOIN purchase_details pd 
                    ON p.id = pd.purchase_id
                JOIN items i 
                    ON pd.item_id = i.sku
                WHERE p.purchase_date BETWEEN ? AND ?
                ORDER BY p.purchase_date ASC
            ";


            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                die("<div class='alert alert-danger'>SQL Prepare Error: " . $conn->error . "</div>");
            }

            $stmt->bind_param("ss", $from, $to);
            $stmt->execute();
            $result = $stmt->get_result();

            $total = 0;
            $i = 1;
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                        <td>{$i}</td>
                        <td>{$row['purchase_date']}</td>
                        <td>{$row['invoice_number']}</td>
                        <td>{$row['supplier']}</td>
                        <td>{$row['item']}</td>
                        <td>{$row['weight']}</td>
                        <td>{$row['rate']}</td>
                        <td>{$row['hamali']}</td>
                        <td>{$row['freight']}</td>
                        <td>{$row['total_amount']}</td>
                    </tr>";
                    $total += $row['total_amount'];
                    $i++;
                }
            } else {
                echo "<tr><td colspan='10' class='text-center text-muted'>No purchases found in this date range</td></tr>";
            }
            ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="9" class="text-end">Total</td>
                    <td><?= number_format($total, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>