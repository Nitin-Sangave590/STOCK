<?php
// customer_receipt.php - Customer Receipt Entry
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';

// Fetch customers
$stmt = $conn->prepare("SELECT id, name FROM accounts WHERE type='customer' ORDER BY name ASC");
$stmt->execute();
$customers = $stmt->get_result();

// Handle form submission
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = $_POST['customer_id'];
    $sale_id = !empty($_POST['sale_id']) ? $_POST['sale_id'] : null;
    $receipt_date = $_POST['receipt_date'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];

    $sql = "INSERT INTO customer_receipts (customer_id, sale_id, receipt_date, amount, description)
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisss", $customer_id, $sale_id, $receipt_date, $amount, $description);
    $stmt->execute();

    header("Location: customer_receipt.php");
    exit;
}
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold">Customer Receipt Entry</h2>
        <a href="customer_receipt.php?action=add" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add New Receipt
        </a>
    </div>

    <?php if ($action === 'add'): ?>
    <div class="card shadow-sm p-4 rounded-3">
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Customer</label>
                <select name="customer_id" id="customer_id" class="form-select" required>
                    <?php 
                    $customers->data_seek(0); 
                    while ($row = $customers->fetch_assoc()) { 
                        echo "<option value='{$row['id']}'>{$row['name']}</option>";
                    } 
                    ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Linked Sale (Optional)</label>
                <select name="sale_id" id="sale_id" class="form-select">
                    <option value="">None</option>
                    <!-- Will be dynamically populated via JS -->
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Receipt Date</label>
                <input type="date" name="receipt_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
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
                    <i class="bi bi-save"></i> Save Receipt
                </button>
                <a href="customer_receipt.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
    $(document).ready(function() {
        $('#customer_id').change(function() {
            var customer_id = $(this).val();
            $.ajax({
                url: 'get_sales.php',
                method: 'GET',
                data: { customer_id: customer_id },
                success: function(data) {
                    $('#sale_id').html(data);
                }
            });
        });
    });
    </script>

    <?php else: ?>
        <style>
            #cancel{
                margin-left: 90%;
                margin-top: -4%;
            }
        </style>
    <div class="card shadow-sm rounded-3">
        <div class="card-header bg-success text-white fw-bold">
            Existing Receipts
            <a href="index.php" id="cancel" class="btn btn-danger"> ❌close </a>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Sale ID</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT cr.*, a.name as customer FROM customer_receipts cr JOIN accounts a ON cr.customer_id = a.id ORDER BY cr.id DESC");
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>
                                <td>{$row['id']}</td>
                                <td>{$row['customer']}</td>
                                <td>" . ($row['sale_id'] ?? '-') . "</td>
                                <td>{$row['receipt_date']}</td>
                                <td class='fw-bold'>₹{$row['amount']}</td>
                                <td>{$row['description']}</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center text-muted'>No receipts found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
