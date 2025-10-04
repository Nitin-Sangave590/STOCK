<?php
// expense_types.php - Manage Expense Types
include 'db.php';
include 'header.php';

$action = $_GET['action'] ?? '';
$modal_message = '';
$modal_type = '';

$expense_type = [];
if ($action === 'edit') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        $modal_message = "Invalid Expense Type ID";
        $modal_type = 'error';
    } else {
        $stmt = $conn->prepare("SELECT * FROM expense_types WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $expense_type = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$expense_type) {
            $modal_message = "Expense Type not found";
            $modal_type = 'error';
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description'] ?? '');

    if (!$name) {
        $modal_message = "Error: Expense Type Name is required.";
        $modal_type = 'error';
    } else {
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO expense_types (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $description);
            if ($stmt->execute()) {
                $modal_message = "Expense Type added successfully!";
                $modal_type = 'success';
            } else {
                error_log("Expense Type Insert Failed: " . $conn->error);
                $modal_message = "Failed to add expense type. Check logs.";
                $modal_type = 'error';
            }
            $stmt->close();
        } elseif ($action === 'edit') {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("UPDATE expense_types SET name = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $description, $id);
            if ($stmt->execute()) {
                $modal_message = "Expense Type updated successfully!";
                $modal_type = 'success';
            } else {
                error_log("Expense Type Update Failed: " . $conn->error);
                $modal_message = "Failed to update expense type. Check logs.";
                $modal_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Pagination for expense types list
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM expense_types");
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
    <title>Manage Expense Types</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #cancel {
            margin-left: 80%;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold">Manage Expense Types</h2>
        <a href="expense_types.php?action=add" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Add New Expense Type
        </a>
    </div>

    <?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="card shadow-sm p-4 rounded-3">
        <form method="POST" class="row g-3" action="expense_types.php?action=<?= $action ?>&id=<?= $action === 'edit' ? $id : '' ?>">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Expense Type Name</label>
                <input type="text" name="name" class="form-control" placeholder="Enter expense type name" required value="<?= $action === 'edit' ? htmlspecialchars($expense_type['name']) : '' ?>">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Optional description..."><?= $action === 'edit' ? htmlspecialchars($expense_type['description']) : '' ?></textarea>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-success me-2">
                    <i class="bi bi-save"></i> <?= $action === 'edit' ? 'Update' : 'Save' ?> Expense Type
                </button>
                <a href="expense_types.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <?php else: ?>
    <div class="card shadow-sm rounded-3">
        <div class="card-header bg-success text-white fw-bold">
            Expense Types List
            <a href="index.php" id="cancel" class="btn btn-danger"> ❌ Close </a>
        </div>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM expense_types ORDER BY id DESC LIMIT ? OFFSET ?");
                    $stmt->bind_param("ii", $limit, $offset);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>
                                <td>{$row['id']}</td>
                                <td>" . htmlspecialchars($row['name']) . "</td>
                                <td>" . htmlspecialchars($row['description'] ?: '-') . "</td>
                                <td>
                                    <a href='expense_types.php?action=edit&id={$row['id']}&page=$page' class='btn btn-sm btn-outline-secondary'>✏ Edit</a>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' class='text-center text-muted'>No expense types found</td></tr>";
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
                <a href="expense_types.php?page=<?= $page ?>" class="btn btn-primary">Back to Expense Types</a>
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
    <?php if ($modal_message && $modal_type === 'success') { ?>
        $('#successMessage').text('<?= addslashes($modal_message) ?>');
        $('#successModal').modal('show');
    <?php } elseif ($modal_message && $modal_type === 'error') { ?>
        $('#errorMessage').text('<?= addslashes($modal_message) ?>');
        $('#errorModal').modal('show');
    <?php } ?>
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>