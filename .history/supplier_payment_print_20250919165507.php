<?php
// print_receipt.php - Generate and print supplier payment receipt
include 'db.php';
include 'header.php';

$payment_id = intval($_GET['id'] ?? 0);
$payment = [];
$modal_message = '';
$modal_type = '';

if ($payment_id) {
    $stmt = $conn->prepare("SELECT sp.*, a.name as supplier_name 
                           FROM supplier_payments sp 
                           JOIN accounts a ON sp.supplier_id = a.id 
                           WHERE sp.id = ?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$payment) {
        $modal_message = "Payment not found";
        $modal_type = 'error';
    }
} else {
    $modal_message = "Invalid Payment ID";
    $modal_type = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
            .receipt-container { width: 100%; margin: 0; }
            body { font-size: 12pt; }
        }
        .receipt-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background: #fff;
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .receipt-details dt {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .receipt-details dd {
            margin-bottom: 15px;
        }
        .company-logo {
            max-width: 100px;
            margin-bottom: 10px;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="container">
    <?php if ($payment): ?>
    <div class="receipt-container">
        <div class="receipt-header">
            <img src="logo.png" alt="Company Logo" class="company-logo" onerror="this.style.display='none'">
            <h2>Payment Receipt</h2>
            <p>Company Name</p>
            <p>123 Business Street, City, Country</p>
            <p>Contact: (123) 456-7890 | email@company.com</p>
        </div>

        <dl class="receipt-details">
            <dt>Receipt Number:</dt>
            <dd>PAY-<?php echo $payment['id']; ?></dd>
            
            <dt>Supplier:</dt>
            <dd><?php echo htmlspecialchars($payment['supplier_name']); ?></dd>
            
            <dt>Payment Date:</dt>
            <dd><?php echo $payment['payment_date']; ?></dd>
            
            <dt>Amount:</dt>
            <dd>₹<?php echo number_format($payment['amount'], 2); ?></dd>
            
            <?php if ($payment['purchase_id']): ?>
            <dt>Linked Purchase ID:</dt>
            <dd>#<?php echo $payment['purchase_id']; ?></dd>
            <?php endif; ?>
            
            <dt>Description:</dt>
            <dd><?php echo htmlspecialchars($payment['description'] ?: 'No description provided'); ?></dd>
        </dl>

        <div class="text-center mt-4">
            <p><strong>Thank you for your business!</strong></p>
            <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>

    <div class="text-center no-print">
        <button onclick="window.print()" class="btn btn-primary me-2">Print Receipt</button>
        <a href="supplier_payment.php" class="btn btn-secondary">Back to Payments</a>
    </div>
    <?php else: ?>
    <div class="alert alert-danger text-center">
        <?php echo $modal_message; ?>
        <br>
        <a href="supplier_payment.php" class="btn btn-secondary mt-3">Back to Payments</a>
    </div>
    <?php endif; ?>
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
    <?php if ($modal_message && $modal_type === 'error') { ?>
        $('#errorMessage').text('<?php echo addslashes($modal_message); ?>');
        $('#errorModal').modal('show');
    <?php } ?>
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>