<?php
require_once '../config/db_connect.php';
require_auth(); // Student or Admin must be authenticated

$user_id = current_user_id();

// Fetch fresh user profile from DB using session user_id
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    redirect('../auth/login.php');
}

$profile_error = '';
$password_error = '';

// Handle Profile Updates or Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($first_name) || empty($last_name) || empty($email)) {
            $profile_error = "First name, last name, and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profile_error = "Please enter a valid university email address.";
        } else {
            // Check if email taken by someone else
            $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $chk->execute([$email, $user_id]);
            if ($chk->fetch()) {
                $profile_error = "This email address is already registered to another account.";
            } else {
                try {
                    // Update user. NOTE: Never accept or update student_id, role, or user_id here (prevents privilege escalation)
                    $upd = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?");
                    $upd->execute([$first_name, $last_name, $email, $user_id]);

                    // Synchronize session immediately so all headers and navigation reflect the update
                    $_SESSION['first_name'] = $first_name;
                    $_SESSION['last_name'] = $last_name;
                    $_SESSION['name'] = trim($first_name . ' ' . $last_name);
                    $_SESSION['email'] = $email;

                    set_flash('success', 'Your personal information has been updated successfully.');
                    redirect('profile.php');
                } catch (PDOException $e) {
                    $profile_error = "Failed to update profile: " . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $password_error = "All password fields are required.";
        } elseif (!password_verify($current_password, $user['password'])) {
            $password_error = "The current password you entered is incorrect.";
        } elseif (strlen($new_password) < 8) {
            $password_error = "The new password must be at least 8 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $password_error = "The new password and confirmation password do not match.";
        } else {
            try {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $upd_pwd = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $upd_pwd->execute([$hash, $user_id]);

                set_flash('success', 'Your password has been changed successfully.');
                redirect('profile.php');
            } catch (PDOException $e) {
                $password_error = "Failed to update password: " . $e->getMessage();
            }
        }
    }
}

// User registration stats
$my_reg_count = $conn->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = ?");
$my_reg_count->execute([$user_id]);
$total_registered_events = (int)$my_reg_count->fetchColumn();

// Compute 2-letter initials
$first_init = !empty($user['first_name']) ? strtoupper(substr(trim($user['first_name']), 0, 1)) : '';
$last_init = !empty($user['last_name']) ? strtoupper(substr(trim($user['last_name']), 0, 1)) : '';
$avatar_initials = ($first_init . $last_init) ?: 'U';

