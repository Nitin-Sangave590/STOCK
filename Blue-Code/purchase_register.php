<?php
include 'db.php';
include 'header.php';

// --- Date Filters (default: current year) ---
$from = $_GET['from'] ?? date('Y-01-01');
$to   = $_GET['to'] ?? date('Y-m-d');
?>

<div class="container my-4">
    <h2 class="mb-4">Purchase Register</h2>

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
            // ✅ Corrected Query with Proper Joins
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
                    ON pd.item_id = i.id   -- ✅ Changed to 'id' (most common PK for items)
                WHERE p.purchase_date BETWEEN ? AND ?
                ORDER BY p.purchase_date ASC, p.id ASC
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

                $total = 0;
                $i = 1;

                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                            <td>{$i}</td>
                            <td>" . htmlspecialchars($row['purchase_date']) . "</td>
                            <td>" . htmlspecialchars($row['invoice_number']) . "</td>
                            <td>" . htmlspecialchars($row['supplier']) . "</td>
                            <td>" . htmlspecialchars($row['item']) . "</td>
                            <td>" . number_format($row['weight'], 2) . "</td>
                            <td>" . number_format($row['rate'], 2) . "</td>
                            <td>" . number_format($row['hamali'], 2) . "</td>
                            <td>" . number_format($row['freight'], 2) . "</td>
                            <td>" . number_format($row['total_amount'], 2) . "</td>
                        </tr>";
                        $total += $row['total_amount'];
                        $i++;
                    }
                } else {
                    echo "<tr><td colspan='10' class='text-center text-muted'>
                            No purchases found in this date range
                          </td></tr>";
                }
            }
            ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="9" class="text-end">Total</td>
                    <td><?= number_format($total ?? 0, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
