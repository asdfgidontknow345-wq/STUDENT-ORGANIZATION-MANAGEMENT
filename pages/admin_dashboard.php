<?php
require_once '../config/db_connect.php';
require_admin(); // Strict RBAC access control

// Compute Real Dashboard Metrics
$total_students = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$total_orgs = $conn->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
$total_events = $conn->query("SELECT COUNT(*) FROM events")->fetchColumn();
$total_registrations = $conn->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
$pending_users = $conn->query("SELECT COUNT(*) FROM users WHERE is_verified = 0")->fetchColumn();
$open_events = $conn->query("SELECT COUNT(*) FROM events WHERE registration_open = 1")->fetchColumn();

// Upcoming Events (next 5)
$upcoming_events = $conn->query("
    SELECT e.*, o.org_name, 
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id) as registered_count 
    FROM events e 
    JOIN organizations o ON e.org_id = o.org_id 
    ORDER BY e.event_date ASC 
    LIMIT 5
")->fetchAll();

// Recent Registrations (latest 6)
$recent_registrations = $conn->query("
    SELECT r.reg_id, r.registration_time, u.student_id, u.first_name, u.last_name, u.email, e.event_title, e.event_id
    FROM registrations r
    JOIN users u ON r.user_id = u.user_id
    JOIN events e ON r.event_id = e.event_id
    ORDER BY r.registration_time DESC
    LIMIT 6
")->fetchAll();

// Recent User Signups (latest 5)
$recent_users = $conn->query("
    SELECT user_id, student_id, first_name, last_name, email, role, is_verified, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container">
    <!-- Top Welcome Banner -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; font-weight: 700; color: var(--blue-600); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                System Overview &amp; Control Center
            </div>
            <h1 style="font-size: 2.15rem; color: var(--navy-900); letter-spacing: -0.025em;">
                Admin Management Dashboard
            </h1>
            <p style="color: var(--slate-500); font-size: 0.95rem; margin-top: 0.2rem;">
                Logged in as <strong><?php echo e($_SESSION['name'] ?? 'Administrator'); ?></strong>. Real-time platform metrics and campus activity monitoring.
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <a href="create_event.php" class="btn btn-primary" style="box-shadow: var(--shadow-subtle);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Post New Event
            </a>
            <a href="admin_organizations.php" class="btn btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                Add Organization
            </a>
            <a href="export_attendees.php?all=1" class="btn btn-secondary" title="Export all registrations to CSV">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export CSV
            </a>
        </div>
    </div>

    <!-- Quick Stat Cards -->
    <div class="metrics-strip" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 2rem;">
        <div class="metric-tile">
            <div class="metric-icon-box metric-icon-navy">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div>
                <div class="metric-data-title">Registered Students</div>
                <div class="metric-data-number"><?php echo $total_students; ?></div>
            </div>
        </div>

        <div class="metric-tile">
            <div class="metric-icon-box metric-icon-blue">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <div class="metric-data-title">Student Orgs</div>
                <div class="metric-data-number"><?php echo $total_orgs; ?></div>
            </div>
        </div>

        <div class="metric-tile">
            <div class="metric-icon-box metric-icon-emerald">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            </div>
            <div>
                <div class="metric-data-title">Total Events</div>
                <div class="metric-data-number"><?php echo $total_events; ?></div>
            </div>
        </div>

        <div class="metric-tile">
            <div class="metric-icon-box metric-icon-navy">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
            <div>
                <div class="metric-data-title">Total Registrations</div>
                <div class="metric-data-number"><?php echo $total_registrations; ?></div>
            </div>
        </div>

        <div class="metric-tile">
            <div class="metric-icon-box" style="background: <?php echo $pending_users > 0 ? 'var(--amber-50)' : 'var(--slate-100)'; ?>; color: <?php echo $pending_users > 0 ? 'var(--amber-600)' : 'var(--slate-500)'; ?>;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div>
                <div class="metric-data-title">Pending Verifications</div>
                <div class="metric-data-number" style="color: <?php echo $pending_users > 0 ? 'var(--amber-600)' : 'inherit'; ?>;">
                    <?php echo $pending_users; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Two-Column Grid -->
    <div style="display: grid; grid-template-columns: 1.6fr 1.2fr; gap: 1.5rem; margin-bottom: 2rem;">
        
        <!-- Left Column: Upcoming Events & Management -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="padding: 1.2rem 1.5rem; background: var(--slate-50); border-bottom: 1px solid var(--slate-200); display: flex; justify-content: space-between; align-items: center;">
                <div style="font-weight: 700; font-size: 0.95rem; color: var(--navy-900); display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                    Upcoming Events Schedule
                </div>
                <a href="admin_events.php" style="font-size: 0.85rem; font-weight: 600; color: var(--blue-600);">View All Events &rarr;</a>
            </div>

            <?php if (empty($upcoming_events)): ?>
                <div style="padding: 3rem 1.5rem; text-align: center; color: var(--slate-500);">
                    No events scheduled yet. <a href="create_event.php">Create the first event</a>.
                </div>
            <?php else: ?>
                <div class="table-wrapper" style="border: none; border-radius: 0;">
                    <table>
                        <thead>
                            <tr>
                                <th>Event Title &amp; Org</th>
                                <th>Date</th>
                                <th>Booked / Cap</th>
                                <th>Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_events as $ev): 
                                $isFull = ($ev['max_participants'] > 0 && $ev['registered_count'] >= $ev['max_participants']);
                                $isOpen = ($ev['registration_open'] == 1 && !$isFull);
                            ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: var(--navy-900); font-size: 0.92rem;">
                                            <a href="event_details.php?id=<?php echo $ev['event_id']; ?>">
                                                <?php echo e($ev['event_title']); ?>
                                            </a>
                                        </div>
                                        <?php echo render_org_badge($ev['org_name'], 20, 'font-size: 0.72rem;'); ?>
                                    </td>
                                    <td style="font-size: 0.85rem; color: var(--slate-600); white-space: nowrap;">
                                        <?php echo date('M j, Y', strtotime($ev['event_date'])); ?>
                                    </td>
                                    <td style="font-size: 0.85rem;">
                                        <strong><?php echo $ev['registered_count']; ?></strong>
                                        <span style="color: var(--slate-400);">/ <?php echo $ev['max_participants'] > 0 ? $ev['max_participants'] : '∞'; ?></span>
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
                                        <a href="edit_event.php?id=<?php echo $ev['event_id']; ?>" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;">Edit</a>
                                        <a href="event_details.php?id=<?php echo $ev['event_id']; ?>" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.55rem; font-size: 0.78rem;">Roster</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Recent Activity Feed (Registrations & Signups) -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Recent Registrations -->
            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="padding: 1.1rem 1.35rem; background: var(--slate-50); border-bottom: 1px solid var(--slate-200); display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-weight: 700; font-size: 0.92rem; color: var(--navy-900); display: flex; align-items: center; gap: 0.45rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        Recent Event Registrations
                    </div>
                    <a href="admin_registrations.php" style="font-size: 0.82rem; font-weight: 600; color: var(--blue-600);">View All &rarr;</a>
                </div>

                <div style="padding: 0.75rem 1.25rem;">
                    <?php if (empty($recent_registrations)): ?>
                        <div style="padding: 1.5rem 0; text-align: center; color: var(--slate-400); font-size: 0.88rem;">
                            No student registrations logged yet.
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($recent_registrations as $r): ?>
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 0.75rem; border-bottom: 1px solid var(--slate-100); font-size: 0.85rem;">
                                    <div>
                                        <div style="font-weight: 600; color: var(--navy-900);">
                                            <?php echo e($r['first_name'] . ' ' . $r['last_name']); ?>
                                            <span style="font-weight: normal; color: var(--slate-500); font-size: 0.78rem;">(<?php echo e($r['student_id']); ?>)</span>
                                        </div>
                                        <div style="color: var(--blue-700); font-size: 0.82rem; margin-top: 0.1rem;">
                                            <?php echo e($r['event_title']); ?>
                                        </div>
                                    </div>
                                    <span style="font-size: 0.75rem; color: var(--slate-400); white-space: nowrap;">
                                        <?php echo date('M j, g:i A', strtotime($r['registration_time'])); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent User Accounts -->
            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="padding: 1.1rem 1.35rem; background: var(--slate-50); border-bottom: 1px solid var(--slate-200); display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-weight: 700; font-size: 0.92rem; color: var(--navy-900); display: flex; align-items: center; gap: 0.45rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Recent Accounts
                    </div>
                    <a href="admin_users.php" style="font-size: 0.82rem; font-weight: 600; color: var(--blue-600);">Manage Users &rarr;</a>
                </div>

                <div style="padding: 0.75rem 1.25rem;">
                    <?php foreach ($recent_users as $u): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--slate-100); font-size: 0.85rem;">
                            <div>
                                <span style="font-weight: 600; color: var(--navy-900);">
                                    <?php echo e($u['first_name'] . ' ' . $u['last_name']); ?>
                                </span>
                                <div style="font-size: 0.78rem; color: var(--slate-500);"><?php echo e($u['email']); ?></div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.4rem;">
                                <span class="status-badge <?php echo $u['role'] === 'admin' ? 'status-open' : 'status-pending'; ?>" style="font-size: 0.68rem; padding: 0.1rem 0.4rem;">
                                    <?php echo strtoupper(e($u['role'])); ?>
                                </span>
                                <?php if ($u['is_verified']): ?>
                                    <span style="color: var(--emerald-600); font-size: 0.75rem; font-weight: 600;">Active</span>
                                <?php else: ?>
                                    <span style="color: var(--amber-600); font-size: 0.75rem; font-weight: 600;">Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
