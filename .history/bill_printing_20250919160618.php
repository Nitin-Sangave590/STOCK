<?php
// bill_printing.php - Patti Printing with A5 Page Size Support
ob_start();
include 'db.php';
include 'header.php';

// ---------- INITIAL SETUP ----------
$modal_message = '';
$modal_type = ''; // 'success' or 'error'

// ---------- HELPER FUNCTION ----------
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// ---------- DYNAMIC COLUMN SELECTION ----------
$columns = ["p.id", "p.invoice_number", "p.date", "a.name AS customer"];
if (columnExists($conn, "sales", "weight")) $columns[] = "p.weight";
if (columnExists($conn, "sales", "rate")) $columns[] = "p.rate";
if (columnExists($conn, "sales", "total_amount")) {
    $columns[] = "p.total_amount";
} elseif (columnExists($conn, "sales", "net_amount")) {
    $columns[] = "p.net_amount AS total_amount";
}
if (columnExists($conn, "sales", "hamali")) $columns[] = "p.hamali";
if (columnExists($conn, "sales", "freight")) $columns[] = "p.freight";
if (columnExists($conn, "sales", "uchal")) $columns[] = "p.uchal";

// ---------- PAGINATION + DATE FILTER ----------
$limit = 10;
$page = max(1, intval($_REQUEST['page'] ?? 1));
$offset = ($page - 1) * $limit;

