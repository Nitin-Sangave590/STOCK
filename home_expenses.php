<?php
// home_expenses.php - Home Expenses Entry & Ledger with Pagination, Total & Date Filter
include 'db.php';
include 'header.php';

// ---------- HELPER FUNCTIONS ----------
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

// ---------- VARIABLES ----------
$action = $_GET['action'] ?? '';
$modal_message = '';
$modal_type = ''; // 'success' or 'error'

// ---------- HANDLE FORM SUBMISSION ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_type = trim($_POST['expense_type']); // Free text input
    $expense_date = $_POST['expense_date'];
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);
    $paid_by = trim($_POST['paid_by'] ?? '');

    if (!$expense_date || $amount <= 0 || empty($expense_type)) {
        $modal_message = "Error: Expense Type, Date, and valid Amount are required.";
        $modal_type = 'error';
    } else {
        if ($action === 'add') {
            $sql = "INSERT INTO home_expenses (expense_type_text, expense_date, amount, description, paid_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdss", $expense_type, $expense_date, $amount, $description, $paid_by);
            if ($stmt->execute()) {
                $modal_message = "✅ Expense added successfully!";
                $modal_type = 'success';
            } else {
                error_log("Expense Insert Failed: " . $conn->error);
                $modal_message = "❌ Failed to add expense. Check logs.";
                $modal_type = 'error';
            }
            $stmt->close();
        }
    }
}

// ---------- FILTERS ----------
$filter_date = $_GET['filter_date'] ?? '';
$where_sql = '';
$params = [];
$types = '';

if (!empty($filter_date)) {
    $where_sql = "WHERE he.expense_date = ?";
    $params[] = $filter_date;
    $types .= "s";
}

// ---------- PAGINATION ----------
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// ---------- COUNT TOTAL ROWS ----------
$count_sql = "SELECT COUNT(*) as total FROM home_expenses he $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();
$total_pages = ceil($total_rows / $limit);

// ---------- FETCH EXPENSES ----------
$list_sql = "SELECT he.* FROM home_expenses he $where_sql ORDER BY he.id DESC LIMIT ? OFFSET ?";
$list_stmt = $conn->prepare($list_sql);

if (!empty($params)) {
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    $list_stmt->bind_param($types, ...$params);
} else {
    $list_stmt->bind_param("ii", $limit, $offset);
}

$list_stmt->execute();
$result = $list_stmt->get_result();
$expenses = [];
$total_amount = 0;
while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
    $total_amount += $row['amount'];
}
$list_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Expenses Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 15px; }
        .table thead th { background-color: #212529; color: white; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold">🏠 Home Expenses</h2>
        <a href="?action=add" class="btn btn-success">➕ Add Expense</a>
    </div>

    <?php if ($action === 'add'): ?>
    <!-- ADD EXPENSE FORM -->
    <div class="card shadow-sm p-4">
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Expense Type</label>
                <input type="text" name="expense_type" class="form-control" placeholder="Enter expense type (e.g., Groceries, Utilities)" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Expense Date</label>
                <input type="date" name="expense_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Amount</label>
                <input type="number" step="0.01" name="amount" class="form-control" required>
            </div>
            <div class="col-md-8">
                <label class="form-label fw-semibold">Paid By</label>
                <input type="text" name="paid_by" class="form-control" placeholder="Cash / Bank / Card / Family Member">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="e.g., Monthly electricity bill"></textarea>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-success me-2">💾 Save</button>
                <a href="home_expenses.php" class="btn btn-secondary">❌ Cancel</a>
            </div>
        </form>
    </div>

    <?php else: ?>
    <!-- FILTER FORM -->
    <form method="GET" class="row g-3 mb-3">
        <div class="col-md-3">
            <label class="form-label">Filter by Date</label>
            <input type="date" name="filter_date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
        </div>
        <div class="col-md-3 align-self-end">
            <button class="btn btn-primary">🔍 Filter</button>
            <a href="home_expenses.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between">
            <span>Home Expenses Ledger</span>
            <a href="index.php" class="btn btn-danger btn-sm">Close ✖</a>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
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
                            <td><?= htmlspecialchars($expense['expense_type_text'] ?? 'N/A') ?></td>
                            <td><?= $expense['expense_date'] ?></td>
                            <td class="fw-bold text-danger text-end">₹<?= number_format($expense['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($expense['paid_by'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($expense['description'] ?? '-') ?></td>
                            <td>
                                <a href="?action=edit&id=<?= $expense['id'] ?>" class="btn btn-sm btn-outline-warning">Edit</a>
                                <a href="?action=delete&id=<?= $expense['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="table-secondary fw-bold">
                            <td colspan="3" class="text-end">TOTAL</td>
                            <td class="text-end text-danger">₹<?= number_format($total_amount, 2) ?></td>
                            <td colspan="3"></td>
                        </tr>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted">No expenses found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <nav class="p-3">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&filter_date=<?= htmlspecialchars($filter_date) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- SUCCESS / ERROR MODAL -->
<?php if ($modal_message): ?>
<div class="modal fade" id="messageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header <?= $modal_type === 'success' ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                <h5 class="modal-title"><?= ucfirst($modal_type) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"><p><?= htmlspecialchars($modal_message) ?></p></div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
<script>$(document).ready(()=>$('#messageModal').modal('show'));</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
</body>
</html>