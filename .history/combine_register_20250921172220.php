<?php
ob_start();
include 'db.php';
include 'header.php';

// Check database connection
if ($conn->connect_error) {
    die("<div class='alert alert-danger container mt-3'>Database connection failed: " . $conn->connect_error . "</div>");
}

// Get action, search query, transaction type, and date filters
$action = $_GET['action'] ?? '';
$search = $_GET['search'] ?? '';
$transaction_type = $_GET['type'] ?? 'all';
$from = $_GET['from'] ?? date('Y-01-01');
$to = $_GET['to'] ?? date('Y-m-d');

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Handle form submission for adding transaction
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $conn->real_escape_string(trim($_POST['type']));
    $account_id = $conn->real_escape_string(trim($_POST['account_id']));
    $invoice_number = $conn->real_escape_string(trim($_POST['invoice_number']));
    $date = $conn->real_escape_string(trim($_POST['date']));
    $item_id = $conn->real_escape_string(trim($_POST['item_id']));
    $weight = $conn->real_escape_string(trim($_POST['weight']));
    $quantity = $type === 'sale' ? $conn->real_escape_string(trim($_POST['quantity'])) : 0;
    $rate = $conn->real_escape_string(trim($_POST['rate']));
    $hamali = $type === 'purchase' ? $conn->real_escape_string(trim($_POST['hamali'])) : 0;
    $freight = $type === 'purchase' ? $conn->real_escape_string(trim($_POST['freight'])) : 0;
    $total_amount = $conn->real_escape_string(trim($_POST['total_amount']));

    $table = ($type === 'purchase') ? 'purchases' : 'sales';
    $detail_table = ($type === 'purchase') ? 'purchase_details' : 'sale_details';

    // Insert into main table
    $sql = "INSERT INTO $table (";
    $sql .= $type === 'purchase' ? "supplier_id, invoice_number, purchase_date, hamali, freight, total_amount" : "customer_id, invoice_number, date, total_amount";
    $sql .= ") VALUES ('$account_id', '$invoice_number', '$date', " . ($type === 'purchase' ? "'$hamali', '$freight', " : "") . "'$total_amount')";

    if ($conn->query($sql)) {
        $transaction_id = $conn->insert_id;
        // Insert into details table
        $detail_sql = "INSERT INTO $detail_table (";
        $detail_sql .= $type === 'purchase' ? "purchase_id, item_id, weight, rate" : "sale_id, item_id, quantity, weight, rate";
        $detail_sql .= ") VALUES ('$transaction_id', '$item_id', " . ($type === 'purchase' ? "'$weight', '$rate'" : "'$quantity', '$weight', '$rate'") . ")";
        
        if ($conn->query($detail_sql)) {
            header("Location: combined_register.php?type=$transaction_type&from=$from&to=$to");
            exit;
        } else {
            echo "<div class='alert alert-danger container mt-3'>Error in details: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='alert alert-danger container mt-3'>Error: " . $conn->error . "</div>";
    }
}

// Build query for transactions
$sql = "SELECT t.id, t.type, t.invoice_number, t.transaction_date, a.name as account_name, i.name as item_name, 
        t.weight, t.quantity, t.rate, t.hamali, t.freight, t.total_amount
        FROM (
            SELECT id, 'purchase' as type, invoice_number, purchase_date as transaction_date, supplier_id as account_id, 
                   pd.item_id, pd.weight, NULL as quantity, pd.rate, hamali, freight, total_amount
            FROM purchases p
            INNER JOIN purchase_details pd ON p.id = pd.purchase_id
            UNION ALL
            SELECT id, 'sale' as type, invoice_number, date as transaction_date, customer_id as account_id, 
                   sd.item_id, sd.weight, sd.quantity, sd.rate, 0 as hamali, 0 as freight, total_amount
            FROM sales s
            INNER JOIN sale_details sd ON s.id = sd.sale_id
        ) t 
        JOIN accounts a ON t.account_id = a.id
        JOIN items i ON t.item_id = i.id
        WHERE t.transaction_date BETWEEN ? AND ?";

