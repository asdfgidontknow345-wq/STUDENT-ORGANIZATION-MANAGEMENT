<?php
require_once '../config/db_connect.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: ../pages/admin_dashboard.php");
    } else {
        header("Location: ../pages/events.php");
    }
    exit;
}

$error = get_flash('error') ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['is_verified'] == 0) {
            $error = "Please verify your email address before logging in. <a href='resend_email.php' style='text-decoration: underline; font-weight: 600;'>Resend verification link</a>";
        } elseif (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header("Location: ../pages/admin_dashboard.php");
            } else {
                header("Location: ../pages/events.php");
            }
            exit;
        } else {
            $error = "Incorrect password. Please verify and try again.";
        }
    } else {
        $error = "No registered account found with that email address.";
    }
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div style="width: 56px; height: 56px; border-radius: var(--radius-md); background: #ffffff; border: 1px solid var(--slate-200); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.15rem auto; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08); overflow: hidden; padding: 4px;">
                <img src="../assets/images/logo.jpg" alt="CampusSync Logo" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.onerror=null; this.src='../1.jpg';">
            </div>
            <h2>Portal Log In</h2>
            <p>Access your event registrations and student organization hub.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label>University Email</label>
                <input type="email" id="email" name="email" placeholder="student@university.edu" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                    <label style="margin-bottom: 0;">Password</label>
                    <a href="resend_email.php" style="font-size: 0.8rem; color: var(--slate-500);">Need activation?</a>
                </div>
                <input type="password" id="password" name="password" placeholder="Enter password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.75rem; margin-top: 1rem;">
                Sign In
            </button>
        </form>

        <!-- Quick Demo Account Fillers -->
        <div style="margin-top: 1.5rem; padding: 1rem; background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: var(--radius-md);">
            <div style="font-size: 0.78rem; font-weight: 700; color: var(--slate-500); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                One-Click Demo Logins
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                <button type="button" onclick="fillCreds('student@university.edu', 'password123')" class="btn btn-secondary btn-sm" style="font-size: 0.8rem;">
                    Student Demo
                </button>
                <button type="button" onclick="fillCreds('admin@university.edu', 'password123')" class="btn btn-secondary btn-sm" style="font-size: 0.8rem;">
                    Admin Demo
                </button>
            </div>
        </div>

        <p style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--slate-500);">
            Don't have an account yet? <a href="register.php" style="font-weight: 700; color: var(--blue-600);">Register here</a>
        </p>
    </div>
</div>

<script>
function fillCreds(e, p) {
    document.getElementById('email').value = e;
    document.getElementById('password').value = p;
}
</script>

<?php include '../includes/footer.php'; ?>