<?php
// header.php - Common Header with Bootstrap 5
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaibhav Trading Company</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { padding: 20px; }
        .print-area { display: none; }
        @media print {
            .no-print { display: none; }
            .print-area { display: block; }
        }
        #main-navbar {
            margin-left: -2%;
            width: 105%;
        }
        #name-navbar {
            margin-left: 1%;
        }
        /* Hide default link hover effect showing URL in status bar */
        a.no-url:hover {
            cursor: pointer;
        }
    </style>
    <script>
        // JavaScript function to handle navigation
        function navigateTo(page) {
            window.location.href = page;
        }
        // Suppress URL display in status bar
        $(document).ready(function() {
            $('a.no-url').on('mouseenter', function(e) {
                window.status = '';
            });
            $('a.no-url').on('mouseleave', function(e) {
                window.status = '';
            });
        });
    </script>
</head>
<body>
<nav id="main-navbar" class="navbar navbar-expand-lg navbar-dark bg-black">
    <div class="container-fluid" id="name-navbar">
        <a class="navbar-brand fw-bold no-url" href="#" onclick="navigateTo('index.php')">Vaibhav Trading Company</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNavDropdown">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <!-- Item & Account Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="itemAccountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Item & Account
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="itemAccountDropdown">
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('accounts.php')">Account Master</a></li>
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('items.php')">Item Master</a></li>
                    </ul>
                </li>

                <!-- Data Entry Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dataEntryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Data Entry
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="dataEntryDropdown">
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('purchase_entry.php')">Purchase Entry</a></li>
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('sale_entry.php')">Sale Bill Entry</a></li>
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('customer_receipt.php')">Customer Receipt</a></li>
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('supplier_payment.php')">Supplier Payment</a></li>
                    </ul>
                </li>

                <!-- Printing Section -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="printingDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Printing
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="printingDropdown">
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('patti_printing.php')">Patti Printing</a></li>
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('bill_printing.php')">Bill Printing</a></li>
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('patti_register_print.php')">Purchase Register Printing</a></li>
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('bill_register_print.php')">Sale Register Printing</a></li>
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('customer_receipt_print.php')">Customer Payment Print</a></li>
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('supplier_payment_print.php')">Supplier Receipt Print</a></li>
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('daywise_purchase_sale_report.php')">Day Wise Transaction Report</a></li>
                    </ul>
                </li>

                <!-- Daily Reports Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dailyReportsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Daily Reports
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="dailyReportsDropdown">
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('purchase_register.php')">Purchase Register</a></li>
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('sale_register.php')">Sale Bill Register</a></li>
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('combine_register.php')">Combine Register</a></li>
                    </ul>
                </li>

                <!-- Ledgers Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="ledgerDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Ledgers
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="ledgerDropdown">
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('customer_ledger.php')">Customer Ledger</a></li>
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('supplier_ledger.php')">Supplier Ledger</a></li>
                    </ul>
                </li>


                <!-- Reports & Stock -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="printingDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Expenses
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="printingDropdown">
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('<?php
// shop_expenses.php - Shop Expenses Entry Module
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';
$modal_message = '';
$modal_type = ''; // 'success' or 'error'

// Fetch expense categories (you can create a separate table for categories or hardcode for now)
$categories = [
    'Utilities' => ['Electricity', 'Water', 'Internet'],
    'Maintenance' => ['Repair', 'Cleaning', 'Equipment'],
    'Office' => ['Stationery', 'Printing', 'Subscriptions'],
    'Others' => ['Travel', 'Bank Charges', 'Miscellaneous']
];

// Fetch expense types from database if table exists
$expense_types = [];
if (columnExists($conn, 'expense_types', 'id')) {
    $stmt = $conn->prepare("SELECT id, name FROM expense_types ORDER BY name ASC");
    $stmt->execute();
    $expense_types_result = $stmt->get_result();
    while ($row = $expense_types_result->fetch_assoc()) {
        $expense_types[] = $row;
    }
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_type_id = !empty($_POST['expense_type_id']) ? intval($_POST['expense_type_id']) : null;
    $expense_date = $_POST['expense_date'];
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);
    $paid_by = trim($_POST['paid_by'] ?? '');

    if (!$expense_date || $amount <= 0) {
        $modal_message = "Error: Expense Date and valid Amount are required.";
        $modal_type = 'error';
    } else {
        if ($action === 'add') {
            $sql = "INSERT INTO shop_expenses (expense_type_id, expense_date, amount, description, paid_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isdss", $expense_type_id, $expense_date, $amount, $description, $paid_by);
            
            if ($stmt->execute()) {
                $modal_message = "Expense added successfully!";
                $modal_type = 'success';
            } else {
                error_log("Expense Insert Failed: " . $conn->error);
                $modal_message = "Failed to add expense. Check logs.";
                $modal_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Pagination for expense list
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(*) as total FROM shop_expenses";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();
$total_pages = ceil($total_rows / $limit);

// Fetch expenses list
$list_sql = "SELECT se.*, et.name as expense_type 
             FROM shop_expenses se 
             LEFT JOIN expense_types et ON se.expense_type_id = et.id 
             ORDER BY se.id DESC LIMIT ? OFFSET ?";
$list_stmt = $conn->prepare($list_sql);
$list_stmt->bind_param("ii", $limit, $offset);
$list_stmt->execute();
$expenses_result = $list_stmt->get_result();
$expenses = [];
while ($row = $expenses_result->fetch_assoc()) {
    $expenses[] = $row;
}
$list_stmt->close();

// Helper function to check if a column/table exists
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Expenses Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #cancel {
            margin-left: 90%;
            margin-top: -4%;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold">Shop Expenses Management</h2>
        <a href="?action=add" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Add New Expense
        </a>
    </div>

    <?php if ($action === 'add'): ?>
    <div class="card shadow-sm p-4 rounded-3">
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Expense Type</label>
                <select name="expense_type_id" id="expense_type_id" class="form-select">
                    <option value="">Select Type</option>
                    <?php if (!empty($expense_types)): ?>
                        <?php foreach ($expense_types as $type): ?>
                            <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">No types available</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Expense Date</label>
                <input type="date" name="expense_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Amount</label>
                <input type="number" step="0.01" name="amount" class="form-control" placeholder="Enter amount" required>
            </div>
            <div class="col-md-8">
                <label class="form-label fw-semibold">Paid By (Optional)</label>
                <input type="text" name="paid_by" class="form-control" placeholder="Cash/Card/Bank/etc">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Details about the expense..."></textarea>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-success me-2">
                    <i class="bi bi-save"></i> Save Expense
                </button>
                <a href="shop_expenses.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <?php else: ?>
    <style>
        #cancel{
            margin-left: 90%;
            margin-top: -4%;
        }
    </style>
    <div class="card shadow-sm rounded-3">
        <div class="card-header bg-primary text-white fw-bold">
            Shop Expenses Ledger
            <a href="index.php" id="cancel" class="btn btn-danger"> ❌close </a>
        </div>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Paid By</th>
                        <th>Description</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($expenses)): ?>
                        <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td><?= $expense['id'] ?></td>
                                <td><?= htmlspecialchars($expense['expense_type'] ?? 'N/A') ?></td>
                                <td><?= $expense['expense_date'] ?></td>
                                <td class="fw-bold text-danger">₹<?= number_format($expense['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($expense['paid_by'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($expense['description'] ?? '-') ?></td>
                                <td>
                                    <a href="?action=edit&id=<?= $expense['id'] ?>" class="btn btn-sm btn-outline-warning">Edit</a>
                                    <a href="?action=delete&id=<?= $expense['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted">No expenses found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mt-3">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Success/Error Modals -->
<?php if ($modal_message): ?>
<div class="modal fade" id="messageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header <?= $modal_type === 'success' ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                <h5 class="modal-title"><?= ucfirst($modal_type) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><?= htmlspecialchars($modal_message) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    <?php if ($modal_message): ?>
        $('#messageModal').modal('show');
    <?php endif; ?>
});
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
</body>
</html>.php')">Shop Expenses</a></li>
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('bill_printing.php')">Home Expenses</a></li>
                        <li><a class="dropdown-item no-url" href="#" onclick="navigateTo('patti_register_print.php')">Other Expenses</a></li>
                    </ul>
                </li>


                <!-- <li class="nav-item">
                    <a class="nav-link no-url" href="#" onclick="navigateTo('reports.php')">Reports</a>
                </li> -->
                <li class="nav-item">
                    <a class="nav-link no-url" href="#" onclick="navigateTo('check_stock.php')">Check Stock</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
</body>
</html>