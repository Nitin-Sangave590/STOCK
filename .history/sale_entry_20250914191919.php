<?php
// sale_entry.php - Sale Entry
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// ✅ Fetch customers safely
$stmt = $conn->prepare("SELECT id, name FROM accounts WHERE type = ? ORDER BY name ASC");
$type = 'customer';
$stmt->bind_param("s", $type);
$stmt->execute();
$customers = $stmt->get_result();
$stmt->close();

// ✅ Fetch items safely with sale_rate and stock
$stmt = $conn->prepare("SELECT id, name, sale_rate, stock FROM items ORDER BY name ASC");
$stmt->execute();
$items = $stmt->get_result();
$stmt->close();

// ✅ Handle form submission
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = intval($_POST['customer_id']);
    $invoice_number = trim($_POST['invoice_number']);
    $sale_date = $_POST['sale_date'];
    $hamali = floatval($_POST['hamali'] ?? 0);
    $freight = floatval($_POST['freight'] ?? 0);
    $total_amount = 0;

    // ✅ Basic validation
    if (!$customer_id || !$invoice_number || !$sale_date) {
        die("Error: Customer, Invoice Number, and Sale Date are required.");
    }

    // ✅ Insert sale entry
    $stmt = $conn->prepare("INSERT INTO sales (customer_id, invoice_number, sale_date, hamali, freight, total_amount) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issddd", $customer_id, $invoice_number, $sale_date, $hamali, $freight, $total_amount);
    if (!$stmt->execute()) {
        error_log("Sale Insert Failed: " . $conn->error);
        die("Failed to insert sale. Check logs.");
    }
    $sale_id = $conn->insert_id;
    $stmt->close();

    // ✅ Insert sale details
    if (!empty($_POST['item_id'])) {
        foreach ($_POST['item_id'] as $index => $item_id) {
            $item_id = intval($item_id);
            $quantity = floatval($_POST['quantity'][$index] ?? 0);
            $weight = floatval($_POST['weight'][$index] ?? 0);
            $rate = floatval($_POST['rate'][$index] ?? 0);
            $total = $weight * $rate;
            $total_amount += $total;

            // Insert details
            $stmt = $conn->prepare("INSERT INTO sale_details (sale_id, item_id, quantity, weight, rate, total) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iidddd", $sale_id, $item_id, $quantity, $weight, $rate, $total);
            if (!$stmt->execute()) {
                error_log("Sale Detail Insert Failed: " . $conn->error);
                die("Failed to insert sale details.");
            }
            $stmt->close();

            // Update stock
            $stmt = $conn->prepare("UPDATE items SET stock = stock - ? WHERE id = ?");
            $stmt->bind_param("di", $quantity, $item_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // ✅ Update net total
    $net_total = $total_amount + $hamali + $freight;
    $stmt = $conn->prepare("UPDATE sales SET total_amount = ? WHERE id = ?");
    $stmt->bind_param("di", $net_total, $sale_id);
    $stmt->execute();
    $stmt->close();

    header("Location: sale_entry.php?success=1&page=$page");
    exit;
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold">Sale Management</h2>
        <button class="btn btn-primary" id="toggleForm">➕ New Sale Entry</button>
    </div>

    <!-- ✅ Sale Entry Form -->
    <div id="saleFormContainer" class="card p-4 shadow mb-4" style="display:none;">
        <form method="POST" id="saleForm" action="sale_entry.php?action=add">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">Select Customer</option>
                        <?php while ($row = $customers->fetch_assoc()) { ?>
                            <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Invoice Number</label>
                    <input type="text" name="invoice_number" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sale Date</label>
                    <input type="date" name="sale_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <h5 class="fw-bold">Products</h5>
            <table class="table table-bordered table-sm" id="productTable">
                <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Weight</th>
                        <th>Rate</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <select name="item_id[]" class="form-select item-select" required>
                                <?php $items->data_seek(0); while ($row = $items->fetch_assoc()) { ?>
                                    <option value="<?= $row['id'] ?>" data-rate="<?= $row['sale_rate'] ?>" data-stock="<?= $row['stock'] ?>">
                                        <?= htmlspecialchars($row['name']) ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </td>
                        <td><input type="number" name="quantity[]" class="form-control qty" min="0" required></td>
                        <td><input type="number" step="0.01" name="weight[]" class="form-control weight" required></td>
                        <td><input type="number" step="0.01" name="rate[]" class="form-control rate" required></td>
                        <td><input type="number" step="0.01" class="form-control total" readonly></td>
                        <td><button type="button" class="btn btn-danger btn-sm remove-row">❌</button></td>
                    </tr>
                </tbody>
            </table>
            <button type="button" id="addRow" class="btn btn-outline-secondary mb-3">➕ Add Product</button>

            <!-- ✅ Expenses -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Hamali</label>
                    <input type="number" step="0.01" name="hamali" id="hamali" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Freight</label>
                    <input type="number" step="0.01" name="freight" id="freight" class="form-control" value="0">
                </div>
            </div>

            <!-- ✅ Grand Total -->
            <div class="mb-3">
                <label class="form-label">Net Total</label>
                <input type="number" step="0.01" id="grandTotal" class="form-control fw-bold" readonly>
            </div>

            <button type="submit" class="btn btn-success">💾 Save Sale</button>
            <button type="button" class="btn btn-secondary" id="closeForm">Cancel</button>
            <div class="form-check mt-3">
                <input type="checkbox" class="form-check-input" id="printCheckbox">
                <label class="form-check-label" for="printCheckbox">Print Bill</label>
            </div>
        </form>
    </div>

    <!-- ✅ Sales List -->
    <div class="card shadow p-3">
        <h4 class="fw-bold mb-3">📋 Sale List</h4>
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Invoice</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Print</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $conn->prepare("SELECT s.*, a.name AS customer FROM sales s JOIN accounts a ON s.customer_id = a.id ORDER BY s.id DESC LIMIT ? OFFSET ?");
                $stmt->bind_param("ii", $limit, $offset);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                        <td>{$row['id']}</td>
                        <td>" . htmlspecialchars($row['customer']) . "</td>
                        <td>" . htmlspecialchars($row['invoice_number']) . "</td>
                        <td>{$row['sale_date']}</td>
                        <td><strong>{$row['total_amount']}</strong></td>
                        <td><a href='print_sale.php?id={$row['id']}' target='_blank' class='btn btn-sm btn-outline-primary'>🖨 Print</a></td>
                    </tr>";
                }
                $stmt->close();

                // Pagination
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM sales");
                $stmt->execute();
                $total_rows = $stmt->get_result()->fetch_assoc()['total'];
                $stmt->close();
                $total_pages = ceil($total_rows / $limit);
                ?>
            </tbody>
        </table>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php } ?>
            </ul>
        </nav>
    </div>

    <!-- ✅ Item Stock Summary -->
    <div class="card shadow p-3 mt-4">
        <h4 class="fw-bold mb-3">📊 Item Stock Summary</h4>
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Item</th>
                    <th>Total Purchased</th>
                    <th>Total Weight</th>
                    <th>Total Sold</th>
                    <th>Available Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $conn->prepare("
                    SELECT 
                        i.id, 
                        i.name, 
                        COALESCE((SELECT SUM(quantity) FROM purchase_details WHERE item_id = i.id), 0) AS total_purchased,
                        COALESCE((SELECT SUM(quantity) FROM sale_details WHERE item_id = i.id), 0) AS total_sold,
                        i.stock AS available
                    FROM items i
                    ORDER BY i.name ASC
                ");
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $available = $row['available']; // Or compute $row['total_purchased'] - $row['total_sold']
                    echo "<tr>
                        <td>" . htmlspecialchars($row['name']) . "</td>
                        <td>{$row['total_purchased']}</td>
                        
                        <td>{$row['total_sold']}</td>
                        <td><strong>{$available}</strong></td>
                    </tr>";
                }
                $stmt->close();
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ✅ Print Preview -->
<div class="print-area" id="printArea" style="display:none;">
    <h2>Sale Bill</h2>
    <p><strong>Customer:</strong> <span id="printCustomer"></span></p>
    <p><strong>Invoice Number:</strong> <span id="printInvoice"></span></p>
    <p><strong>Date:</strong> <span id="printDate"></span></p>
    <p><strong>Hamali:</strong> <span id="printHamali"></span></p>
    <p><strong>Freight:</strong> <span id="printFreight"></span></p>
    <table border="1" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Weight</th>
                <th>Rate</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody id="printTableBody"></tbody>
    </table>
    <p><strong>Net Total:</strong> <span id="printGrandTotal"></span></p>
</div>

<script>
$(document).ready(function() {
    $("#toggleForm").click(() => $("#saleFormContainer").slideDown());
    $("#closeForm").click(() => $("#saleFormContainer").slideUp());

    $(document).on('change', '.item-select', function() {
        let rate = $(this).find('option:selected').data('rate');
        $(this).closest('tr').find('.rate').val(rate);
        calculateTotals();
    });

    $(document).on('input', '.qty', function() {
        let qty = parseFloat($(this).val()) || 0;
        let stock = parseFloat($(this).closest('tr').find('.item-select option:selected').data('stock')) || 0;
        if (qty > stock) {
            alert("Insufficient stock! Available: " + stock);
            $(this).val(stock);
        }
        calculateTotals();
    });

    $(document).on('input', '.weight, .rate, #hamali, #freight', calculateTotals);

    $('#addRow').click(function() {
        let row = $('#productTable tbody tr:first').clone();
        row.find('input').val('');
        $('#productTable tbody').append(row);
    });

    $(document).on('click', '.remove-row', function() {
        if ($('#productTable tbody tr').length > 1) {
            $(this).closest('tr').remove();
            calculateTotals();
        }
    });

    function calculateTotals() {
        let grandTotal = 0;
        $('#productTable tbody tr').each(function() {
            let weight = parseFloat($(this).find('.weight').val()) || 0;
            let rate = parseFloat($(this).find('.rate').val()) || 0;
            let rowTotal = weight * rate;
            $(this).find('.total').val(rowTotal.toFixed(2));
            grandTotal += rowTotal;
        });
        let hamali = parseFloat($('#hamali').val()) || 0;
        let freight = parseFloat($('#freight').val()) || 0;
        $('#grandTotal').val((grandTotal + hamali + freight).toFixed(2));
    }

    $('#saleForm').submit(function(e) {
        if ($('#printCheckbox').is(':checked')) {
            e.preventDefault();
            // Fill Print Area
            $('#printCustomer').text($('select[name="customer_id"] option:selected').text());
            $('#printInvoice').text($('input[name="invoice_number"]').val());
            $('#printDate').text($('input[name="sale_date"]').val());
            $('#printHamali').text($('#hamali').val() || '0.00');
            $('#printFreight').text($('#freight').val() || '0.00');
            $('#printGrandTotal').text($('#grandTotal').val());

            let tableBody = '';
            $('#productTable tbody tr').each(function() {
                tableBody += '<tr>' +
                    '<td>' + $(this).find('.item-select option:selected').text() + '</td>' +
                    '<td>' + $(this).find('.qty').val() + '</td>' +
                    '<td>' + ($(this).find('.weight').val() || '0.00') + '</td>' +
                    '<td>' + $(this).find('.rate').val() + '</td>' +
                    '<td>' + $(this).find('.total').val() + '</td>' +
                '</tr>';
            });
            $('#printTableBody').html(tableBody);

            $('#printArea').show();
            window.print();
            $('#printArea').hide();
            $(this).unbind('submit').submit();
        }
    });
});
</script>

<?php include 'footer.php'; ?>