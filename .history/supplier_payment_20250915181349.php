<?php
// supplier_payment.php - Supplier Payment Entry
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';

// Fetch suppliers
$stmt = $conn->prepare("SELECT id, name FROM accounts WHERE type='supplier' ORDER BY name ASC");
$stmt->execute();
$suppliers = $stmt->get_result();

// Handle form submission
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = $_POST['supplier_id'];
    $purchase_id = !empty($_POST['purchase_id']) ? $_POST['purchase_id'] : null;
    $payment_date = $_POST['payment_date'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];

    $sql = "INSERT INTO supplier_payments (supplier_id, purchase_id, payment_date, amount, description)
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisss", $supplier_id, $purchase_id, $payment_date, $amount, $description);
    $stmt->execute();

    header("Location: supplier_payment.php");
    exit;
}
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold">Supplier Payment Entry</h2>
        <a href="supplier_payment.php?action=add" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Add New Payment
        </a>
    </div>

    <?php if ($action === 'add'): ?>
    <div class="card shadow-sm p-4 rounded-3">
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Supplier</label>
                <select name="supplier_id" id="supplier_id" class="form-select" required>
                    <?php 
                    $suppliers->data_seek(0); 
                    while ($row = $suppliers->fetch_assoc()) { 
                        echo "<option value='{$row['id']}'>{$row['name']}</option>";
                    } 
                    ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Linked Purchase (Optional)</label>
                <select name="purchase_id" id="purchase_id" class="form-select">
                    <option value="">None</option>
                    <!-- Will be dynamically populated via JS -->
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Payment Date</label>
                <input type="date" name="payment_date" class="form-control" required value="<?= date('Y-m-d'); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Amount</label>
                <input type="number" step="0.01" name="amount" class="form-control" placeholder="Enter amount" required>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Optional notes..."></textarea>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-success me-2">
                    <i class="bi bi-save"></i> Save Payment
                </button>
                <a href="supplier_payment.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
    $(document).ready(function() {
        $('#supplier_id').change(function() {
            var supplier_id = $(this).val();
            $.ajax({
                url: 'get_purchases.php',
                method: 'GET',
                data: { supplier_id: supplier_id },
                success: function(data) {
                    $('#purchase_id').html(data);
                }
            });
        });
    });
    </script>

    <?php else: ?>
    <div class="card shadow-sm rounded-3">
        <div class="card-header bg-success text-white fw-bold">
            Existing Payments
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Supplier</th>
                        <th>Purchase ID</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT sp.*, a.name as supplier 
                                            FROM supplier_payments sp 
                                            JOIN accounts a ON sp.supplier_id = a.id 
                                            ORDER BY sp.id DESC");
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>
                                <td>{$row['id']}</td>
                                <td>{$row['supplier']}</td>
                                <td>" . ($row['purchase_id'] ?? '-') . "</td>
                                <td>{$row['payment_date']}</td>
                                <td class='fw-bold'>₹{$row['amount']}</td>
                                <td>{$row['description']}</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center text-muted'>No payments found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
