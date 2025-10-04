<?php
include 'db.php';

// ---------- helper: find which column exists ----------
function findColumn($conn, $table, $candidates) {
    foreach ($candidates as $c) {
        $col = $conn->real_escape_string($c);
        $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
        if ($res && $res->num_rows > 0) return $c;
    }
    return null;
}

// ---------- AJAX: return ledger table only ----------
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $customer_id = intval($_GET['id'] ?? 0);
    $from = $_GET['from'] ?? '2000-01-01';
    $to   = $_GET['to'] ?? date('Y-m-d');

    $sales_date_col = findColumn($conn, 'sales', ['date','sale_date','created_at','invoice_date']);
    if (!$sales_date_col) die("No date-like column found in sales table.");

    $receipts_table = 'customer_receipts';
    $tblExists = $conn->query("SHOW TABLES LIKE '{$receipts_table}'");
    if (!$tblExists || $tblExists->num_rows == 0) {
        die("Receipts table '{$receipts_table}' not found.");
    }

    $receipts_date_col = findColumn($conn, $receipts_table, ['date','receipt_date','created_at','payment_date']);
    if (!$receipts_date_col) die("No date-like column found in {$receipts_table}.");

    $receipts_desc_col = findColumn($conn, $receipts_table, ['description','desc','narration','remarks','note']);
    if (!$receipts_desc_col) $receipts_desc_col = null;

    // Prepare & fetch sales
    $sales_sql = "SELECT invoice_number, bill_no, total_amount AS amount, `{$sales_date_col}` AS date, hamali, freight
                  FROM sales
                  WHERE customer_id = ? AND `{$sales_date_col}` BETWEEN ? AND ?
                  ORDER BY `{$sales_date_col}` ASC";
    $stmt1 = $conn->prepare($sales_sql);
    if (!$stmt1) die("SQL Prepare Error (Sales): " . $conn->error);
    $stmt1->bind_param("iss", $customer_id, $from, $to);
    $stmt1->execute();
    $sales = $stmt1->get_result();

    // Prepare & fetch receipts
    $desc_sel = $receipts_desc_col ? "`{$receipts_desc_col}` AS description" : "'' AS description";
    $receipts_sql = "SELECT amount, `{$receipts_date_col}` AS date, {$desc_sel}
                     FROM `{$receipts_table}`
                     WHERE customer_id = ? AND `{$receipts_date_col}` BETWEEN ? AND ?
                     ORDER BY `{$receipts_date_col}` ASC";
    $stmt2 = $conn->prepare($receipts_sql);
    if (!$stmt2) die("SQL Prepare Error (Receipts): " . $conn->error);
    $stmt2->bind_param("iss", $customer_id, $from, $to);
    $stmt2->execute();
    $receipts = $stmt2->get_result();

    // Merge and output
    $entries = [];
    while ($r = $sales->fetch_assoc()) {
        $entries[] = [
            'type' => 'debit',
            'date' => $r['date'],
            'bill_no' => $r['bill_no'] ?? '',
            'invoice' => $r['invoice_number'] ?? '',
            'amount' => floatval($r['amount']),
            'desc' => '',
            'extra' => ['hamali' => $r['hamali'] ?? 0, 'freight' => $r['freight'] ?? 0]
        ];
    }
    while ($r = $receipts->fetch_assoc()) {
        $entries[] = [
            'type' => 'credit',
            'date' => $r['date'],
            'bill_no' => '',
            'invoice' => '',
            'amount' => floatval($r['amount']),
            'desc' => $r['description'] ?? ''
        ];
    }

    usort($entries, function($a, $b){
        return strtotime($a['date']) <=> strtotime($b['date']);
    });

    // Output HTML only (no header/footer)
    $total_debit = 0.0;
    $total_credit = 0.0;
    ob_start();
    ?>
    <div style="padding:8px;">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div><strong>Period:</strong> <?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?></div>
        <div><button class="btn btn-sm btn-secondary" onclick="document.getElementById('ledgerModal').querySelector('.btn-close').click();">Close</button></div>
      </div>

      <table class="table table-bordered" style="background:white;">
        <thead class="table-light text-center">
          <tr>
            <th colspan="4">Debit (Bills)</th>
            <th colspan="3">Credit (Receipts)</th>
          </tr>
          <tr class="text-center">
            <th>Date</th><th>Invoice</th><th>Amount</th>
            <th>Date</th><th>Description</th><th>Amount</th>
          </tr>
        </thead>
        <tbody>
        <?php if (count($entries) === 0): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No transactions in this period.</td></tr>
        <?php else: ?>
            <?php foreach ($entries as $e): ?>
                <?php if ($e['type'] === 'debit'): $total_debit += $e['amount']; ?>
                    <tr>
                        <td class="text-center"><?= htmlspecialchars($e['date']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($e['invoice']) ?></td>
                        <td class="text-end"><?= number_format($e['amount'],2) ?></td>
                        <td></td><td></td><td></td>
                    </tr>
                <?php else: $total_credit += $e['amount']; ?>
                    <tr>
                        <td></td><td></td><td></td>
                        <td class="text-center"><?= htmlspecialchars($e['date']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($e['desc']) ?></td>
                        <td class="text-end"><?= number_format($e['amount'],2) ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
          <tr style="background:#f7f7f9;">
            <td colspan="3"><strong>Total Debit:</strong> <?= number_format($total_debit,2) ?></td>
            <td colspan="2" class="text-end"><strong>Total Credit:</strong></td>
            <td class="text-end"><strong><?= number_format($total_credit,2) ?></strong></td>
          </tr>
          <tr>
            <td colspan="6" class="text-end"><strong>Closing Balance: <?= number_format($total_debit - $total_credit,2) ?></strong></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php
    echo ob_get_clean();
    exit;
}

// ---------- NORMAL PAGE: include header & list customers ----------
include 'header.php';
$customers = $conn->query("SELECT id, name FROM accounts WHERE type='customer' ORDER BY name ASC");
if (!$customers) die("SQL Error fetching customers: " . $conn->error);

// (rest of HTML output remains same)
?>
<!doctype html>
<html>
<!-- ... your existing normal page HTML ... -->
