<?php
require_once '../../includes/db.php';

// Fetch stock data from the database
$query = "SELECT * FROM stock";
$result = $db->query($query);

// Display stock levels, highlight low stock
if ($result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Item</th><th>Quantity</th></tr>";
    while($row = $result->fetch_assoc()) {
        $lowStockClass = ($row['quantity'] < 10) ? 'low-stock' : '';
        echo "<tr class='$lowStockClass'><td>" . $row["item"] . "</td><td>" . $row["quantity"] . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "No stock data found.";
}
?>
<!-- Stock table with alerts -->