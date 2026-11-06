<?php
require_once '../config/db_connect.php';
require_admin(); // Strict RBAC access control

$search = trim($_GET['search'] ?? '');
$org_filter = intval($_GET['org_id'] ?? 0);
$status_filter = trim($_GET['status'] ?? 'all');

// Handle Event Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $event_id = intval($_POST['event_id'] ?? 0);

    if ($action === 'delete_event' && $event_id > 0) {
        try {
            $del = $conn->prepare("DELETE FROM events WHERE event_id = ?");
            $del->execute([$event_id]);
            set_flash('success', 'Event and its registration records were successfully deleted.');
        } catch (PDOException $e) {
            set_flash('error', 'Failed to delete event: ' . $e->getMessage());
        }
        redirect('admin_events.php');
    } elseif ($action === 'toggle_registration' && $event_id > 0) {
        $stmt = $conn->prepare("SELECT registration_open FROM events WHERE event_id = ?");
        $stmt->execute([$event_id]);
        $ev = $stmt->fetch();
        if ($ev) {
            $new_val = $ev['registration_open'] ? 0 : 1;
            $upd = $conn->prepare("UPDATE events SET registration_open = ? WHERE event_id = ?");
            $upd->execute([$new_val, $event_id]);
            set_flash('success', $new_val ? 'Event registration is now OPEN.' : 'Event registration is now CLOSED.');
        }
        redirect('admin_events.php');
    }
}

