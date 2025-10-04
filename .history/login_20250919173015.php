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
            background: linear-gradient(135deg, #74ebd5, #acb6e5);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            padding: 2rem;
            max-width: 400px;
            width: 100%;
        }
        .form-control:focus {
            border-color: #74ebd5;
            box-shadow: 0 0 5px rgba(116, 235, 213, 0.5);
        }
        .btn-custom {
            background: linear-gradient(to right, #74ebd5, #acb6e5);
            border: none;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-2xl font-bold text-center mb-6">Login</h2>
        <?php
        session_start();
        $error = '';
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $password = $_POST['password'];

            // Basic validation (replace with your database logic)
            if (empty($email) || empty($password)) {
                $error = "Please fill in all fields";
            } else {
                // Replace with actual database check
                if ($email === "test@example.com" && $password === "password123") {
                    $_SESSION['user'] = $email;
                    header("Location: dashboard.php"); // Redirect to dashboard
                    exit();
                } else {
                    $error = "Invalid email or password";
                }
            }
        }

        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['forgot'])) {
            $forgot_email = filter_var($_POST['forgot_email'], FILTER_SANITIZE_EMAIL);
            if (empty($forgot_email)) {
                $error = "Please enter your email";
            } else {
                // Simulate sending reset email (replace with actual email logic)
                $reset_token = bin2hex(random_bytes(16));
                $_SESSION['reset_token'] = $reset_token;
                $error = "Password reset link sent to $forgot_email";
                // In real implementation, send email with reset link
            }
        }
        ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="">
            <div class="mb-4">
                <label for="email" class="form-label"><i class="fas fa-envelope mr-2"></i>Email</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label"><i class="fas fa-lock mr-2"></i>Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <div class="mb-4 text-right">
                <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal" class="text-sm text-blue-600 hover:underline">Forgot Password?</a>
            </div>
            <button type="submit" name="login" class="btn btn-custom w-100 py-2">Login</button>
        </form>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="forgotPasswordModalLabel">Forgot Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="forgot_email" class="form-label">Enter your email address</label>
                            <input type="email" class="form-control" id="forgot_email" name="forgot_email" placeholder="Enter your email" required>
                        </div>
                        <button type="submit" name="forgot" class="btn btn-custom w-100">Send Reset Link</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>