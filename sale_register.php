<?php
include 'db.php';
include 'header.php';

// --- Date Filters (default: current year) ---
$from = $_GET['from'] ?? date('Y-01-01');
$to   = $_GET['to'] ?? date('Y-m-d');
?>

<div class="container my-4">
    <h2 class="mb-4">Sales Register</h2>

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
                    <th>Bill No</th>
                    <th>Customer</th>
                    <th>Item</th>
                    <th>Qty</th>                    
                    <th>Weight</th>
                    <th>Rate</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // ✅ Sales Register Query
            $sql = "
                SELECT 
                    s.id,
                    s.invoice_number,
                    s.date,
                    c.name AS customer,
                    i.name AS item,
                    sd.weight,
                    sd.rate,
                    sd.quantity,

                    s.total_amount
                FROM sales s
                INNER JOIN accounts c 
                    ON s.customer_id = c.id
                INNER JOIN sale_details sd 
                    ON s.id = sd.sale_id
                INNER JOIN items i 
                    ON sd.item_id = i.id
                WHERE s.date BETWEEN ? AND ?
                ORDER BY s.date ASC, s.id ASC
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
                            <td>" . htmlspecialchars($row['date']) . "</td>
                            <td>" . htmlspecialchars($row['invoice_number']) . "</td>
                            <td>" . htmlspecialchars($row['customer']) . "</td>
                            <td>" . number_format($row['quantity'], 2) . "</td>
                            <td>" . htmlspecialchars($row['item']) . "</td>
                            <td>" . number_format($row['weight'], 2) . "</td>                            
                            <td>" . number_format($row['rate'], 2) . "</td>
                            <td>" . number_format($row['total_amount'], 2) . "</td>
                        </tr>";
                        $total += $row['total_amount'];
                        $i++;
                    }
                } else {
                    echo "<tr><td colspan='10' class='text-center text-muted'>
                            No sales found in this date range
                          </td></tr>";
                }
            }
            ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="8" class="text-end">Total</td>
                    <td><?= number_format($total ?? 0, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
