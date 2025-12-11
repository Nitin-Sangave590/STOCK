
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


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beautiful Login Page</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background: transparent;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
            padding: 2.5rem;
            max-width: 450px;
            width: 90%;
            animation: fadeIn 1s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #4b79a1;
            box-shadow: 0 0 8px rgba(75, 121, 161, 0.3);
            outline: none;
        }
        .btn-custom {
            background: linear-gradient(to right, #4b79a1, #283e51);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            background: linear-gradient(to right, #5a9bd4, #365f91);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 0.5rem;
        }
        .alert {
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        .modal-header {
            border-bottom: none;
            padding-bottom: 0;
        }
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }
        .forgot-link {
            color: #4b79a1;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .forgot-link:hover {
            color: #283e51;
            text-decoration: underline;
        }
        @media (max-width: 576px) {
            .login-container {
                padding: 1.5rem;
            }
            .btn-custom {
                padding: 0.6rem;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <h2 class="text-3xl font-bold text-center mb-6 text-gray-800">Vaibhav Trading Company</h2>

    <!-- Error Message (Optional: can be shown dynamically via JS) -->
    <div id="error-message" class="alert alert-danger d-none"></div>

    <!-- Login Form -->
    <form method="POST" action="login_action.php">
        <div class="mb-4">
            <label for="email" class="form-label"><i class="fas fa-user mr-2"></i>Username</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-user"></i></span>
                <input type="text" class="form-control" id="email" name="email" placeholder="Enter username" required>
            </div>
        </div>
        <div class="mb-4">
            <label for="password" class="form-label"><i class="fas fa-lock mr-2"></i>Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
            </div>
        </div>
        <div class="mb-4 text-right">
            <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal" class="forgot-link">Forgot Password?</a>
        </div>
        <button type="submit" name="login" class="btn btn-custom w-100 py-3">Login</button>
    </form>
</div>

<!-- Forgot Password Modal -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-xl font-bold" id="forgotPasswordModalLabel">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-center text-gray-700">Password reset is disabled for this static login.</p>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

