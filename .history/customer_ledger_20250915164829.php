<?php
// customer_ledger.php - Single File Version with Modal + Ledger Details
include 'db.php';
include 'header.php';

// Fetch customers with balances
$sql = "SELECT a.id, a.name, 
        IFNULL(SUM(CASE WHEN l.type='debit' THEN l.amount ELSE 0 END),0) as total_debit,
        IFNULL(SUM(CASE WHEN l.type='credit' THEN l.amount ELSE 0 END),0) as total_credit
        FROM accounts a
        LEFT JOIN ledger l ON a.id = l.account_id
        WHERE a.type = 'customer'
        GROUP BY a.id, a.name";
$result = $conn->query($sql);
if (!$result) {
    die("SQL Error: " . $conn->error);
}

// If this is an AJAX request for details, return only the ledger table
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $customer_id = intval($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT type, amount, date, description FROM ledger WHERE account_id = ? ORDER BY date ASC");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $total_debit = $total_credit = 0;
    ob_start();
    ?>
    <table class="table table-bordered ledger-modal-table">
        <thead class="table-secondary">
            <tr>
                <th colspan="3">Debit</th>
                <th colspan="3">Credit</th>
            </tr>
            <tr>
                <th>Amount</th><th>Date</th><th>Description</th>
                <th>Amount</th><th>Date</th><th>Description</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $res->fetch_assoc()):
                if ($row['type'] === 'debit') {
                    echo "<tr>
                            <td>{$row['amount']}</td>
                            <td>{$row['date']}</td>
                            <td>{$row['description']}</td>
                            <td></td><td></td><td></td>
                          </tr>";
                    $total_debit += $row['amount'];
                } else {
                    echo "<tr>
                            <td></td><td></td><td></td>
                            <td>{$row['amount']}</td>
                            <td>{$row['date']}</td>
                            <td>{$row['description']}</td>
                          </tr>";
                    $total_credit += $row['amount'];
                }
            endwhile; ?>
        </tbody>
        <tfoot class="fw-bold">
            <tr>
                <td colspan="3">Total Debit: <?= number_format($total_debit, 2) ?></td>
                <td colspan="3">Total Credit: <?= number_format($total_credit, 2) ?></td>
            </tr>
            <tr>
                <td colspan="6" class="text-end">Balance: <?= number_format($total_debit - $total_credit, 2) ?></td>
            </tr>
        </tfoot>
    </table>
    <?php
    echo ob_get_clean();
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Customer Ledger</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <style>
        body { background: #f9f9f9; }
        .ledger-table { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .modal-header { background: #6f42c1; color: white; }
        .modal-footer { background: #f1f1f1; }
        .ledger-modal-table th, .ledger-modal-table td { text-align: center; vertical-align: middle; }
        .balance-positive { color: green; font-weight: bold; }
        .balance-negative { color: red; font-weight: bold; }
        .close-btn { background: #dc3545; border: none; color: white; border-radius: 50%; width: 35px; height: 35px; font-weight: bold; }
        .close-btn:hover { background: #bb2d3b; }
    </style>
</head>
<body class="p-4">
    <h2 class="mb-4">Customer Ledger</h2>

    <table class="table table-striped table-hover ledger-table">
        <thead class="table-dark">
            <tr>
                <th>Customer Name</th>
                <th>Balance</th>
                <th>View</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()):
                $balance = $row['total_debit'] - $row['total_credit']; ?>
                <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td class="<?= $balance >= 0 ? 'balance-positive':'balance-negative' ?>">
                        <?= number_format($balance, 2) ?>
                    </td>
                    <td>
                        <button class="btn btn-primary btn-sm view-ledger" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['name']) ?>">
                            View
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Modal -->
    <div class="modal fade" id="ledgerModal" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Ledger Details</h5>
            <button type="button" class="close-btn" data-bs-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body" id="ledgerContent">
            <p class="text-center">Loading...</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
    document.querySelectorAll('.view-ledger').forEach(btn => {
        btn.addEventListener('click', function() {
            const customerId = this.dataset.id;
            const customerName = this.dataset.name;
            document.querySelector('#ledgerModal .modal-title').innerText = "Ledger: " + customerName;

            fetch("customer_ledger.php?ajax=1&id=" + customerId)
                .then(res => res.text())
                .then(html => document.getElementById("ledgerContent").innerHTML = html);

            new bootstrap.Modal(document.getElementById('ledgerModal')).show();
        });
    });
    </script>
</body>
</html>
