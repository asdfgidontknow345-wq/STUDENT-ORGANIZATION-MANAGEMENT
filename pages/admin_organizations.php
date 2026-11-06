<?php
require_once '../config/db_connect.php';
require_admin(); // Strict RBAC access control

$search = trim($_GET['search'] ?? '');

// Handle POST actions (Add, Edit, Delete Organization)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_org') {
        $org_name = trim($_POST['org_name'] ?? '');
        $org_description = trim($_POST['org_description'] ?? '');
        $officer_id = !empty($_POST['officer_id']) ? intval($_POST['officer_id']) : null;

        if (empty($org_name)) {
            set_flash('error', 'Organization name is required.');
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO organizations (org_name, org_description, officer_id) VALUES (?, ?, ?)");
                $stmt->execute([$org_name, $org_description, $officer_id]);
                set_flash('success', 'Student organization added successfully.');
            } catch (PDOException $e) {
                set_flash('error', 'Failed to add organization: ' . $e->getMessage());
            }
        }
        redirect('admin_organizations.php');
    } elseif ($action === 'edit_org') {
        $org_id = intval($_POST['org_id'] ?? 0);
        $org_name = trim($_POST['org_name'] ?? '');
        $org_description = trim($_POST['org_description'] ?? '');
        $officer_id = !empty($_POST['officer_id']) ? intval($_POST['officer_id']) : null;

        if ($org_id <= 0 || empty($org_name)) {
            set_flash('error', 'Invalid organization details.');
        } else {
            try {
                $stmt = $conn->prepare("UPDATE organizations SET org_name = ?, org_description = ?, officer_id = ? WHERE org_id = ?");
                $stmt->execute([$org_name, $org_description, $officer_id, $org_id]);
                set_flash('success', 'Organization updated successfully.');
            } catch (PDOException $e) {
                set_flash('error', 'Failed to update organization: ' . $e->getMessage());
            }
        }
        redirect('admin_organizations.php');
    } elseif ($action === 'delete_org') {
        $org_id = intval($_POST['org_id'] ?? 0);
        if ($org_id <= 0) {
            set_flash('error', 'Invalid organization ID.');
        } else {
            try {
                $stmt = $conn->prepare("DELETE FROM organizations WHERE org_id = ?");
                $stmt->execute([$org_id]);
                set_flash('success', 'Organization and associated events removed.');
            } catch (PDOException $e) {
                set_flash('error', 'Failed to delete organization: ' . $e->getMessage());
            }
        }
        redirect('admin_organizations.php');
    }
}

// Fetch all organizations with event count and officer name
$query = "
    SELECT o.*, 
           (SELECT COUNT(*) FROM events e WHERE e.org_id = o.org_id) as event_count,
           u.first_name as officer_first, u.last_name as officer_last, u.email as officer_email, u.student_id as officer_student_id
    FROM organizations o
    LEFT JOIN users u ON o.officer_id = u.user_id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (o.org_name LIKE ? OR o.org_description LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
}

$query .= " ORDER BY o.org_name ASC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$organizations = $stmt->fetchAll();

