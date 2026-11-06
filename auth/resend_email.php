<?php
include '../config/db_connect.php';

$error = $success = '';
$verify_link = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND is_verified = 0");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $new_code = md5(uniqid($email, true));
        $update = $conn->prepare("UPDATE users SET verification_code = ? WHERE user_id = ?");
        $update->execute([$new_code, $user['user_id']]);
        
        $verify_link = "verify.php?code=" . urlencode($new_code);
        $success = "A fresh activation link has been generated for your account.";
    } else {
        $error = "No pending unverified account was found with that email address.";
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
            <h2>Resend Activation</h2>
            <p>Enter your university email to receive a new account verification link.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" style="flex-direction: column; align-items: stretch; gap: 0.6rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    Activation Link Ready
                </div>
                <div><?php echo $success; ?></div>
                <div style="margin-top: 0.4rem;">
                    <a href="<?php echo $verify_link; ?>" class="btn btn-primary btn-block" style="background: var(--success-dot); border-color: var(--success-dot);">
                        ⚡ Click Here to Activate Account &rarr;
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label>University Email Address</label>
                <input type="email" name="email" placeholder="student@university.edu" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.75rem; margin-top: 0.5rem;">
                Generate Activation Link
            </button>
        </form>

        <p style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem;">
            <a href="login.php" style="color: var(--slate-500); font-weight: 600;">&larr; Back to Login</a>
        </p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
