<?php
// supplier_payment.php - Supplier Payment Entry
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';
$modal_message = '';
$modal_type = ''; // 'success' or 'error'

// Fetch suppliers
$stmt = $conn->prepare("SELECT id, name FROM accounts WHERE type='supplier' ORDER BY name ASC");
$stmt->execute();
$suppliers = $stmt->get_result();
$stmt->close();

$payment = [];
if ($action === 'edit') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        $modal_message = "Invalid Payment ID";
        $modal_type = 'error';
    } else {
        $stmt = $conn->prepare("SELECT * FROM supplier_payments WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$payment) {
            $modal_message = "Payment not found";
            $modal_type = 'error';
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = intval($_POST['supplier_id']);
    $purchase_id = !empty($_POST['purchase_id']) ? intval($_POST['purchase_id']) : null;
    $payment_date = $_POST['payment_date'];
    $amount = floatval($_POST['amount']);
    $description = $_POST['description'];

    if (!$supplier_id || !$payment_date || $amount <= 0) {
        $modal_message = "Error: Supplier, Payment Date, and valid Amount are required.";
        $modal_type = 'error';
    } else {
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO supplier_payments (supplier_id, purchase_id, payment_date, amount, description) 
                                    VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisds", $supplier_id, $purchase_id, $payment_date, $amount, $description);
            if ($stmt->execute()) {
                $modal_message = "Payment added successfully!";
                $modal_type = 'success';
            } else {
                error_log("Payment Insert Failed: " . $conn->error);
                $modal_message = "Failed to add payment. Check logs.";
                $modal_type = 'error';
            }
            $stmt->close();
        } elseif ($action === 'edit') {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("UPDATE supplier_payments SET supplier_id = ?, purchase_id = ?, payment_date = ?, amount = ?, description = ? WHERE id = ?");
            $stmt->bind_param("iisdsi", $supplier_id, $purchase_id, $payment_date, $amount, $description, $id);
            if ($stmt->execute()) {
                $modal_message = "Payment updated successfully!";
                $modal_type = 'success';
            } else {
                error_log("Payment Update Failed: " . $conn->error);
                $modal_message = "Failed to update payment. Check logs.";
                $modal_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Pagination for payment list
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM supplier_payments");
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$total_pages = ceil($total_rows / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Payment Entry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #cancel{
            margin-left: 80%;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold">Supplier Payment Entry</h2>
        <a href="supplier_payment.php?action=add" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Add New Payment
        </a>
    </div>

    <?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="card shadow-sm p-4 rounded-3">
        <form method="POST" class="row g-3" action="supplier_payment.php?action=<?= $action ?>&id=<?= $action === 'edit' ? $id : '' ?>">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Supplier</label>
                <select name="supplier_id" id="supplier_id" class="form-select" required>
                    <option value="">Select Supplier</option>
                    <?php 
                    $suppliers->data_seek(0); 
                    while ($row = $suppliers->fetch_assoc()) { 
                        $selected = ($action === 'edit' && $row['id'] == $payment['supplier_id']) ? 'selected' : '';
                        echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                    } 
                    ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Linked Purchase (Optional)</label>
                <select name="purchase_id" id="purchase_id" class="form-select">
                    <option value="">None</option>
                    <?php if ($action === 'edit' && $payment['purchase_id']) {
                        $stmt = $conn->prepare("SELECT id, invoice_number FROM purchases WHERE id = ?");
                        $stmt->bind_param("i", $payment['purchase_id']);
                        $stmt->execute();
                        $purchase = $stmt->get_result()->fetch_assoc();
                        echo "<option value='{$purchase['id']}' selected>#{$purchase['invoice_number']}</option>";
                        $stmt->close();
                    } ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Payment Date</label>
                <input type="date" name="payment_date" class="form-control" required value="<?= $action === 'edit' ? $payment['payment_date'] : date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Amount</label>
                <input type="number" step="0.01" name="amount" class="form-control" placeholder="Enter amount" required value="<?= $action === 'edit' ? $payment['amount'] : '' ?>">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Optional notes..."><?= $action === 'edit' ? htmlspecialchars($payment['description']) : '' ?></textarea>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-success me-2">
                    <i class="bi bi-save"></i> <?= $action === 'edit' ? 'Update' : 'Save' ?> Payment
                </button>
                <a href="supplier_payment.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <?php else: ?>
    <div class="card shadow-sm rounded-3">
        <div class="card-header bg-success text-white fw-bold">
            Supplier Ledger
            <a href="index.php" id="cancel" class="btn btn-danger"> ❌close </a>
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
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT sp.*, a.name as supplier 
                                            FROM supplier_payments sp 
                                            JOIN accounts a ON sp.supplier_id = a.id 
                                            ORDER BY sp.id DESC 
                                            LIMIT ? OFFSET ?");
                    $stmt->bind_param("ii", $limit, $offset);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $purchase_info = $row['purchase_id'] ? "#{$row['purchase_id']}" : '-';
                            echo "<tr>
                                <td>{$row['id']}</td>
                                <td>" . htmlspecialchars($row['supplier']) . "</td>
                                <td>$purchase_info</td>
                                <td>{$row['payment_date']}</td>
                                <td class='fw-bold'>₹{$row['amount']}</td>
                                <td>" . htmlspecialchars($row['description']) . "</td>
                                <td>
                                    <a href='supplier_payment.php?action=edit&id={$row['id']}&page=$page' class='btn btn-sm btn-outline-secondary'>✏ Edit</a>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center text-muted'>No payments found</td></tr>";
                    }
                    $stmt->close();
                    ?>
                </tbody>
            </table>
        </div>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mt-3">
                <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php } ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Success</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="successMessage"></p>
            </div>
            <div class="modal-footer">
                <a href="supplier_payment.php?page=<?= $page ?>" class="btn btn-primary">Back to Payments</a>
            </div>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Error</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="errorMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Show modals if message is set
    <?php if ($modal_message && $modal_type === 'success') { ?>
        $('#successMessage').text('<?= addslashes($modal_message) ?>');
        $('#successModal').modal('show');
    <?php } elseif ($modal_message && $modal_type === 'error') { ?>
        $('#errorMessage').text('<?= addslashes($modal_message) ?>');
        $('#errorModal').modal('show');
    <?php } ?>

    $('#supplier_id').change(function() {
        var supplier_id = $(this).val();
        $.ajax({
            url: 'get_purchases.php',
            method: 'GET',
            data: { supplier_id: supplier_id },
            success: function(data) {
                $('#purchase_id').html('<option value="">None</option>' + data);
            }
        });
    });

    <?php if ($action === 'edit' && $payment['supplier_id']) { ?>
        // Trigger change to populate purchases for edit mode
        $('#supplier_id').trigger('change');
    <?php } ?>
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>