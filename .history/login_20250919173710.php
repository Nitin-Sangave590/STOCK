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
            background: linear-gradient(135deg, #4b79a1, #283e51);
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
    <?php
    session_start();
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = ' ';
    $db_name = 'Stock';

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $error = '';
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            $error = "Please fill in all fields";
        } else {
            $stmt = $conn->prepare("SELECT password FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user'] = $email;
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Invalid email or password";
                }
            } else {
                $error = "Invalid email or password";
            }
            $stmt->close();
        }
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['forgot'])) {
        $forgot_email = filter_var($_POST['forgot_email'], FILTER_SANITIZE_EMAIL);
        if (empty($forgot_email)) {
            $error = "Please enter your email";
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $forgot_email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $token = bin2hex(random_bytes(16));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $forgot_email, $token, $expires);
                if ($stmt->execute()) {
                    $error = "Password reset link sent to $forgot_email";
                    // Add actual email sending logic here
                } else {
                    $error = "Error processing request";
                }
            } else {
                $error = "Email not found";
            }
            $stmt->close();
        }
    }
    ?>

    <div class="login-container">
        <h2 class="text-3xl font-bold text-center mb-6 text-gray-800">Welcome Back</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="">
            <div class="mb-4">
                <label for="email" class="form-label"><i class="fas fa-envelope mr-2"></i>Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label"><i class="fas fa-lock mr-2"></i>Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                </div>
            </div>
            <div class="mb-4 text-right">
                <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal" class="forgot-link">Forgot Password?</a>
            </div>
            <button type="submit" name="login" class="btn btn-custom w-100 py-3">Sign In</button>
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
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="forgot_email" class="form-label">Enter your email address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="forgot_email" name="forgot_email" placeholder="Enter your email" required>
                            </div>
                        </div>
                        <button type="submit" name="forgot" class="btn btn-custom w-100 py-2">Send Reset Link</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>