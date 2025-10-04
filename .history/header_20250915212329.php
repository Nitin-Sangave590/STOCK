<?php
// header.php - Common Header with Bootstrap
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management Software</title>
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
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Stock Management</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                
                <option value="">
                    
                </option>
                <li class="nav-item"><a class="nav-link" href="accounts.php">Account Master</a></li>
                <li class="nav-item"><a class="nav-link" href="items.php">Item Master</a></li>
                <li class="nav-item"><a class="nav-link" href="purchase_entry.php">Purchase Entry</a></li>
                <li class="nav-item"><a class="nav-link" href="sale_entry.php">Sale Bill Entry</a></li>
                <li class="nav-item"><a class="nav-link" href="customer_receipt.php">Customer Receipt</a></li>
                <li class="nav-item"><a class="nav-link" href="supplier_payment.php">Supplier Payment</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php">Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="check_stock.php">Check Stock</a></li>
                <li class="nav-item"><a class="nav-link" href="customer_ledger.php">Customer Ledger</a></li>
                <li class="nav-item"><a class="nav-link" href="supplier_ledger.php">Supplier Ledger</a></li>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-4">