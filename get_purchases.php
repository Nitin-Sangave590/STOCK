<?php
include 'db.php';
$supplier_id = intval($_GET['supplier_id'] ?? 0);
$stmt = $conn->prepare("SELECT id, invoice_number FROM purchases WHERE supplier_id = ?");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    echo "<option value='{$row['id']}'>#{$row['invoice_number']}</option>";
}
$stmt->close();
?>