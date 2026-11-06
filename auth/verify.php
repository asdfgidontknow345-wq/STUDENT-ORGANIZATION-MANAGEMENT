<?php
include '../config/db_connect.php';

$error = $success = '';

if (isset($_GET['code'])) {
    $code = trim($_GET['code']);
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE verification_code = ? AND is_verified = 0");
    $stmt->execute([$code]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $update = $conn->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE user_id = ?");
        $update->execute([$user['user_id']]);
        $success = "Email verified successfully! Your student account is now activated.";
    } else {
        $error = "This verification code is either invalid or has expired.";
    }
} else {
    $error = "No verification code was provided.";
}
include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="auth-wrapper" style="text-align: center;">
    <div class="auth-card">
        <?php if ($success): ?>
            <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--success-bg); color: var(--success-dot); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
            <h2>Account Verified!</h2>
            <p style="margin-top: 0.5rem; margin-bottom: 1.75rem; color: var(--slate-600);">
                <?php echo $success; ?> You can now sign in to explore and register for campus events.
            </p>
            <a href="login.php" class="btn btn-primary btn-block" style="padding: 0.75rem;">
                Proceed to Log In &rarr;
            </a>
        <?php else: ?>
            <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--danger-bg); color: var(--danger-dot); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            </div>
            <h2>Verification Failed</h2>
            <p style="margin-top: 0.5rem; margin-bottom: 1.5rem; color: var(--slate-600);">
                <?php echo $error; ?>
            </p>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <a href="resend_email.php" class="btn btn-primary btn-block">Resend Activation Link</a>
                <a href="login.php" class="btn btn-secondary btn-block">Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
