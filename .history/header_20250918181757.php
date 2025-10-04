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
        #main-navbar{
            margin-left: -2%;
            width: 105%;
        }
        #name-navbar{
            margin-left: 1%;
        }
    </style>
</head>
<body>
<nav id="main-navbar" class="navbar navbar-expand-lg navbar-dark bg-black width">
    <div class="container-fluid" id="name-navbar">
        <a class="navbar-brand fw-bold" href="index.php">Vaibhav Trading Company </a>
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
                        <li><a class="dropdown-item" href="accounts.php">Account Master</a></li>
                        <li><a class="dropdown-item" href="items.php">Item Master</a></li>
                    </ul>
                </li>

                <!-- Data Entry Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dataEntryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Data Entry
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="dataEntryDropdown">
                        <li><a class="dropdown-item" href="purchase_entry.php">Purchase Entry</a></li>
                        <li><a class="dropdown-item" href="sale_entry.php">Sale Bill Entry</a></li>
                        <li><a class="dropdown-item" href="customer_receipt.php">Customer Receipt</a></li>
                        <li><a class="dropdown-item" href="supplier_payment.php">Supplier Payment</a></li>
                    </ul>
                </li>

                <!-- PRINTING SECTION -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dataEntryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Data Entry
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="dataEntryDropdown">
                        <li><a class="dropdown-item" href="patti_print.php">Patti Printing</a></li>
                        <li><a class="dropdown-item" href="bill_print.php">Bill Printing</a></li>
                        <li><a class="dropdown-item" href="Purchase_register_print.php">Purchase Register Printing</a></li>
                        <li><a class="dropdown-item" href="sale_registerbill_print.php">Sale Bill Printing</a></li>
                        <li><a class="dropdown-item" href="customer_receipt_print.php">Customer Payment Print</a></li>
                        <li><a class="dropdown-item" href="supplier_payment_print.php">Supplier Receipt Print</a></li>
                    </ul>
                </li>

                <!-- Daily Reports Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dailyReportsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Daily Reports
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="dailyReportsDropdown">
                        <li><a class="dropdown-item" href="purchase_register.php">Purchase Register</a></li>
                        <li><a class="dropdown-item" href="sale_register.php">Sale Bill Register</a></li>
                        <li><a class="dropdown-item" href="combine_register.php">Combine Register</a></li>
                    </ul>
                </li>

                <!-- Ledgers Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="ledgerDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Ledgers
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="ledgerDropdown">
                        <li><a class="dropdown-item" href="customer_ledger.php">Customer Ledger</a></li>
                        <li><a class="dropdown-item" href="supplier_ledger.php">Supplier Ledger</a></li>
                    </ul>
                </li>

                <!-- Reports & Stock -->
                <li class="nav-item"><a class="nav-link" href="reports.php">Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="check_stock.php">Check Stock</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