if ($transaction_type !== 'all') {
    $sql .= " AND t.type = ?";
}
if ($search) {
    $search = $conn->real_escape_string($search);
    $sql .= " AND (a.name LIKE '%$search%' OR i.name LIKE '%$search%' OR t.invoice_number LIKE '%$search%')";
}

// Get total records for pagination
$total_sql = $sql;
$stmt = $conn->prepare($total_sql);
if ($stmt === false) {
    echo "<div class='alert alert-danger container mt-3'>SQL Prepare Error: " . $conn->error . "</div>";
    exit;
}

if ($transaction_type !== 'all') {
    $stmt->bind_param("sss", $from, $to, $transaction_type);
} else {
    $stmt->bind_param("ss", $from, $to);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_records = $total_result->num_rows;
$total_pages = ceil($total_records / $records_per_page);

// Add limit for pagination
$sql .= " ORDER BY t.transaction_date DESC, t.id DESC LIMIT ?, ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo "<div class='alert alert-danger container mt-3'>SQL Prepare Error: " . $conn->error . "</div>";
    exit;
}

if ($transaction_type !== 'all') {
    $stmt->bind_param("sssi", $from, $to, $transaction_type, $offset, $records_per_page);
} else {
    $stmt->bind_param("ssii", $from, $to, $offset, $records_per_page);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<style>
    body {
        background-color: #f5f6fa;
        font-family: 'Inter', sans-serif;
        color: #2d3748;
    }
    .container {
        max-width: 1400px;
        margin: 2rem auto;
    }
    .card-custom {
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        padding: 2rem;
        transition: transform 0.3s ease;
    }
    .card-custom:hover {
        transform: translateY(-4px);
    }
    .page-title {
        font-size: 2rem;
        font-weight: 700;
        color: #2b6cb0;
        text-align: center;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .nav-tabs {
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 1.5rem;
    }
    .nav-tabs .nav-link {
        color: #4a5568;
        font-weight: 500;
        padding: 0.75rem 1.5rem;
        border-radius: 8px 8px 0 0;
        transition: all 0.3s ease;
    }
    .nav-tabs .nav-link:hover {
        background-color: #edf2f7;
    }
    .nav-tabs .nav-link.active {
        background-color: #2b6cb0;
        color: white;
        border: none;
    }
    .search-bar {
        display: flex;
        gap: 0.5rem;
        max-width: 600px;
    }
    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 0.75rem;
        transition: border-color 0.3s ease;
    }
    .form-control:focus, .form-select:focus {
        border-color: #2b6cb0;
        box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.1);
    }
    .btn {
        border-radius: 8px;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    .btn-primary {
        background-color: #2b6cb0;
        border-color: #2b6cb0;
    }
    .btn-primary:hover {
        background-color: #2c5282;
        border-color: #2c5282;
    }
    .btn-secondary {
        background-color: #718096;
        border-color: #718096;
    }
    .btn-secondary:hover {
        background-color: #5a6b85;
        border-color: #5a6b85;
    }
    .btn-success {
        background-color: #38a169;
        border-color: #38a169;
    }
    .btn-success:hover {
        background-color: #2f855a;
        border-color: #2f855a;
    }
    .table {
        border-radius: 8px;
        overflow: hidden;
    }
    .table thead {
        background-color: #2b6cb0;
        color: white;
    }
    .table-hover tbody tr {
        transition: all 0.3s ease;
    }
    .table-hover tbody tr:hover {
        background-color: #edf2f7;
        transform: translateX(4px);
    }
    .pagination .page-link {
        border-radius: 6px;
        margin: 0 4px;
        color: #2b6cb0;
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    .pagination .page-link:hover {
        background-color: #2b6cb0;
        color: white;
        border-color: #2b6cb0;
    }
    .pagination .page-item.active .page-link {
        background-color: #2b6cb0;
        border-color: #2b6cb0;
        color: white;
    }
    .pagination .page-item.disabled .page-link {
        color: #a0aec0;
        cursor: not-allowed;
    }
    #closeaccountmas {
        position: absolute;
        right: 1rem;
        top: 1rem;
    }
</style>

<div class="container">
    <h2 class="page-title">📊 Combined Register</h2>

    <?php if ($action === 'add'): ?>
        <div class="card-custom">
            <h4 class="mb-4 text-primary">➕ Add New Transaction</h4>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label"><strong>Type</strong></label>
                    <select name="type" class="form-select" required onchange="toggleFields(this)">
                        <option value="">-- Select Transaction Type --</option>
                        <option value="purchase">Purchase</option>
                        <option value="sale">Sale</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Account</strong></label>
                    <select name="account_id" class="form-select" required>
                        <option value="">-- Select Account --</option>
                        <?php
                        $account_sql = "SELECT id, name, type FROM accounts ORDER BY name";
                        $account_result = $conn->query($account_sql);
                        if ($account_result) {
                            while ($acc = $account_result->fetch_assoc()) {
                                echo "<option value='{$acc['id']}'>{$acc['name']} ({$acc['type']})</option>";
                            }
                        } else {
                            echo "<option value=''>No accounts available</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Invoice Number</strong></label>
                    <input type="text" name="invoice_number" class="form-control" required placeholder="Enter invoice number">
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Date</strong></label>
                    <input type="date" name="date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Item</strong></label>
                    <select name="item_id" class="form-select" required>
                        <option value="">-- Select Item --</option>
                        <?php
                        $item_sql = "SELECT id, name FROM items ORDER BY name";
                        $item_result = $conn->query($item_sql);
                        if ($item_result) {
                            while ($item = $item_result->fetch_assoc()) {
                                echo "<option value='{$item['id']}'>{$item['name']}</option>";
                            }
                        } else {
                            echo "<option value=''>No items available</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Weight</strong></label>
                    <input type="number" step="0.01" name="weight" class="form-control" required placeholder="Enter weight">
                </div>
                <div class="mb-3" id="quantity-field" style="display: none;">
                    <label class="form-label"><strong>Quantity</strong></label>
                    <input type="number" step="0.01" name="quantity" class="form-control" placeholder="Enter quantity">
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Rate</strong></label>
                    <input type="number" step="0.01" name="rate" class="form-control" required placeholder="Enter rate">
                </div>
                <div class="mb-3" id="hamali-field">
                    <label class="form-label"><strong>Hamali</strong></label>
                    <input type="number" step="0.01" name="hamali" class="form-control" placeholder="Enter hamali">
                </div>
                <div class="mb-3" id="freight-field">
                    <label class="form-label"><strong>Freight</strong></label>
                    <input type="number" step="0.01" name="freight" class="form-control" placeholder="Enter freight">
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Total Amount</strong></label>
                    <input type="number" step="0.01" name="total_amount" class="form-control" required placeholder="Enter total amount">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">💾 Save Transaction</button>
                    <a href="combined_register.php?type=<?php echo $transaction_type; ?>&from=<?php echo $from; ?>&to=<?php echo $to; ?>" class="btn btn-secondary">❌ Cancel</a>
                </div>
            </form>
        </div>
        <script>
            function toggleFields(select) {
                const quantityField = document.getElementById('quantity-field');
                const hamaliField = document.getElementById('hamali-field');
                const freightField = document.getElementById('freight-field');
                if (select.value === 'sale') {
                    quantityField.style.display = 'block';
                    hamaliField.style.display = 'none';
                    freightField.style.display = 'none';
                } else {
                    quantityField.style.display = 'none';
                    hamaliField.style.display = 'block';
                    freightField.style.display = 'block';
                }
            }
        </script>
    <?php else: ?>
        <!-- Tabs for All, Purchases, Sales -->
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link <?php echo $transaction_type === 'all' ? 'active' : ''; ?>" href="combined_register.php?type=all&from=<?php echo $from; ?>&to=<?php echo $to; ?>">All Transactions</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $transaction_type === 'purchase' ? 'active' : ''; ?>" href="combined_register.php?type=purchase&from=<?php echo $from; ?>&to=<?php echo $to; ?>">Purchases</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $transaction_type === 'sale' ? 'active' : ''; ?>" href="combined_register.php?type=sale&from=<?php echo $from; ?>&to=<?php echo $to; ?>">Sales</a>
            </li>
        </ul>

        <!-- Filter and Search Bar -->
        <div class="d-flex justify-content-between mb-4">
            <form class="search-bar" method="GET">
                <div class="d-flex gap-2">
                    <select name="type" class="form-select" style="width: 150px;">
                        <option value="all" <?php echo $transaction_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="purchase" <?php echo $transaction_type === 'purchase' ? 'selected' : ''; ?>>Purchase</option>
                        <option value="sale" <?php echo $transaction_type === 'sale' ? 'selected' : ''; ?>>Sale</option>
                    </select>
                    <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($from); ?>" required>
                    <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($to); ?>" required>
                    <input type="text" name="search" class="form-control" placeholder="Search by account, item, or invoice" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                </div>
            </form>
            <a href="combined_register.php?action=add&type=<?php echo $transaction_type; ?>&from=<?php echo $from; ?>&to=<?php echo $to; ?>" class="btn btn-success">➕ Add New Transaction</a>
        </div>

        <!-- Transactions Table -->
        <div class="card-custom position-relative">
            <h4 class="text-dark mb-4">📋 <?php echo ucfirst($transaction_type === 'all' ? 'All Transactions' : $transaction_type . 's'); ?>
                <a href="index.php" id="closeaccountmas" class="btn btn-danger btn-sm">❌</a>
            </h4>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Invoice No</th>
                            <th>Account</th>
                            <th>Item</th>
                            <th>Weight</th>
                            <th>Quantity</th>
                            <th>Rate</th>
                            <th>Hamali</th>
                            <th>Freight</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total = 0;
                        $i = 1;
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>
                                    <td>$i</td>
                                    <td>" . ucfirst(htmlspecialchars($row['type'])) . "</td>
                                    <td>" . htmlspecialchars($row['transaction_date']) . "</td>
                                    <td>" . htmlspecialchars($row['invoice_number']) . "</td>
                                    <td>" . htmlspecialchars($row['account_name']) . "</td>
                                    <td>" . htmlspecialchars($row['item_name']) . "</td>
                                    <td>" . number_format($row['weight'], 2) . "</td>
                                    <td>" . ($row['quantity'] ? number_format($row['quantity'], 2) : '-') . "</td>
                                    <td>" . number_format($row['rate'], 2) . "</td>
                                    <td>" . ($row['hamali'] ? number_format($row['hamali'], 2) : '-') . "</td>
                                    <td>" . ($row['freight'] ? number_format($row['freight'], 2) : '-') . "</td>
                                    <td>" . number_format($row['total_amount'], 2) . "</td>
                                </tr>";
                                $total += $row['total_amount'];
                                $i++;
                            }
                        } else {
                            echo "<tr><td colspan='12' class='text-center text-muted'>No transactions found in this date range</td></tr>";
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="11" class="text-end">Total</td>
                            <td><?php echo number_format($total ?? 0, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="combined_register.php?type=<?php echo $transaction_type; ?>&search=<?php echo urlencode($search); ?>&from=<?php echo $from; ?>&to=<?php echo $to; ?>&page=<?php echo $page - 1; ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="combined_register.php?type=<?php echo $transaction_type; ?>&search=<?php echo urlencode($search); ?>&from=<?php echo $from; ?>&to=<?php echo $to; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="combined_register.php?type=<?php echo $transaction_type; ?>&search=<?php echo urlencode($search); ?>&from=<?php echo $from; ?>&to=<?php echo $to; ?>&page=<?php echo $page + 1; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>