// Build Query
$query = "
    SELECT e.*, o.org_name,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id) as registered_count 
    FROM events e 
    JOIN organizations o ON e.org_id = o.org_id 
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (e.event_title LIKE ? OR e.event_description LIKE ? OR e.event_venue LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($org_filter > 0) {
    $query .= " AND e.org_id = ?";
    $params[] = $org_filter;
}

if ($status_filter === 'open') {
    $query .= " AND e.registration_open = 1 AND (e.max_participants = 0 OR (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id) < e.max_participants)";
} elseif ($status_filter === 'full') {
    $query .= " AND e.max_participants > 0 AND (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id) >= e.max_participants";
} elseif ($status_filter === 'closed') {
    $query .= " AND e.registration_open = 0";
}

$query .= " ORDER BY e.event_date DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Orgs for filter
$all_orgs = $conn->query("SELECT * FROM organizations ORDER BY org_name ASC")->fetchAll();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.8rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; font-weight: 700; color: var(--blue-600); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                Administration &amp; Scheduling
            </div>
            <h1 style="font-size: 2.1rem; color: var(--navy-900); letter-spacing: -0.02em;">Event Management</h1>
            <p style="color: var(--slate-500); font-size: 0.95rem; margin-top: 0.2rem;">
                Publish new activities, update dates, configure attendee limits, and inspect event rosters.
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="create_event.php" class="btn btn-primary" style="box-shadow: var(--shadow-subtle);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Create New Event
            </a>
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

    <!-- Filter Panel -->
    <div class="filter-panel">
        <form method="GET" action="admin_events.php" class="filter-grid" style="grid-template-columns: 2fr 1.2fr 1fr auto auto;">
            <div class="search-input-wrapper">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search title, description, or venue...">
            </div>

            <select name="org_id">
                <option value="0">All Organizations</option>
                <?php foreach ($all_orgs as $org): ?>
                    <option value="<?php echo $org['org_id']; ?>" <?php echo $org_filter == $org['org_id'] ? 'selected' : ''; ?>>
                        <?php echo e($org['org_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status">
                <option value="all">All Statuses</option>
                <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open for Booking</option>
                <option value="full" <?php echo $status_filter === 'full' ? 'selected' : ''; ?>>Fully Booked</option>
                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Registration Closed</option>
            </select>

            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($search !== '' || $org_filter > 0 || $status_filter !== 'all'): ?>
                <a href="admin_events.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Events Table Card -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 1.1rem 1.5rem; background: var(--slate-50); border-bottom: 1px solid var(--slate-200); display: flex; justify-content: space-between; align-items: center;">
            <div style="font-weight: 700; font-size: 0.95rem; color: var(--navy-900);">
                All Campus Events (<?php echo count($events); ?> total)
            </div>
        </div>

        <?php if (empty($events)): ?>
            <div style="padding: 4rem 1.5rem; text-align: center; color: var(--slate-500);">
                <p>No events found matching your criteria.</p>
                <a href="create_event.php" class="btn btn-primary btn-sm" style="margin-top: 0.5rem;">Create An Event</a>
            </div>
        <?php else: ?>
            <div class="table-wrapper" style="border: none; border-radius: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Event Details &amp; Organization</th>
                            <th>Schedule Date</th>
                            <th>Venue</th>
                            <th>Booked / Cap</th>
                            <th>Status</th>
                            <th style="text-align: right;">Management Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $ev): 
                            $isFull = ($ev['max_participants'] > 0 && $ev['registered_count'] >= $ev['max_participants']);
                            $isOpen = ($ev['registration_open'] == 1 && !$isFull);
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; font-size: 0.95rem; color: var(--navy-900); margin-bottom: 0.2rem;">
                                        <a href="event_details.php?id=<?php echo $ev['event_id']; ?>">
                                            <?php echo e($ev['event_title']); ?>
                                        </a>
                                    </div>
                                    <?php echo render_org_badge($ev['org_name'], 20, 'font-size: 0.72rem;'); ?>
                                </td>
                                <td style="font-size: 0.88rem; color: var(--slate-700); white-space: nowrap;">
                                    <?php echo date('M j, Y', strtotime($ev['event_date'])); ?>
                                </td>
                                <td style="font-size: 0.88rem; color: var(--slate-600);">
                                    <?php echo e($ev['event_venue']); ?>
                                </td>
                                <td style="font-size: 0.88rem;">
                                    <strong><?php echo $ev['registered_count']; ?></strong>
                                    <span style="color: var(--slate-400);">/ <?php echo $ev['max_participants'] > 0 ? $ev['max_participants'] : '∞'; ?></span>
                                    <?php if ($ev['max_participants'] > 0): 
                                        $remaining = max(0, $ev['max_participants'] - $ev['registered_count']);
                                    ?>
                                        <div style="font-size: 0.75rem; color: var(--slate-500);"><?php echo $remaining; ?> slots left</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$ev['registration_open']): ?>
                                        <span class="status-badge status-closed" style="font-size: 0.72rem;">Closed</span>
                                    <?php elseif ($isFull): ?>
                                        <span class="status-badge status-full" style="font-size: 0.72rem;">Full</span>
                                    <?php else: ?>
                                        <span class="status-badge status-open" style="font-size: 0.72rem;">Open</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                                        <!-- Edit -->
                                        <a href="edit_event.php?id=<?php echo $ev['event_id']; ?>" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;">
                                            Edit
                                        </a>

                                        <!-- Toggle Open/Closed -->
                                        <form method="POST" style="display: inline; margin: 0;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_registration">
                                            <input type="hidden" name="event_id" value="<?php echo $ev['event_id']; ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;" title="Toggle Registration">
                                                <?php echo $ev['registration_open'] ? 'Close Reg' : 'Open Reg'; ?>
                                            </button>
                                        </form>

                                        <!-- Attendees Roster -->
                                        <a href="event_details.php?id=<?php echo $ev['event_id']; ?>" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;" title="View Attendee Roster">
                                            Roster (<?php echo $ev['registered_count']; ?>)
                                        </a>

                                        <!-- Export CSV -->
                                        <a href="export_attendees.php?event_id=<?php echo $ev['event_id']; ?>" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;" title="Export CSV">
                                            CSV
                                        </a>

                                        <!-- Delete Event -->
                                        <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Are you sure you want to permanently delete this event and its registrations?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_event">
                                            <input type="hidden" name="event_id" value="<?php echo $ev['event_id']; ?>">
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

<?php include '../includes/footer.php'; ?>
