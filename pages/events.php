<?php
require_once '../config/db_connect.php';
include '../includes/header.php';
include '../includes/navbar.php';

$search = trim($_GET['search'] ?? '');
$org_filter = intval($_GET['org_id'] ?? 0);
$status_filter = trim($_GET['status'] ?? 'all');

// 1. Compute Header Summary KPIs
$total_events = $conn->query("SELECT COUNT(*) FROM events")->fetchColumn();

// Open events query
$open_events = $conn->query("
    SELECT COUNT(*) FROM events e 
    WHERE e.registration_open = 1 
      AND (e.max_participants = 0 OR (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id) < e.max_participants)
")->fetchColumn();

// Upcoming events query
$upcoming_events = $conn->query("
    SELECT COUNT(*) FROM events WHERE event_date >= date('now') OR event_date >= CURRENT_DATE
")->fetchColumn();

// User registration count
$my_registered_count = 0;
$user_registered_event_ids = [];
if (is_logged_in()) {
    $my_stmt = $conn->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = ?");
    $my_stmt->execute([current_user_id()]);
    $my_registered_count = $my_stmt->fetchColumn();

    $reg_ids_stmt = $conn->prepare("SELECT event_id FROM registrations WHERE user_id = ?");
    $reg_ids_stmt->execute([current_user_id()]);
    $user_registered_event_ids = $reg_ids_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// 2. Base query with registrations count
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

$query .= " ORDER BY e.event_date ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Fetch all organizations for the dropdown filter
$all_orgs = $conn->query("SELECT * FROM organizations ORDER BY org_name ASC")->fetchAll();
?>

<div class="container">
    <!-- Header Section -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.8rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; font-weight: 700; color: var(--blue-600); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Campus Event Calendar
            </div>
            <h1 style="font-size: 2.1rem; color: var(--navy-900); letter-spacing: -0.02em;">Discover Campus Events</h1>
            <p style="color: var(--slate-500); font-size: 0.96rem; margin-top: 0.2rem;">
                Explore, register, and engage with university student organizations and activities.
            </p>
        </div>
        <?php if (is_officer_or_admin()): ?>
            <a href="create_event.php" class="btn btn-navy" style="box-shadow: var(--shadow-subtle);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Create Event
            </a>
        <?php endif; ?>
    </div>

    <?php if ($flash = get_flash()): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Summary KPI Metrics Section -->
    <div class="metrics-strip">
        <div class="metric-tile">
            <div class="metric-icon-box metric-icon-navy">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            </div>
            <div>
                <div class="metric-data-title">Total Events</div>
                <div class="metric-data-number"><?php echo $total_events; ?></div>
            </div>
        </div>

        <div class="metric-tile">
            <div class="metric-icon-box metric-icon-emerald">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div>
                <div class="metric-data-title">Open for Registration</div>
                <div class="metric-data-number"><?php echo $open_events; ?></div>
            </div>
        </div>

        <div class="metric-tile">
            <div class="metric-icon-box metric-icon-blue">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 10"/></svg>
            </div>
            <div>
                <div class="metric-data-title">Upcoming Events</div>
                <div class="metric-data-number"><?php echo $upcoming_events; ?></div>
            </div>
        </div>

        <div class="metric-tile">
            <div class="metric-icon-box metric-icon-navy">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <div class="metric-data-title"><?php echo is_logged_in() ? 'My Registrations' : 'Campus Registrations'; ?></div>
                <div class="metric-data-number"><?php echo is_logged_in() ? $my_registered_count : $conn->query("SELECT COUNT(*) FROM registrations")->fetchColumn(); ?></div>
            </div>
        </div>
    </div>

    <!-- Search & Filtering Panel -->
    <div class="filter-panel">
        <form method="GET" class="filter-grid">
            <!-- Keyword search -->
            <div>
                <label>Search Events</label>
                <div class="input-with-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="search" placeholder="Search by title, venue, or keywords..." value="<?php echo e($search); ?>">
                </div>
            </div>

            <!-- Organization filter -->
            <div>
                <label>Student Organization</label>
                <select name="org_id">
                    <option value="0">All Organizations</option>
                    <?php foreach ($all_orgs as $org): ?>
                        <option value="<?php echo $org['org_id']; ?>" <?php echo $org_filter == $org['org_id'] ? 'selected' : ''; ?>>
                            <?php echo e($org['org_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status filter -->
            <div>
                <label>Registration Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open for Registration</option>
                    <option value="full" <?php echo $status_filter === 'full' ? 'selected' : ''; ?>>Full Capacity</option>
                    <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>

            <!-- Action buttons -->
            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="height: 42px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Filter
                </button>
                <?php if ($search !== '' || $org_filter > 0 || $status_filter !== 'all'): ?>
                    <a href="events.php" class="btn btn-secondary" style="height: 42px;" title="Reset all filters">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Results Status Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <span style="font-size: 0.88rem; font-weight: 600; color: var(--slate-500);">
            Showing <strong><?php echo count($events); ?></strong> event(s)
            <?php if ($search !== ''): ?> for keyword "<em><?php echo e($search); ?></em>"<?php endif; ?>
        </span>
    </div>

    <!-- Event Cards Grid (3 columns on desktop, 2 on tablet, 1 on mobile) -->
    <?php if (empty($events)): ?>
        <div class="card" style="text-align: center; padding: 4rem 1.5rem;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--slate-100); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto; color: var(--slate-400);">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
            <h3 style="font-size: 1.25rem; margin-bottom: 0.35rem; color: var(--navy-900);">No matching events found</h3>
            <p style="color: var(--slate-500); font-size: 0.92rem; max-width: 420px; margin: 0 auto 1.5rem auto;">
                Try adjusting your search query, choosing a different organization, or resetting the filters.
            </p>
            <a href="events.php" class="btn btn-secondary">Clear All Filters</a>
        </div>
    <?php else: ?>
        <div class="events-responsive-grid">
            <?php foreach ($events as $event): 
                $is_full = ($event['max_participants'] > 0 && $event['registered_count'] >= $event['max_participants']);
                $is_open = ($event['registration_open'] == 1 && !$is_full);
                $pct = ($event['max_participants'] > 0) ? min(100, round(($event['registered_count'] / $event['max_participants']) * 100)) : 0;
                $is_user_registered = in_array($event['event_id'], $user_registered_event_ids);
                $slots_available = ($event['max_participants'] > 0) ? max(0, $event['max_participants'] - $event['registered_count']) : null;
            ?>
                <div class="event-card">
                    <div>
                        <!-- Organization & Status Badge -->
                        <div class="event-card-top">
                            <?php echo render_org_badge($event['org_name']); ?>

                            <?php if (!$event['registration_open']): ?>
                                <span class="status-badge status-closed">Closed</span>
                            <?php elseif ($is_full): ?>
                                <span class="status-badge status-full">Full</span>
                            <?php else: ?>
                                <span class="status-badge status-open">Open</span>
                            <?php endif; ?>
                        </div>

                        <!-- Event Title -->
                        <h3 class="event-title">
                            <a href="event_details.php?id=<?php echo $event['event_id']; ?>">
                                <?php echo e($event['event_title']); ?>
                            </a>
                        </h3>

                        <!-- Metadata (Date & Venue) -->
                        <div class="event-details-meta">
                            <div class="meta-row">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                                <span><?php echo date('D, M j, Y', strtotime($event['event_date'])); ?></span>
                            </div>
                            <div class="meta-row">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo e($event['event_venue']); ?></span>
                            </div>
                        </div>

                        <!-- Brief Description -->
                        <p class="event-brief"><?php echo e($event['event_description']); ?></p>
                    </div>

                    <!-- Available Slots & Actions -->
                    <div>
                        <div class="slots-bar-container">
                            <div class="slots-bar-label">
                                <span>
                                    <?php if ($slots_available !== null): ?>
                                        <strong style="color: <?php echo $slots_available > 0 ? 'var(--emerald-600)' : 'var(--danger-text)'; ?>;"><?php echo $slots_available; ?> slots left</strong>
                                    <?php else: ?>
                                        <span>Unlimited capacity</span>
                                    <?php endif; ?>
                                </span>
                                <span>
                                    <?php if ($event['max_participants'] > 0): ?>
                                        <strong><?php echo $event['registered_count']; ?></strong> / <?php echo $event['max_participants']; ?>
                                    <?php else: ?>
                                        <strong><?php echo $event['registered_count']; ?></strong> registered
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ($event['max_participants'] > 0): ?>
                                <div class="slots-track">
                                    <div class="slots-fill <?php echo $pct >= 100 ? 'is-full' : ($pct > 75 ? 'is-high' : ''); ?>" style="width: <?php echo $pct; ?>%;"></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Card Buttons -->
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <a href="event_details.php?id=<?php echo $event['event_id']; ?>" class="btn btn-secondary btn-sm" style="flex: 1;">
                                View Details
                            </a>

                            <?php if ($is_user_registered): ?>
                                <span class="status-badge status-open" style="font-size: 0.78rem; padding: 0.45rem 0.7rem;">
                                    Registered ✓
                                </span>
                            <?php elseif ($is_open && is_logged_in()): ?>
                                <form method="POST" action="event_details.php?id=<?php echo $event['event_id']; ?>" style="flex: 1; margin: 0;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="register">
                                    <button type="submit" class="btn btn-primary btn-sm btn-block" onclick="return confirm('Register for <?php echo htmlspecialchars(addslashes($event['event_title'])); ?>?');">
                                        Register
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>