<?php
require_once '../config/db_connect.php';
include '../includes/header.php';
include '../includes/navbar.php';

// Fetch quick stats
$org_count = $conn->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
$event_count = $conn->query("SELECT COUNT(*) FROM events WHERE registration_open = 1")->fetchColumn();
$reg_total = $conn->query("SELECT COUNT(*) FROM registrations")->fetchColumn();

// If user is logged in, count their registrations and fetch their registered IDs
$my_reg_count = 0;
$user_registered_event_ids = [];
if (is_logged_in()) {
    $my_stmt = $conn->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = ?");
    $my_stmt->execute([current_user_id()]);
    $my_reg_count = $my_stmt->fetchColumn();

    $reg_ids_stmt = $conn->prepare("SELECT event_id FROM registrations WHERE user_id = ?");
    $reg_ids_stmt->execute([current_user_id()]);
    $user_registered_event_ids = $reg_ids_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Fetch upcoming events with registration counts
$upcoming = $conn->query("
    SELECT e.*, o.org_name, 
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id) as registered_count 
    FROM events e 
    JOIN organizations o ON e.org_id = o.org_id 
    ORDER BY e.event_date ASC 
    LIMIT 3
")->fetchAll();

// Fetch organizations
$orgs = $conn->query("SELECT * FROM organizations ORDER BY org_name ASC")->fetchAll();
?>

<div class="container">
    <!-- Hero Banner Card -->
    <div class="card" style="background: linear-gradient(135deg, var(--navy-900) 0%, var(--navy-800) 50%, var(--navy-700) 100%); color: #ffffff; padding: 3rem 2.5rem; border: none; margin-bottom: 2rem;">
        <div style="max-width: 680px;">
            <div style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.35rem 0.8rem; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: var(--radius-full); font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--blue-100); margin-bottom: 1.25rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                University Student Affairs Portal
            </div>

            <?php if (is_logged_in()): ?>
                <h1 style="font-size: 2.3rem; color: #ffffff; letter-spacing: -0.025em; line-height: 1.2; margin-bottom: 0.75rem;">
                    Welcome back, <?php echo e($_SESSION['first_name'] ?? 'Student'); ?>!
                </h1>
                <p style="color: var(--slate-300); font-size: 1.05rem; line-height: 1.6; margin-bottom: 2rem;">
                    Discover campus events, reserve entry tickets for academic seminars and club workshops, and view your verified registration passes.
                </p>
            <?php else: ?>
                <h1 style="font-size: 2.3rem; color: #ffffff; letter-spacing: -0.025em; line-height: 1.2; margin-bottom: 0.75rem;">
                    Empowering Student Organizations &amp; Campus Events
                </h1>
                <p style="color: var(--slate-300); font-size: 1.05rem; line-height: 1.6; margin-bottom: 2rem;">
                    The official university platform to discover accredited student organizations, reserve tickets for summits and workshops, and connect with academic leaders.
                </p>
            <?php endif; ?>

            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="events.php" class="btn btn-primary" style="padding: 0.75rem 1.6rem; font-size: 0.95rem;">
                    Browse Campus Events &rarr;
                </a>
                <?php if (!is_logged_in()): ?>
                    <a href="../auth/register.php" class="btn btn-secondary" style="padding: 0.75rem 1.6rem; font-size: 0.95rem; background: rgba(255, 255, 255, 0.12); color: #ffffff !important; border-color: rgba(255, 255, 255, 0.25);">
                        Create Student Account
                    </a>
                <?php elseif (is_admin()): ?>
                    <a href="admin_dashboard.php" class="btn btn-secondary" style="padding: 0.75rem 1.6rem; font-size: 0.95rem; background: rgba(255, 255, 255, 0.12); color: #ffffff !important; border-color: rgba(255, 255, 255, 0.25);">
                        Admin Dashboard &rarr;
                    </a>
                <?php else: ?>
                    <a href="my_registrations.php" class="btn btn-secondary" style="padding: 0.75rem 1.6rem; font-size: 0.95rem; background: rgba(255, 255, 255, 0.12); color: #ffffff !important; border-color: rgba(255, 255, 255, 0.25);">
                        My Registrations (<?php echo $my_reg_count; ?>)
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Summary KPI Metrics -->
    <div class="metrics-strip">
        <div class="metric-tile">
            <div class="metric-icon-box metric-icon-navy">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            </div>
            <div>
                <div class="metric-data-title">Active Events</div>
                <div class="metric-data-number"><?php echo $event_count; ?></div>
            </div>
        </div>

        <div class="metric-tile">
            <div class="metric-icon-box metric-icon-blue">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <div class="metric-data-title">Recognized Orgs</div>
                <div class="metric-data-number"><?php echo $org_count; ?></div>
            </div>
        </div>

        <div class="metric-tile">
            <div class="metric-icon-box metric-icon-emerald">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div>
                <div class="metric-data-title">Total Registrations</div>
                <div class="metric-data-number"><?php echo $reg_total; ?></div>
            </div>
        </div>
    </div>

    <!-- Featured Upcoming Events -->
    <div style="margin-bottom: 2.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.5rem;">
            <div>
                <span style="font-size: 0.8rem; font-weight: 700; color: var(--blue-600); text-transform: uppercase; letter-spacing: 0.05em;">Featured</span>
                <h2 style="font-size: 1.6rem; color: var(--navy-900); letter-spacing: -0.02em; margin-top: 0.2rem;">Upcoming Campus Events</h2>
            </div>
            <a href="events.php" class="btn btn-secondary btn-sm">
                View All Events &rarr;
            </a>
        </div>

        <div class="events-responsive-grid">
            <?php foreach ($upcoming as $event): 
                $is_full = ($event['max_participants'] > 0 && $event['registered_count'] >= $event['max_participants']);
                $is_open = ($event['registration_open'] == 1 && !$is_full);
                $pct = ($event['max_participants'] > 0) ? min(100, round(($event['registered_count'] / $event['max_participants']) * 100)) : 0;
                $is_user_registered = in_array($event['event_id'], $user_registered_event_ids);
            ?>
                <div class="event-card">
                    <div>
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

                        <h3 class="event-title">
                            <a href="event_details.php?id=<?php echo $event['event_id']; ?>">
                                <?php echo e($event['event_title']); ?>
                            </a>
                        </h3>

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

                        <p class="event-brief"><?php echo e($event['event_description']); ?></p>
                    </div>

                    <div>
                        <div class="slots-bar-container">
                            <div class="slots-bar-label">
                                <span>Slots</span>
                                <span>
                                    <?php if ($event['max_participants'] > 0): ?>
                                        <strong><?php echo $event['registered_count']; ?></strong> / <?php echo $event['max_participants']; ?>
                                    <?php else: ?>
                                        <strong><?php echo $event['registered_count']; ?></strong> (Unlimited)
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ($event['max_participants'] > 0): ?>
                                <div class="slots-track">
                                    <div class="slots-fill <?php echo $pct >= 100 ? 'is-full' : ($pct > 75 ? 'is-high' : ''); ?>" style="width: <?php echo $pct; ?>%;"></div>
                                </div>
                            <?php endif; ?>
                        </div>

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
    </div>

    <!-- Recognized Student Organizations -->
    <div class="card" style="margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div>
                <h2>Recognized Student Organizations</h2>
                <p style="color: var(--slate-500); font-size: 0.9rem;">Accredited campus clubs, academic chapters, and governing councils.</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem;">
            <?php foreach ($orgs as $org): ?>
                <div style="border: 1px solid var(--slate-200); border-radius: var(--radius-md); padding: 1.25rem; background: var(--slate-50); display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="min-width: 38px; height: 38px; padding: 0 0.5rem; border-radius: var(--radius-sm); background: var(--navy-800); color: #ffffff; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.85rem; margin-bottom: 0.75rem; letter-spacing: 0.03em;" title="<?php echo e($org['org_name']); ?>">
                            <?php echo e(get_org_acronym($org['org_name'])); ?>
                        </div>
                        <h3 style="font-size: 1.05rem; margin-bottom: 0.35rem; color: var(--navy-900);"><?php echo e($org['org_name']); ?></h3>
                        <p style="font-size: 0.86rem; color: var(--slate-500); line-height: 1.5;"><?php echo e($org['org_description']); ?></p>
                    </div>
                    <div style="margin-top: 1rem;">
                        <a href="events.php?org_id=<?php echo $org['org_id']; ?>" style="font-size: 0.85rem; font-weight: 700; color: var(--blue-600); display: inline-flex; align-items: center; gap: 0.3rem;">
                            Browse Org Events &rarr;
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>