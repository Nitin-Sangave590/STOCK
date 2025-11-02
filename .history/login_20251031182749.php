

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

<div class="login-container">
    <h2 class="text-3xl font-bold text-center mb-6 text-gray-800">Vaibhav Trading Company</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST" action="">
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

<!-- JavaScript to manipulate URL after redirect -->
<script>
    window.history.replaceState(null, null, '/stock/#');
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>