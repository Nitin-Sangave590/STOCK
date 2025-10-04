<?php
// check_stock.php - Check Stock
include 'db.php';
include 'header.php';

$items = $conn->query("SELECT * FROM items");

if (isset($_GET['item_id'])) {
    $item_id = $_GET['item_id'];
    $purchased = $conn->query("SELECT SUM(quantity) as total FROM purchase_details WHERE item_id = $item_id")->fetch_assoc()['total'] ?? 0;
    $sold = $conn->query("SELECT SUM(quantity) as total FROM sale_details WHERE item_id = $item_id")->fetch_assoc()['total'] ?? 0;
    $remaining = $purchased - $sold;

    $item_name = $conn->query("SELECT name FROM items WHERE id = $item_id")->fetch_assoc()['name'];

    echo "<div class='alert alert-info'>Stock for $item_name: $remaining</div>";
}
?>
<h2>Check Stock</h2>
<form method="GET">
    <div class="mb-3">
        <label>Select Product</label>
        <select name="item_id" class="form-select" required>
            <?php while ($row = $items->fetch_assoc()) { ?>
                <option value="<?php echo $row['id']; ?>"><?php echo $row['name']; ?></option>
            <?php } ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Check Stock</button>
</form>

<h3>All Stocks</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <th>Item</th>
            <th>Remaining Stock</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $items->data_seek(0);
        while ($item = $items->fetch_assoc()) {
            $item_id = $item['id'];
            $purchased = $conn->query("SELECT SUM(quantity) as total FROM purchase_details WHERE item_id = $item_id")->fetch_assoc()['total'] ?? 0;
            $sold = $conn->query("SELECT SUM(quantity) as total FROM sale_details WHERE item_id = $item_id")->fetch_assoc()['total'] ?? 0;
            $remaining = $purchased - $sold;
            echo "<tr><td>{$item['name']}</td><td>$remaining</td></tr>";
        }
        ?>
    </tbody>
</table>
<?php include 'footer.php'; ?>