// Formatted registration / member date
$member_since = !empty($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : 'Recent Member';

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container" style="max-width: 860px;">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; font-weight: 700; color: var(--blue-600); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Account Settings
            </div>
            <h1 style="font-size: 2.1rem; color: var(--navy-900); letter-spacing: -0.02em;">Student &amp; User Profile</h1>
            <p style="color: var(--slate-500); font-size: 0.95rem; margin-top: 0.2rem;">
                Manage your student identification, contact credentials, and account security.
            </p>
        </div>
        <div>
            <a href="my_registrations.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 600;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <span>My Registrations (<?php echo $total_registered_events; ?>)</span>
            </a>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($flash = get_flash()): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>" style="margin-bottom: 1.5rem;">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Profile Overview Card -->
    <div class="card" style="margin-bottom: 1.75rem; padding: 1.75rem; border: 1px solid var(--slate-200); box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
        <div style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;">
            <div style="width: 76px; height: 76px; border-radius: 50%; background: linear-gradient(135deg, var(--navy-900), var(--blue-600)); color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 1.85rem; font-weight: 800; letter-spacing: 0.05em; box-shadow: 0 4px 12px rgba(10, 37, 64, 0.25); flex-shrink: 0;">
                <?php echo e($avatar_initials); ?>
            </div>
            <div style="flex: 1; min-width: 250px;">
                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                    <h2 style="font-size: 1.5rem; color: var(--navy-900); margin: 0; font-weight: 700;">
                        <?php echo e($user['first_name'] . ' ' . $user['last_name']); ?>
                    </h2>
                    <span class="status-badge status-open" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase;">
                        <?php echo strtoupper(e($user['role'])); ?>
                    </span>
                    <?php if ($user['is_verified']): ?>
                        <span style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.78rem; font-weight: 600; color: var(--emerald-600); background: #ecfdf5; padding: 0.2rem 0.6rem; border-radius: 9999px; border: 1px solid #a7f3d0;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            Verified Account
                        </span>
                    <?php else: ?>
                        <span style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.78rem; font-weight: 600; color: #b45309; background: #fffbeb; padding: 0.2rem 0.6rem; border-radius: 9999px; border: 1px solid #fde68a;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            Pending Verification
                        </span>
                    <?php endif; ?>
                </div>

                <div style="display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap; font-size: 0.88rem; color: var(--slate-600); margin-top: 0.5rem;">
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem;">
                        <strong style="color: var(--slate-700);">Student ID:</strong> 
                        <span style="font-family: monospace; font-size: 0.95rem; font-weight: 600; color: var(--navy-900);"><?php echo e($user['student_id']); ?></span>
                    </span>
                    <span>&bull;</span>
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem;">
                        <strong style="color: var(--slate-700);">Email:</strong> 
                        <span><?php echo e($user['email']); ?></span>
                    </span>
                    <span>&bull;</span>
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem;">
                        <strong style="color: var(--slate-700);">Member Since:</strong> 
                        <span><?php echo e($member_since); ?></span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Forms Stack -->
    <div style="display: flex; flex-direction: column; gap: 1.75rem;">
        
        <!-- Section 1: Personal Details -->
        <div class="card" style="padding: 2rem;">
            <div style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--slate-100); padding-bottom: 1rem;">
                <h3 style="font-size: 1.25rem; color: var(--navy-900); margin-bottom: 0.25rem; font-weight: 700;">Personal Information</h3>
                <p style="font-size: 0.88rem; color: var(--slate-500); margin: 0;">Update your name and primary university contact email.</p>
            </div>

            <?php if ($profile_error): ?>
                <div class="alert alert-error" style="margin-bottom: 1.25rem;">
                    <?php echo e($profile_error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="profile.php" id="form-profile">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_profile">

                <!-- Non-editable System Identity Information -->
                <div style="background: #f8fafc; border: 1px solid var(--slate-200); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem;">
                    <div style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--slate-600); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.4rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        Non-Editable University Records
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div>
                            <label style="font-size: 0.82rem; font-weight: 600; color: var(--slate-500); margin-bottom: 0.35rem; display: block;">Student Identification Number</label>
                            <input type="text" value="<?php echo e($user['student_id']); ?>" disabled class="input-disabled" style="font-family: monospace; font-weight: 600;">
                        </div>
                        <div>
                            <label style="font-size: 0.82rem; font-weight: 600; color: var(--slate-500); margin-bottom: 0.35rem; display: block;">Assigned Account Role</label>
                            <input type="text" value="<?php echo strtoupper(e($user['role'])); ?>" disabled class="input-disabled" style="font-weight: 600;">
                        </div>
                        <div>
                            <label style="font-size: 0.82rem; font-weight: 600; color: var(--slate-500); margin-bottom: 0.35rem; display: block;">Verification Status</label>
                            <input type="text" value="<?php echo $user['is_verified'] ? 'Verified Account' : 'Pending Verification'; ?>" disabled class="input-disabled" style="font-weight: 600;">
                        </div>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--slate-500); margin-top: 0.75rem; display: flex; align-items: center; gap: 0.35rem;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        Official records and security roles are managed strictly by university administrators to maintain system integrity.
                    </div>
                </div>

                <!-- Editable Fields -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="first_name" style="font-weight: 600; color: var(--slate-700);">First Name <span style="color: var(--danger-dot);">*</span></label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo e($user['first_name']); ?>" required placeholder="Enter first name">
                    </div>

                    <div class="form-group">
                        <label for="last_name" style="font-weight: 600; color: var(--slate-700);">Last Name <span style="color: var(--danger-dot);">*</span></label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo e($user['last_name']); ?>" required placeholder="Enter last name">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" style="font-weight: 600; color: var(--slate-700);">University Email Address <span style="color: var(--danger-dot);">*</span></label>
                    <input type="email" id="email" name="email" value="<?php echo e($user['email']); ?>" required placeholder="student@university.edu">
                    <span style="font-size: 0.78rem; color: var(--slate-500); display: block; margin-top: 0.25rem;">This email will be used for event confirmations, registration tickets, and system notifications.</span>
                </div>

                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--slate-100);">
                    <button type="reset" class="btn btn-secondary" onclick="window.location.reload();">Reset Changes</button>
                    <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.4rem;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        Save Profile Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Section 2: Change Password -->
        <div class="card" style="padding: 2rem;">
            <div style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--slate-100); padding-bottom: 1rem;">
                <h3 style="font-size: 1.25rem; color: var(--navy-900); margin-bottom: 0.25rem; font-weight: 700;">Account Security &amp; Password</h3>
                <p style="font-size: 0.88rem; color: var(--slate-500); margin: 0;">Change your password to ensure your account remains safe and authorized.</p>
            </div>

            <?php if ($password_error): ?>
                <div class="alert alert-error" style="margin-bottom: 1.25rem;">
                    <?php echo e($password_error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="profile.php" id="form-password" autocomplete="off">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label for="current_password" style="font-weight: 600; color: var(--slate-700);">Current Password <span style="color: var(--danger-dot);">*</span></label>
                    <div class="password-input-group">
                        <input type="password" id="current_password" name="current_password" placeholder="Enter your current password" required autocomplete="current-password">
                        <button type="button" class="btn-toggle-pw" onclick="togglePasswordVisibility('current_password', this)" title="Show or hide password" aria-label="Toggle password visibility">
                            <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        </button>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="new_password" style="font-weight: 600; color: var(--slate-700);">New Password (min. 8 characters) <span style="color: var(--danger-dot);">*</span></label>
                        <div class="password-input-group">
                            <input type="password" id="new_password" name="new_password" placeholder="New secure password" required minlength="8" autocomplete="new-password">
                            <button type="button" class="btn-toggle-pw" onclick="togglePasswordVisibility('new_password', this)" title="Show or hide password" aria-label="Toggle password visibility">
                                <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" style="font-weight: 600; color: var(--slate-700);">Confirm New Password <span style="color: var(--danger-dot);">*</span></label>
                        <div class="password-input-group">
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password" required minlength="8" autocomplete="new-password">
                            <button type="button" class="btn-toggle-pw" onclick="togglePasswordVisibility('confirm_password', this)" title="Show or hide password" aria-label="Toggle password visibility">
                                <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div style="font-size: 0.78rem; color: var(--slate-500); margin-top: 0.25rem; display: flex; align-items: center; gap: 0.35rem;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    Password must be at least 8 characters. We recommend a strong combination of letters, numbers, and symbols.
                </div>

                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--slate-100);">
                    <button type="reset" class="btn btn-secondary">Clear Fields</button>
                    <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.4rem;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        Update Password
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
function togglePasswordVisibility(inputId, btn) {
    var input = document.getElementById(inputId);
    if (!input) return;
    var eye = btn.querySelector('.icon-eye');
    var eyeOff = btn.querySelector('.icon-eye-off');
    
    if (input.type === 'password') {
        input.type = 'text';
        if (eye) eye.style.display = 'none';
        if (eyeOff) eyeOff.style.display = 'inline-block';
    } else {
        input.type = 'password';
        if (eye) eye.style.display = 'inline-block';
        if (eyeOff) eyeOff.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
