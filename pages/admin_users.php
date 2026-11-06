<?php
require_once '../config/db_connect.php';
require_admin(); // Strict RBAC access control

$search = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? 'all');
$status_filter = trim($_GET['status'] ?? 'all');

// Handle POST actions (Toggle Status, Edit User, Delete User)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $target_user_id = intval($_POST['user_id'] ?? 0);

    if ($target_user_id <= 0) {
        set_flash('error', 'Invalid target user specified.');
        redirect('admin_users.php');
    }

    // Safety: Prevent admin from deactivating or deleting their own active account
    if ($target_user_id === current_user_id() && in_array($action, ['toggle_status', 'delete_user'])) {
        set_flash('error', 'Security protection: You cannot deactivate or delete your own logged-in account.');
        redirect('admin_users.php');
    }

    if ($action === 'toggle_status') {
        $stmt = $conn->prepare("SELECT is_verified FROM users WHERE user_id = ?");
        $stmt->execute([$target_user_id]);
        $curr = $stmt->fetch();
        if ($curr) {
            $new_status = $curr['is_verified'] ? 0 : 1;
            $update = $conn->prepare("UPDATE users SET is_verified = ? WHERE user_id = ?");
            $update->execute([$new_status, $target_user_id]);
            $msg = $new_status ? 'User account has been activated/verified.' : 'User account has been deactivated.';
            set_flash('success', $msg);
        } else {
            set_flash('error', 'User not found.');
        }
        redirect('admin_users.php');
    } elseif ($action === 'delete_user') {
        try {
            $del = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $del->execute([$target_user_id]);
            set_flash('success', 'User account and associated records have been successfully deleted.');
        } catch (PDOException $e) {
            set_flash('error', 'Failed to delete user: ' . $e->getMessage());
        }
        redirect('admin_users.php');
    } elseif ($action === 'edit_user') {
        $student_id = trim($_POST['student_id'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'student');

        if (empty($student_id) || empty($first_name) || empty($last_name) || empty($email)) {
            set_flash('error', 'All fields are required.');
            redirect('admin_users.php');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Invalid email address format.');
            redirect('admin_users.php');
        }

        if (!in_array($role, ['student', 'officer', 'admin'])) {
            $role = 'student';
        }

        // Check if student_id or email is already taken by another user
        $check = $conn->prepare("SELECT user_id FROM users WHERE (student_id = ? OR email = ?) AND user_id != ?");
        $check->execute([$student_id, $email, $target_user_id]);
        if ($check->fetch()) {
            set_flash('error', 'Student ID or Email is already used by another account.');
            redirect('admin_users.php');
        }

        try {
            $upd = $conn->prepare("
                UPDATE users 
                SET student_id = ?, first_name = ?, last_name = ?, email = ?, role = ?
                WHERE user_id = ?
            ");
            $upd->execute([$student_id, $first_name, $last_name, $email, $role, $target_user_id]);
            set_flash('success', 'User information updated successfully.');
        } catch (PDOException $e) {
            set_flash('error', 'Database error: ' . $e->getMessage());
        }
        redirect('admin_users.php');
    }
}

// Build query with filters
$query = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM registrations r WHERE r.user_id = u.user_id) as registration_count
    FROM users u 
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (u.student_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($role_filter !== 'all') {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
}

if ($status_filter === 'verified') {
    $query .= " AND u.is_verified = 1";
} elseif ($status_filter === 'pending') {
    $query .= " AND u.is_verified = 0";
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Statistics
$stat_total = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stat_students = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$stat_officers = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'officer'")->fetchColumn();
$stat_pending = $conn->query("SELECT COUNT(*) FROM users WHERE is_verified = 0")->fetchColumn();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.8rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; font-weight: 700; color: var(--blue-600); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Administration &amp; Directory
            </div>
            <h1 style="font-size: 2.1rem; color: var(--navy-900); letter-spacing: -0.02em;">User &amp; Student Management</h1>
            <p style="color: var(--slate-500); font-size: 0.95rem; margin-top: 0.2rem;">
                Manage student records, officer designations, account activation, and verification status.
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="admin_dashboard.php" class="btn btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($flash = get_flash()): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Summary Metrics -->
    <div class="metrics-strip" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 1.8rem;">
        <div class="metric-tile">
            <div class="metric-icon-box metric-icon-navy">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </div>
            <div>
                <div class="metric-data-title">Total Accounts</div>
                <div class="metric-data-number"><?php echo $stat_total; ?></div>
            </div>
        </div>

        <div class="metric-tile">
            <div class="metric-icon-box metric-icon-blue">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div>
                <div class="metric-data-title">Students</div>
                <div class="metric-data-number"><?php echo $stat_students; ?></div>
            </div>
        </div>

        <div class="metric-tile">
            <div class="metric-icon-box metric-icon-emerald">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <div>
                <div class="metric-data-title">Org Officers</div>
                <div class="metric-data-number"><?php echo $stat_officers; ?></div>
            </div>
        </div>

        <div class="metric-tile">
            <div class="metric-icon-box" style="background: <?php echo $stat_pending > 0 ? 'var(--amber-50)' : 'var(--slate-100)'; ?>; color: <?php echo $stat_pending > 0 ? 'var(--amber-600)' : 'var(--slate-500)'; ?>;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div>
                <div class="metric-data-title">Pending Activation</div>
                <div class="metric-data-number" style="color: <?php echo $stat_pending > 0 ? 'var(--amber-600)' : 'inherit'; ?>;">
                    <?php echo $stat_pending; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="filter-panel">
        <form method="GET" action="admin_users.php" class="filter-grid" style="grid-template-columns: 2fr 1fr 1fr auto auto;">
            <div class="search-input-wrapper">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search by Student ID, full name, or email...">
            </div>

            <select name="role">
                <option value="all">All Roles</option>
                <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                <option value="officer" <?php echo $role_filter === 'officer' ? 'selected' : ''; ?>>Officer</option>
                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>

            <select name="status">
                <option value="all">All Statuses</option>
                <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Active / Verified</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending / Inactive</option>
            </select>

            <button type="submit" class="btn btn-primary">Filter Users</button>
            <?php if ($search !== '' || $role_filter !== 'all' || $status_filter !== 'all'): ?>
                <a href="admin_users.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Users Table Card -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 1.1rem 1.5rem; background: var(--slate-50); border-bottom: 1px solid var(--slate-200); display: flex; justify-content: space-between; align-items: center;">
            <div style="font-weight: 700; font-size: 0.95rem; color: var(--navy-900);">
                System Users Directory (<?php echo count($users); ?> records)
            </div>
        </div>

        <?php if (empty($users)): ?>
            <div style="padding: 4rem 1.5rem; text-align: center; color: var(--slate-500);">
                <p>No user records matched your criteria.</p>
                <a href="admin_users.php" class="btn btn-secondary btn-sm" style="margin-top: 0.5rem;">Reset Filter</a>
            </div>
        <?php else: ?>
            <div class="table-wrapper" style="border: none; border-radius: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>University Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registrations</th>
                            <th>Joined Date</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): 
                            $isSelf = ($u['user_id'] === current_user_id());
                        ?>
                            <tr>
                                <td>
                                    <span style="font-family: monospace; font-weight: 700; color: var(--navy-900); font-size: 0.9rem;">
                                        <?php echo e($u['student_id']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--navy-900); font-size: 0.92rem;">
                                        <?php echo e($u['first_name'] . ' ' . $u['last_name']); ?>
                                        <?php if ($isSelf): ?>
                                            <span style="font-size: 0.7rem; background: var(--blue-100); color: var(--blue-800); padding: 0.1rem 0.4rem; border-radius: 4px; margin-left: 0.3rem;">You</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="font-size: 0.88rem; color: var(--slate-600);">
                                    <?php echo e($u['email']); ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $u['role'] === 'admin' ? 'status-open' : ($u['role'] === 'officer' ? 'status-pending' : 'status-closed'); ?>" style="font-size: 0.72rem;">
                                        <?php echo strtoupper(e($u['role'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['is_verified']): ?>
                                        <span class="status-badge status-open" style="font-size: 0.72rem;">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge status-closed" style="font-size: 0.72rem;">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.88rem; font-weight: 600;">
                                    <?php echo $u['registration_count']; ?>
                                </td>
                                <td style="font-size: 0.82rem; color: var(--slate-500); white-space: nowrap;">
                                    <?php echo date('M j, Y', strtotime($u['created_at'])); ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                                        <!-- Edit Modal Trigger -->
                                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;" onclick='openEditModal(<?php echo json_encode($u); ?>)'>
                                            Edit
                                        </button>

                                        <?php if (!$isSelf): ?>
                                            <!-- Toggle Status Form -->
                                            <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('<?php echo $u['is_verified'] ? 'Deactivate this student account?' : 'Activate and verify this student account?'; ?>');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                                <button type="submit" class="btn btn-sm <?php echo $u['is_verified'] ? 'btn-secondary' : 'btn-primary'; ?>" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;">
                                                    <?php echo $u['is_verified'] ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>

                                            <!-- Delete User Form -->
                                            <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('WARNING: Are you sure you want to permanently delete this user account? All their registrations will also be removed.');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;">
                                                    Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for Editing User -->
<div id="editUserModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 1000; align-items: center; justify-content: center; padding: 1.5rem;">
    <div class="card" style="max-width: 500px; width: 100%; padding: 2rem; background: #ffffff; box-shadow: var(--shadow-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.3rem; color: var(--navy-900); margin: 0;">Edit User Information</h3>
            <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 1.5rem; line-height: 1; cursor: pointer; color: var(--slate-400);">&times;</button>
        </div>

        <form method="POST" action="admin_users.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="edit_user_id">

            <div class="form-group">
                <label>Student ID Number <span style="color: var(--danger-dot);">*</span></label>
                <input type="text" name="student_id" id="edit_student_id" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>First Name <span style="color: var(--danger-dot);">*</span></label>
                    <input type="text" name="first_name" id="edit_first_name" required>
                </div>
                <div class="form-group">
                    <label>Last Name <span style="color: var(--danger-dot);">*</span></label>
                    <input type="text" name="last_name" id="edit_last_name" required>
                </div>
            </div>

            <div class="form-group">
                <label>University Email <span style="color: var(--danger-dot);">*</span></label>
                <input type="email" name="email" id="edit_email" required>
            </div>

            <div class="form-group">
                <label>System Role <span style="color: var(--danger-dot);">*</span></label>
                <select name="role" id="edit_role" required>
                    <option value="student">Student</option>
                    <option value="officer">Organization Officer</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(user) {
    document.getElementById('edit_user_id').value = user.user_id;
    document.getElementById('edit_student_id').value = user.student_id;
    document.getElementById('edit_first_name').value = user.first_name;
    document.getElementById('edit_last_name').value = user.last_name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    
    const modal = document.getElementById('editUserModal');
    modal.style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

// Close when clicking outside modal card
window.addEventListener('click', function(e) {
    const modal = document.getElementById('editUserModal');
    if (e.target === modal) {
        closeEditModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