// Fetch potential officers (users with role officer or student)
$officers = $conn->query("
    SELECT user_id, student_id, first_name, last_name, email, role 
    FROM users 
    WHERE role IN ('officer', 'student', 'admin') 
    ORDER BY first_name ASC
")->fetchAll();

$total_orgs = count($organizations);
$total_events_all = $conn->query("SELECT COUNT(*) FROM events")->fetchColumn();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.8rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; font-weight: 700; color: var(--blue-600); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Campus Organizations Directory
            </div>
            <h1 style="font-size: 2.1rem; color: var(--navy-900); letter-spacing: -0.02em;">Organization Management</h1>
            <p style="color: var(--slate-500); font-size: 0.95rem; margin-top: 0.2rem;">
                Register and oversee accredited academic bodies, student councils, and societies.
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <button type="button" class="btn btn-primary" onclick="openAddModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Add New Organization
            </button>
            <a href="admin_dashboard.php" class="btn btn-secondary">
                Dashboard
            </a>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($flash = get_flash()): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Search Panel -->
    <div class="filter-panel">
        <form method="GET" action="admin_organizations.php" class="filter-grid" style="grid-template-columns: 1fr auto auto;">
            <div class="search-input-wrapper">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search organization name or description...">
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="admin_organizations.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Organizations Table Card -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 1.1rem 1.5rem; background: var(--slate-50); border-bottom: 1px solid var(--slate-200); display: flex; justify-content: space-between; align-items: center;">
            <div style="font-weight: 700; font-size: 0.95rem; color: var(--navy-900);">
                Accredited Organizations (<?php echo count($organizations); ?> total)
            </div>
        </div>

        <?php if (empty($organizations)): ?>
            <div style="padding: 4rem 1.5rem; text-align: center; color: var(--slate-500);">
                <p>No organizations found. Click "Add New Organization" to create one.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper" style="border: none; border-radius: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Organization Name</th>
                            <th>Description</th>
                            <th>Assigned Officer</th>
                            <th>Associated Events</th>
                            <th>Date Created</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($organizations as $org): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--navy-900); font-size: 0.95rem;">
                                        <?php echo e($org['org_name']); ?>
                                    </div>
                                    <span style="font-size: 0.75rem; color: var(--slate-400); font-family: monospace;">ORG #<?php echo $org['org_id']; ?></span>
                                </td>
                                <td style="max-width: 320px; font-size: 0.88rem; color: var(--slate-600); line-height: 1.5;">
                                    <?php echo e($org['org_description'] ?: 'No description provided.'); ?>
                                </td>
                                <td>
                                    <?php if (!empty($org['officer_first'])): ?>
                                        <div style="font-weight: 600; color: var(--navy-900); font-size: 0.88rem;">
                                            <?php echo e($org['officer_first'] . ' ' . $org['officer_last']); ?>
                                        </div>
                                        <div style="font-size: 0.76rem; color: var(--slate-500);"><?php echo e($org['officer_email']); ?></div>
                                    <?php else: ?>
                                        <span style="color: var(--slate-400); font-size: 0.82rem; font-style: italic;">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="admin_events.php?org_id=<?php echo $org['org_id']; ?>" style="font-weight: 700; color: var(--blue-600); font-size: 0.92rem;">
                                        <?php echo $org['event_count']; ?> event<?php echo $org['event_count'] != 1 ? 's' : ''; ?>
                                    </a>
                                </td>
                                <td style="font-size: 0.82rem; color: var(--slate-500); white-space: nowrap;">
                                    <?php echo date('M j, Y', strtotime($org['created_at'])); ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;" onclick='openEditModal(<?php echo json_encode($org); ?>)'>
                                            Edit
                                        </button>
                                        <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('WARNING: Deleting this organization will also delete all events and registrations associated with it. Continue?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_org">
                                            <input type="hidden" name="org_id" value="<?php echo $org['org_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;">
                                                Delete
                                            </button>
                                        </form>
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

<!-- Modal for Adding Organization -->
<div id="addOrgModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 1000; align-items: center; justify-content: center; padding: 1.5rem;">
    <div class="card" style="max-width: 520px; width: 100%; padding: 2rem; background: #ffffff; box-shadow: var(--shadow-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.3rem; color: var(--navy-900); margin: 0;">Add New Student Organization</h3>
            <button type="button" onclick="closeAddModal()" style="background: none; border: none; font-size: 1.5rem; line-height: 1; cursor: pointer; color: var(--slate-400);">&times;</button>
        </div>

        <form method="POST" action="admin_organizations.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_org">

            <div class="form-group">
                <label>Organization Name <span style="color: var(--danger-dot);">*</span></label>
                <input type="text" name="org_name" placeholder="e.g. Computer Science Society (CSS)" required>
            </div>

            <div class="form-group">
                <label>Description / Mission</label>
                <textarea name="org_description" rows="3" placeholder="Brief outline of the organization's charter and goals..."></textarea>
            </div>

            <div class="form-group">
                <label>Assign Officer / Head (Optional)</label>
                <select name="officer_id">
                    <option value="">-- No officer assigned yet --</option>
                    <?php foreach ($officers as $off): ?>
                        <option value="<?php echo $off['user_id']; ?>">
                            <?php echo e($off['first_name'] . ' ' . $off['last_name'] . ' (' . $off['student_id'] . ') - ' . ucfirst($off['role'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" onclick="closeAddModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Organization</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal for Editing Organization -->
<div id="editOrgModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 1000; align-items: center; justify-content: center; padding: 1.5rem;">
    <div class="card" style="max-width: 520px; width: 100%; padding: 2rem; background: #ffffff; box-shadow: var(--shadow-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.3rem; color: var(--navy-900); margin: 0;">Edit Organization</h3>
            <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 1.5rem; line-height: 1; cursor: pointer; color: var(--slate-400);">&times;</button>
        </div>

        <form method="POST" action="admin_organizations.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="edit_org">
            <input type="hidden" name="org_id" id="edit_org_id">

            <div class="form-group">
                <label>Organization Name <span style="color: var(--danger-dot);">*</span></label>
                <input type="text" name="org_name" id="edit_org_name" required>
            </div>

            <div class="form-group">
                <label>Description / Mission</label>
                <textarea name="org_description" id="edit_org_description" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label>Assign Officer / Head (Optional)</label>
                <select name="officer_id" id="edit_officer_id">
                    <option value="">-- No officer assigned --</option>
                    <?php foreach ($officers as $off): ?>
                        <option value="<?php echo $off['user_id']; ?>">
                            <?php echo e($off['first_name'] . ' ' . $off['last_name'] . ' (' . $off['student_id'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
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
function openAddModal() {
    document.getElementById('addOrgModal').style.display = 'flex';
}
function closeAddModal() {
    document.getElementById('addOrgModal').style.display = 'none';
}

function openEditModal(org) {
    document.getElementById('edit_org_id').value = org.org_id;
    document.getElementById('edit_org_name').value = org.org_name;
    document.getElementById('edit_org_description').value = org.org_description || '';
    document.getElementById('edit_officer_id').value = org.officer_id || '';
    document.getElementById('editOrgModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editOrgModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('addOrgModal')) closeAddModal();
    if (e.target === document.getElementById('editOrgModal')) closeEditModal();
});
</script>

<?php include '../includes/footer.php'; ?>