$start_date = $_POST['start_date'] ?? $_GET['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? $_GET['end_date'] ?? '';

$sql = "SELECT " . implode(", ", $columns) . "
        FROM sales p
        JOIN accounts a ON p.customer_id = a.id
        WHERE 1=1";
$params = [];
$types = '';

if (!empty($start_date)) {
    $sql .= " AND p.date >= ?";
    $params[] = $start_date;
    $types .= 's';
}
if (!empty($end_date)) {
    $sql .= " AND p.date <= ?";
    $params[] = $end_date;
    $types .= 's';
}
$sql .= " ORDER BY p.id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql) or die("SQL Error: " . $conn->error);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$pattis = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- PREVIEW + PRINT + WHATSAPP ----------
$preview_content = '';

function fetchBillDetails($conn, $ids) {
    if (empty($ids)) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql_details = "SELECT p.*, a.name AS customer, pd.quantity, pd.weight, pd.rate, pd.total, i.name AS item_name
                    FROM sales p
                    JOIN accounts a ON p.customer_id = a.id
                    LEFT JOIN purchase_details pd ON p.id = pd.purchase_id
                    LEFT JOIN items i ON pd.item_id = i.id
                    WHERE p.id IN ($placeholders)";
    $stmt = $conn->prepare($sql_details);
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $data;
}

// --- PREVIEW / PRINT ---
if (isset($_POST['preview']) || isset($_POST['print']) || isset($_POST['single_preview'])) {
    $selected_ids = [];
    if (isset($_POST['single_preview'])) {
        $selected_ids = [intval($_POST['single_preview'])];
    } elseif (!empty($_POST['selected_ids'])) {
        $selected_ids = array_map('intval', $_POST['selected_ids']);
    } elseif (isset($_POST['select_all'])) {
        $selected_ids = array_column($pattis, 'id');
    }

    if (!empty($selected_ids)) {
        $details = fetchBillDetails($conn, $selected_ids);

        foreach ($selected_ids as $id) {
            $purchase_details = array_values(array_filter($details, fn($d) => $d['id'] == $id));
            if (empty($purchase_details)) continue;

            $purchase = $purchase_details[0];
            $product_total = array_sum(array_column($purchase_details, 'total'));
            $total_expenses = ($purchase['hamali'] ?? 0) + ($purchase['freight'] ?? 0) + ($purchase['uchal'] ?? 0);
            $net_amount = $product_total - $total_expenses;

            $preview_content .= '<div class="print-container">';
            $preview_content .= '<div class="invoice-header">
                                    <h2>वैभव ट्रेडिंग कंपनी</h2>
                                    <p>Shop No 2, Karibasweshwar Complex, Main Road, Kasar Shirshi</p>
                                    <p>Mo.No: 8208893491</p>
                                    <p>Farmer Patti</p>
                                </div>';
            $preview_content .= '<div class="invoice-details">
                                    <p><strong>Farmer:</strong> ' . htmlspecialchars($purchase['customer']) . '</p>
                                    <p><strong>Bill No:</strong> ' . htmlspecialchars($purchase['invoice_number']) . '</p>
                                    <p><strong>Date:</strong> ' . date("d-m-Y", strtotime($purchase['date'])) . '</p>
                                </div>';
            $preview_content .= '<div class="table-container"><table>
                                    <thead><tr>
                                        <th>Product</th><th>Qty</th><th>Weight</th><th>Rate</th><th>Total</th>
                                    </tr></thead><tbody>';
            foreach ($purchase_details as $d) {
                $preview_content .= '<tr>
                    <td>' . htmlspecialchars($d['item_name'] ?? '-') . '</td>
                    <td>' . ($d['quantity'] ?? 0) . '</td>
                    <td>' . ($d['weight'] ?? 0) . '</td>
                    <td>' . number_format($d['rate'] ?? 0, 2) . '</td>
                    <td>' . number_format($d['total'] ?? 0, 2) . '</td>
                </tr>';
            }
            $preview_content .= '</tbody></table></div>';
            $preview_content .= '<div class="total-section">
                                    <p><strong>Grand Total:</strong> ₹' . number_format($product_total, 2) . '</p>
                                    <p><strong>Hamali:</strong> ₹' . number_format($purchase['hamali'] ?? 0, 2) . '</p>
                                    <p><strong>Motar Bhade:</strong> ₹' . number_format($purchase['freight'] ?? 0, 2) . '</p>
                                    <p><strong>Uchal:</strong> ₹' . number_format($purchase['uchal'] ?? 0, 2) . '</p>
                                    <p class="total-amount"><strong>Net Total:</strong> ₹' . number_format($net_amount, 2) . '</p>
                                </div>';
            $preview_content .= '</div>';
        }
    }
}

// --- WHATSAPP ---
if (isset($_POST['whatsapp'])) {
    $selected_ids = !empty($_POST['selected_ids']) ? array_map('intval', $_POST['selected_ids']) : array_column($pattis, 'id');
    if (!empty($selected_ids)) {
        $details = fetchBillDetails($conn, $selected_ids);
        $msg = "Selected Bill Details:\n";
        foreach ($selected_ids as $id) {
            $purchase_details = array_values(array_filter($details, fn($d) => $d['id'] == $id));
            if (empty($purchase_details)) continue;
            $purchase = $purchase_details[0];
            $product_total = array_sum(array_column($purchase_details, 'total'));
            $total_expenses = ($purchase['hamali'] ?? 0) + ($purchase['freight'] ?? 0) + ($purchase['uchal'] ?? 0);
            $net_amount = $product_total - $total_expenses;

            $msg .= "Bill No: {$purchase['invoice_number']}\nDate: " . date("d-m-Y", strtotime($purchase['date'])) . "\nFarmer: {$purchase['customer']}\n";
            foreach ($purchase_details as $d) {
                $msg .= "Product: {$d['item_name']} | Qty: {$d['quantity']} | Weight: {$d['weight']} | Rate: ₹" . number_format($d['rate'], 2) . " | Total: ₹" . number_format($d['total'], 2) . "\n";
            }
            $msg .= "Grand Total: ₹" . number_format($product_total, 2) . "\nTotal Expenses: ₹" . number_format($total_expenses, 2) . "\nNet Total: ₹" . number_format($net_amount, 2) . "\n----------------\n";
        }
        header("Location: https://wa.me/?text=" . urlencode($msg));
        exit;
    }
}

// ---------- PAGINATION ----------
$total_rows = $conn->query("SELECT COUNT(*) AS total FROM sales")->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bill Printing</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
.print-container { width:148mm; min-height:210mm; margin:0 auto; background:#fff; padding:10mm; border-radius:12px; box-shadow:0px 4px 12px rgba(0,0,0,0.1); page-break-after:always; }
.invoice-header { text-align:center; border-bottom:2px solid #ddd; margin-bottom:20px; }
.invoice-header h2 { margin:0; font-size:28px; }
table { width:100%; border-collapse:collapse; margin-top:10px; }
table th, table td { border:1px solid #ddd; padding:8px; text-align:center; }
.table th { background:#f1f1f1; }
.total-section { margin-top:20px; text-align:right; }
.total-amount { font-size:22px; font-weight:bold; color:#2c3e50; }
@media print { .no-print { display:none; } }
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<div class="container mt-4">
    <h2 class="fw-bold mb-3">Bill Printing</h2>

    <!-- Filter Form -->
    <form method="post" id="filterForm" class="mb-3 row g-2">
        <div class="col-md-4"><input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>"></div>
        <div class="col-md-4"><input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>"></div>
        <div class="col-md-4"><button type="submit" class="btn btn-info w-100">Filter</button></div>
    </form>

    <div class="mb-3 no-print">
        <button class="btn btn-primary" id="previewBtn">Preview Selected</button>
        <button class="btn btn-success" id="printBtn" disabled>Print Selected</button>
        <button class="btn btn-secondary" id="selectAllBtn">Select All</button>
        <button class="btn btn-warning" id="deselectAllBtn">Deselect All</button>
        <button class="btn btn-success" id="whatsappBtn"><i class="fab fa-whatsapp"></i> Send on WhatsApp</button>
    </div>

    <div class="card shadow p-3">
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th><input type="checkbox" id="selectAllCheckbox"></th>
                    <th>#</th>
                    <th>Bill No</th>
                    <th>Farmer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Preview/Print</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pattis as $row): ?>
                <tr>
                    <td><input type="checkbox" class="bill-checkbox" name="selected_ids[]" value="<?= $row['id'] ?>"></td>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                    <td><?= htmlspecialchars($row['customer']) ?></td>
                    <td><?= date("d-m-Y", strtotime($row['date'])) ?></td>
                    <td><strong><?= number_format($row['total_amount'] ?? 0, 2) ?></strong></td>
                    <td><button class="btn btn-sm btn-outline-primary single-preview-btn" data-id="<?= $row['id'] ?>">🖨 Preview</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <nav>
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>

    <?php if ($preview_content): ?>
        <div id="previewContent"><?= $preview_content ?></div>
        <script>
            <?php if (isset($_POST['print'])): ?>
                window.print();
                window.onafterprint = () => window.location.href = 'bill_printing.php?page=<?= $page ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>';
            <?php endif; ?>
        </script>
    <?php endif; ?>
</div>

<script>
$(function(){
    function updatePrintBtn(){ $('#printBtn').prop('disabled',$('.bill-checkbox:checked').length===0); }
    $('#selectAllCheckbox').change(function(){ $('.bill-checkbox').prop('checked',this.checked); updatePrintBtn(); });
    $('#selectAllBtn').click(()=>{$('#selectAllCheckbox').prop('checked',true).change();});
    $('#deselectAllBtn').click(()=>{$('#selectAllCheckbox').prop('checked',false).change();});
    $('.bill-checkbox').change(updatePrintBtn);
    $('#previewBtn').click(()=>submitSelected('preview'));
    $('#printBtn').click(()=>submitSelected('print'));
    $('#whatsappBtn').click(()=>submitSelected('whatsapp'));
    $('.single-preview-btn').click(function(){ submitForm([$(this).data('id')],'single_preview'); });

    function submitSelected(action){
        let ids=$('.bill-checkbox:checked').map((_,e)=>e.value).get();
        if(ids.length===0){alert('Please select at least one bill.'); return;}
        submitForm(ids,action);
    }
    function submitForm(ids,action){
        let form=$('<form>',{method:'POST',action:'bill_printing.php'});
        ids.forEach(id=>form.append($('<input>',{type:'hidden',name:'selected_ids[]',value:id})));
        form.append($('<input>',{type:'hidden',name:action,value:true}));
        form.append($('<input>',{type:'hidden',name:'start_date',value:$('[name="start_date"]').val()}));
        form.append($('<input>',{type:'hidden',name:'end_date',value:$('[name="end_date"]').val()}));
        form.append($('<input>',{type:'hidden',name:'page',value:'<?= $page ?>'}));
        $('body').append(form); form.submit();
    }
});
</script>
<?php include 'footer.php'; ?>
<?php ob_end_flush(); ?>
</body>
</html>
