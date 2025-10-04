<?php
// sale_entry.php - Sale Bill Entry
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';

// Fetch customers & items using prepared statements
$stmt = $conn->prepare("SELECT id, name FROM accounts WHERE type = ? ORDER BY name ASC");
$type = 'customer';
$stmt->bind_param("s", $type);
$stmt->execute();
$customers = $stmt->get_result();
$stmt->close();

$items = $conn->query("SELECT id, name, sale_rate FROM items ORDER BY name ASC");

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = intval($_POST['customer_id']);
    $invoice_number = trim($_POST['invoice_number']);
    $sale_date = $_POST['sale_date'];
    $total_amount = 0;

    // Insert into sales table
    $stmt = $conn->prepare("INSERT INTO sales (customer_id, invoice_number, sale_date, total_amount) VALUES (?, ?, ?, 0)");
    $stmt->bind_param("isset", $customer_id, $invoice_number, $sale_date);
    $stmt->execute();
    $sale_id = $stmt->insert_id;
    $stmt->close();

    // Insert sale details
    $stmt = $conn->prepare("INSERT INTO sale_details (sale_id, item_id, quantity, weight, rate, total) VALUES (?, ?, ?, ?, ?, ?)");
    for ($i = 0; $i < count($_POST['item_id']); $i++) {
        $item_id = intval($_POST['item_id'][$i]);
        $quantity = floatval($_POST['quantity'][$i]);
        $weight = floatval($_POST['weight'][$i] ?? 0);
        $rate = floatval($_POST['rate'][$i]);
        $total = $quantity * $rate;
        $total_amount += $total;
        $stmt->bind_param("iiiddd", $sale_id, $item_id, $quantity, $weight, $rate, $total);
        $stmt->execute();
    }
    $stmt->close();

    // Update total amount in sales table
    $stmt = $conn->prepare("UPDATE sales SET total_amount = ? WHERE id = ?");
    $stmt->bind_param("di", $total_amount, $sale_id);
    $stmt->execute();
    $stmt->close();

    header("Location: sale_entry.php");
    exit;
}
?>

<div class="container mt-4">
    <h2 class="mb-4 text-primary">Sale Bill Entry</h2>

    <?php if ($action === 'add'): ?>
    <form method="POST" class="card shadow p-4" id="saleForm">
        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">Customer</label>
                <select name="customer_id" class="form-select" required>
                    <option value="">Select Customer</option>
                    <?php while ($row = $customers->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Invoice Number</label>
                <input type="text" name="invoice_number" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Sale Date</label>
                <input type="date" name="sale_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <h4 class="text-secondary mt-3">Products</h4>
        <table class="table table-bordered align-middle" id="productTable">
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
                            <option value="">Select Item</option>
                            <?php $items->data_seek(0); while ($row = $items->fetch_assoc()): ?>
                                <option value="<?= $row['id'] ?>" data-rate="<?= $row['sale_rate'] ?>">
                                    <?= htmlspecialchars($row['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </td>
                    <td><input type="number" min="0" step="1" name="quantity[]" class="form-control qty" required></td>
                    <td><input type="number" min="0" step="0.01" name="weight[]" class="form-control"></td>
                    <td><input type="number" min="0" step="0.01" name="rate[]" class="form-control rate" required></td>
                    <td><input type="number" step="0.01" class="form-control total" readonly></td>
                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm remove-row">×</button></td>
                </tr>
            </tbody>
        </table>

        <button type="button" id="addRow" class="btn btn-outline-primary btn-sm mb-3">+ Add Product</button>

        <div class="row">
            <div class="col-md-4 ms-auto">
                <label class="form-label fw-bold">Grand Total</label>
                <input type="number" step="0.01" id="grandTotal" class="form-control text-end bg-light" readonly>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">💾 Save Sale</button>
            <a href="sale_entry.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        function calculateRowTotal(row) {
            let qty = parseFloat(row.querySelector('.qty').value) || 0;
            let rate = parseFloat(row.querySelector('.rate').value) || 0;
            row.querySelector('.total').value = (qty * rate).toFixed(2);
        }

        function calculateGrandTotal() {
            let total = 0;
            document.querySelectorAll('.total').forEach(el => total += parseFloat(el.value) || 0);
            document.getElementById('grandTotal').value = total.toFixed(2);
        }

        document.getElementById('productTable').addEventListener('input', e => {
            if (e.target.classList.contains('qty') || e.target.classList.contains('rate')) {
                let row = e.target.closest('tr');
                calculateRowTotal(row);
                calculateGrandTotal();
            }
        });

        document.getElementById('productTable').addEventListener('change', e => {
            if (e.target.classList.contains('item-select')) {
                let rate = e.target.selectedOptions[0].dataset.rate;
                e.target.closest('tr').querySelector('.rate').value = rate;
                calculateRowTotal(e.target.closest('tr'));
                calculateGrandTotal();
            }
        });

        document.getElementById('addRow').addEventListener('click', () => {
            let tableBody = document.querySelector('#productTable tbody');
            let newRow = tableBody.querySelector('tr').cloneNode(true);
            newRow.querySelectorAll('input').forEach(input => input.value = '');
            tableBody.appendChild(newRow);
        });

        document.getElementById('productTable').addEventListener('click', e => {
            if (e.target.classList.contains('remove-row')) {
                let row = e.target.closest('tr');
                if (document.querySelectorAll('#productTable tbody tr').length > 1) {
                    row.remove();
                    calculateGrandTotal();
                }
            }
        });
    });
    </script>

    <?php else: ?>
    <div class="mb-3">
        <a href="sale_entry.php?action=add" class="btn btn-primary">➕ New Sale Entry</a>
    </div>
    <div class="card shadow">
        <div class="card-body">
            <h4 class="mb-3 text-secondary">Existing Sales</h4>
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Invoice</th>
                        <th>Date</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT s.*, a.name AS customer FROM sales s JOIN accounts a ON s.customer_id = a.id ORDER BY s.id DESC");
                    while ($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['customer']) ?></td>
                        <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                        <td><?= htmlspecialchars($row['sale_date']) ?></td>
                        <td class="text-end"><?= number_format($row['total_amount'], 2) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
