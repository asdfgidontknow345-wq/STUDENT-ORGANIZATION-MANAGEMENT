<?php
require_once '../config/db_connect.php';
require_admin(); // Strict RBAC access control

$search = trim($_GET['search'] ?? '');
$event_filter = intval($_GET['event_id'] ?? 0);

// Handle Registration Cancellation by Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $reg_id = intval($_POST['reg_id'] ?? 0);

    if ($action === 'cancel_registration' && $reg_id > 0) {
        try {
            $del = $conn->prepare("DELETE FROM registrations WHERE reg_id = ?");
            $del->execute([$reg_id]);
            set_flash('success', 'Student registration has been cancelled successfully.');
        } catch (PDOException $e) {
            set_flash('error', 'Failed to cancel registration: ' . $e->getMessage());
        }
        $redirectUrl = 'admin_registrations.php' . ($event_filter > 0 ? "?event_id=$event_filter" : "");
        redirect($redirectUrl);
    }
}

// Build query
$query = "
    SELECT r.reg_id, r.registration_time,
           u.user_id, u.student_id, u.first_name, u.last_name, u.email,
           e.event_id, e.event_title, e.event_date, e.event_venue,
           o.org_name
    FROM registrations r
    JOIN users u ON r.user_id = u.user_id
    JOIN events e ON r.event_id = e.event_id
    JOIN organizations o ON e.org_id = o.org_id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (u.student_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR e.event_title LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($event_filter > 0) {
    $query .= " AND r.event_id = ?";
    $params[] = $event_filter;
}

$query .= " ORDER BY r.registration_time DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$registrations = $stmt->fetchAll();

// Events list for filter
$all_events = $conn->query("SELECT event_id, event_title FROM events ORDER BY event_date DESC")->fetchAll();

$total_reg_count = count($registrations);

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.8rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; font-weight: 700; color: var(--blue-600); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                Registration Logs &amp; Attendance
            </div>
            <h1 style="font-size: 2.1rem; color: var(--navy-900); letter-spacing: -0.02em;">Event Registrations Master List</h1>
            <p style="color: var(--slate-500); font-size: 0.95rem; margin-top: 0.2rem;">
                Live database of all student ticket bookings, attendance confirmations, and capacity utilization.
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <?php if ($event_filter > 0): ?>
                <a href="print_attendees.php?event_id=<?php echo $event_filter; ?>" target="_blank" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
                    Print Attendee Roster
                </a>
                <a href="export_attendees.php?event_id=<?php echo $event_filter; ?>" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export CSV (This Event)
                </a>
            <?php else: ?>
                <a href="export_attendees.php?all=1" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export All to CSV
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($flash = get_flash()): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Filter Panel -->
    <div class="filter-panel">
        <form method="GET" action="admin_registrations.php" class="filter-grid" style="grid-template-columns: 2fr 1.5fr auto auto;">
            <div class="search-input-wrapper">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search student name, student ID, email, or event title...">
            </div>

            <select name="event_id">
                <option value="0">All Campus Events</option>
                <?php foreach ($all_events as $ev): ?>
                    <option value="<?php echo $ev['event_id']; ?>" <?php echo $event_filter == $ev['event_id'] ? 'selected' : ''; ?>>
                        <?php echo e($ev['event_title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($search !== '' || $event_filter > 0): ?>
                <a href="admin_registrations.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Registrations Table Card -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 1.1rem 1.5rem; background: var(--slate-50); border-bottom: 1px solid var(--slate-200); display: flex; justify-content: space-between; align-items: center;">
            <div style="font-weight: 700; font-size: 0.95rem; color: var(--navy-900);">
                Active Registrations Log (<?php echo count($registrations); ?> entries)
            </div>
            <span class="status-badge status-open" style="font-size: 0.78rem;">
                Real-Time Verified
            </span>
        </div>

        <?php if (empty($registrations)): ?>
            <div style="padding: 4rem 1.5rem; text-align: center; color: var(--slate-500);">
                <p>No registration records found for this query.</p>
                <?php if ($search !== '' || $event_filter > 0): ?>
                    <a href="admin_registrations.php" class="btn btn-secondary btn-sm" style="margin-top: 0.5rem;">Reset Filter</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrapper" style="border: none; border-radius: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Full Name</th>
                            <th>Email Address</th>
                            <th>Event Registered</th>
                            <th>Organization</th>
                            <th>Registration Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $reg): ?>
                            <tr>
                                <td>
                                    <span style="font-family: monospace; font-weight: 700; color: var(--navy-900); font-size: 0.88rem;">
                                        <?php echo e($reg['student_id']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--navy-900); font-size: 0.92rem;">
                                        <?php echo e($reg['first_name'] . ' ' . $reg['last_name']); ?>
                                    </div>
                                </td>
                                <td style="font-size: 0.88rem; color: var(--slate-600);">
                                    <?php echo e($reg['email']); ?>
                                </td>
                                <td>
                                    <a href="event_details.php?id=<?php echo $reg['event_id']; ?>" style="font-weight: 600; color: var(--blue-700); font-size: 0.9rem;">
                                        <?php echo e($reg['event_title']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo render_org_badge($reg['org_name'], 20, 'font-size: 0.72rem;'); ?>
                                </td>
                                <td style="font-size: 0.82rem; color: var(--slate-500); white-space: nowrap;">
                                    <?php echo date('M j, Y h:i A', strtotime($reg['registration_time'])); ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Cancel this student registration for <?php echo addslashes($reg['event_title']); ?>?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="cancel_registration">
                                        <input type="hidden" name="reg_id" value="<?php echo $reg['reg_id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;">
                                            Cancel Reg
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
