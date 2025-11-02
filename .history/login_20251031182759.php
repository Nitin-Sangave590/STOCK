
<?php
include "db.php"; // ✅ Connect to stock database inside db.php
session_start();

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // ✅ Static login credentials
    $correct_username = "vtc";
    $correct_password = "vtc5744";

    if ($email === $correct_username && $password === $correct_password) {
        $_SESSION['user'] = $email;
        header("Location: /stock/");
        exit();
    } else {
        $error = "Invalid username or password";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['forgot'])) {
    $error = "Password reset is not available for static login.";
}
?>