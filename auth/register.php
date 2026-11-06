<?php
include '../config/db_connect.php';

$error = $success = '';
$verify_link = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verify_csrf();
    $student_id = trim($_POST['student_id'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($student_id) || empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid university email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters in length.";
    } else {
        $verification_code = md5(uniqid($email, true));
        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $conn->prepare("INSERT INTO users (student_id, first_name, last_name, email, password, verification_code) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student_id, $first_name, $last_name, $email, $hashed_pass, $verification_code]);
            
            $verify_link = "verify.php?code=" . urlencode($verification_code);
            $success = "Account created successfully! Click the verification link below to activate your account and start registering for events.";
        } catch(PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                $error = "An account with this Student ID or Email already exists.";
            } else {
                $error = "Registration error: " . $e->getMessage();
            }
        }
    }
}
include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="auth-wrapper" style="max-width: 520px;">
    <div class="auth-card">
        <div class="auth-header">
            <div style="width: 56px; height: 56px; border-radius: var(--radius-md); background: #ffffff; border: 1px solid var(--slate-200); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.15rem auto; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08); overflow: hidden; padding: 4px;">
                <img src="../assets/images/logo.jpg" alt="CampusSync Logo" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.onerror=null; this.src='../1.jpg';">
            </div>
            <h2>Create Student Account</h2>
            <p>Join student organizations and participate in university events.</p>
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
                    Registration Succeeded!
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
                <label>Student ID Number <span style="color: var(--danger-dot);">*</span></label>
                <input type="text" name="student_id" placeholder="e.g. 2026-10482" required value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>First Name <span style="color: var(--danger-dot);">*</span></label>
                    <input type="text" name="first_name" placeholder="Juan" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Last Name <span style="color: var(--danger-dot);">*</span></label>
                    <input type="text" name="last_name" placeholder="Dela Cruz" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>University Email <span style="color: var(--danger-dot);">*</span></label>
                <input type="email" name="email" placeholder="student@university.edu" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Password <span style="color: var(--danger-dot);">*</span></label>
                <input type="password" name="password" placeholder="At least 6 characters" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.8rem; margin-top: 1rem;">
                Create Account &amp; Activate
            </button>
        </form>

        <p style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--slate-500);">
            Already have an account? <a href="login.php" style="font-weight: 700; color: var(--blue-600);">Login here</a>
        </p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
