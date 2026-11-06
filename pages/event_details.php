<?php
require_once '../config/db_connect.php';

$event_id = intval($_GET['id'] ?? 0);
if ($event_id <= 0) {
    redirect('events.php');
}

$error = '';
$success = '';

// Handle Registration, Cancellation, or Admin Attendee Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_logged_in()) {
        redirect('../auth/login.php');
    }
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $current_uid = current_user_id();

    // Check if user is active/verified
    $u_stmt = $conn->prepare("SELECT is_verified, role FROM users WHERE user_id = ?");
    $u_stmt->execute([$current_uid]);
    $currentUserData = $u_stmt->fetch();

    if ($action === 'register') {
        if (!$currentUserData || !$currentUserData['is_verified']) {
            $error = "Your account is not yet verified or active. Please verify your account before registering for campus events.";
        } else {
            try {
                $conn->beginTransaction();

                // Lock and fetch fresh event state
                $chk_stmt = $conn->prepare("
                    SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id) as current_reg 
                    FROM events e 
                    WHERE e.event_id = ?
                ");
                $chk_stmt->execute([$event_id]);
                $ev = $chk_stmt->fetch();

                // Check already registered
                $dup_check = $conn->prepare("SELECT reg_id FROM registrations WHERE user_id = ? AND event_id = ?");
                $dup_check->execute([$current_uid, $event_id]);
                $already_registered = $dup_check->fetch();

                if (!$ev) {
                    $conn->rollBack();
                    $error = "Event not found.";
                } elseif ($already_registered) {
                    $conn->rollBack();
                    $error = "You are already registered for this event.";
                } elseif ($ev['registration_open'] != 1) {
                    $conn->rollBack();
                    $error = "Registration for this event is currently closed.";
                } elseif ($ev['max_participants'] > 0 && $ev['current_reg'] >= $ev['max_participants']) {
                    $conn->rollBack();
                    $error = "Sorry, this event has already reached its maximum participant capacity.";
                } else {
                    $ins = $conn->prepare("INSERT INTO registrations (user_id, event_id) VALUES (?, ?)");
                    $ins->execute([$current_uid, $event_id]);
                    $conn->commit();
                    $success = "Registration confirmed! You have successfully reserved a seat for this event.";
                }
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                if (strpos($e->getMessage(), 'UNIQUE') !== false || strpos($e->getMessage(), 'Duplicate') !== false) {
                    $error = "You are already registered for this event.";
                } else {
                    $error = "Registration failed: " . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'cancel') {
        $del = $conn->prepare("DELETE FROM registrations WHERE user_id = ? AND event_id = ?");
        $del->execute([$current_uid, $event_id]);
        $success = "Your registration has been cancelled successfully.";
    } elseif ($action === 'admin_remove_attendee') {
        if (!is_officer_or_admin()) {
            require_admin();
        }
        $target_user_id = intval($_POST['target_user_id'] ?? 0);
        if ($target_user_id > 0) {
            $del = $conn->prepare("DELETE FROM registrations WHERE user_id = ? AND event_id = ?");
            $del->execute([$target_user_id, $event_id]);
            $success = "Student attendee has been removed from this event roster.";
        }
    }
}

// Fetch event details
$stmt = $conn->prepare("
    SELECT e.*, o.org_name, o.org_description,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id) as registered_count 
    FROM events e 
    JOIN organizations o ON e.org_id = o.org_id 
    WHERE e.event_id = ?
");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    redirect('events.php');
}

// Check if currently logged in user is registered
$is_registered = false;
if (is_logged_in()) {
    $chk_user = $conn->prepare("SELECT reg_id FROM registrations WHERE user_id = ? AND event_id = ?");
    $chk_user->execute([current_user_id(), $event_id]);
    $is_registered = (bool)$chk_user->fetch();
}

$is_full = ($event['max_participants'] > 0 && $event['registered_count'] >= $event['max_participants']);
$is_open = ($event['registration_open'] == 1 && !$is_full);
$pct = ($event['max_participants'] > 0) ? min(100, round(($event['registered_count'] / $event['max_participants']) * 100)) : 0;
$slots_left = ($event['max_participants'] > 0) ? max(0, $event['max_participants'] - $event['registered_count']) : null;

// If user is admin/officer, fetch attendees list
$attendees = [];
if (is_officer_or_admin()) {
    $att_stmt = $conn->prepare("
        SELECT u.user_id, u.student_id, u.first_name, u.last_name, u.email, r.registration_time 
        FROM registrations r 
        JOIN users u ON r.user_id = u.user_id 
        WHERE r.event_id = ? 
        ORDER BY r.registration_time ASC
    ");
    $att_stmt->execute([$event_id]);
    $attendees = $att_stmt->fetchAll();
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container">
    <!-- Back Navigation -->
    <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <a href="<?php echo is_admin() ? 'admin_events.php' : 'events.php'; ?>" style="display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 600; color: var(--slate-500); font-size: 0.9rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Events Directory
        </a>
        <?php if (is_admin()): ?>
            <div style="display: flex; gap: 0.5rem;">
                <a href="edit_event.php?id=<?php echo $event['event_id']; ?>" class="btn btn-secondary btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    Edit Event
                </a>
                <a href="export_attendees.php?event_id=<?php echo $event['event_id']; ?>" class="btn btn-secondary btn-sm">
                    Export CSV
                </a>
                <a href="print_attendees.php?event_id=<?php echo $event['event_id']; ?>" target="_blank" class="btn btn-secondary btn-sm">
                    Print Roster
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($flash = get_flash()): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <div><?php echo e($error); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <div><?php echo e($success); ?></div>
        </div>
    <?php endif; ?>

    <!-- Two-Column Desktop Layout -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: flex-start;">
        <!-- Left: Event Information -->
        <div>
            <div class="card" style="padding: 2.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap;">
                    <?php echo render_org_badge($event['org_name'], 22, 'font-size: 0.85rem; padding: 0.35rem 0.85rem;', 14); ?>

                    <?php if (!$event['registration_open']): ?>
                        <span class="status-badge status-closed">Registration Closed</span>
                    <?php elseif ($is_full): ?>
                        <span class="status-badge status-full">Full Capacity</span>
                    <?php else: ?>
                        <span class="status-badge status-open">Open for Registration</span>
                    <?php endif; ?>
                </div>

                <h1 style="font-size: 2.1rem; color: var(--navy-900); letter-spacing: -0.02em; margin-bottom: 1.5rem; line-height: 1.25;">
                    <?php echo e($event['event_title']); ?>
                </h1>

                <h3 style="font-size: 1.15rem; margin-bottom: 0.75rem; color: var(--navy-900);">Event Description</h3>
                <div style="white-space: pre-line; color: var(--slate-600); font-size: 0.98rem; line-height: 1.7; margin-bottom: 2.5rem;">
                    <?php echo e($event['event_description']); ?>
                </div>

                <div style="border-top: 1px solid var(--slate-200); padding-top: 1.5rem;">
                    <h3 style="font-size: 1.1rem; margin-bottom: 0.4rem; color: var(--navy-900);">About <?php echo e($event['org_name']); ?></h3>
                    <p style="color: var(--slate-500); font-size: 0.9rem; line-height: 1.6;">
                        <?php echo e($event['org_description'] ?: 'Accredited university student organization.'); ?>
                    </p>
                </div>
            </div>

            <!-- Admin / Officer Attendee Roster -->
            <?php if (is_officer_or_admin()): ?>
                <div class="card" style="margin-top: 2rem; padding: 0; overflow: hidden;">
                    <div style="padding: 1.1rem 1.5rem; background: var(--slate-50); border-bottom: 1px solid var(--slate-200); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="font-size: 1.1rem; margin: 0; color: var(--navy-900);">Registered Attendees (<?php echo count($attendees); ?>)</h3>
                            <p style="color: var(--slate-500); font-size: 0.82rem; margin: 0.2rem 0 0 0;">Official participant roster</p>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="print_attendees.php?event_id=<?php echo $event['event_id']; ?>" target="_blank" class="btn btn-secondary btn-sm">Print</a>
                            <a href="export_attendees.php?event_id=<?php echo $event['event_id']; ?>" class="btn btn-secondary btn-sm">Export CSV</a>
                        </div>
                    </div>

                    <?php if (empty($attendees)): ?>
                        <div style="padding: 3rem 1.5rem; text-align: center; color: var(--slate-500); font-size: 0.9rem;">
                            No students have registered for this event yet.
                        </div>
                    <?php else: ?>
                        <div class="table-wrapper" style="border: none; border-radius: 0;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student ID</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Registered At</th>
                                        <?php if (is_admin()): ?>
                                            <th style="text-align: right;">Action</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendees as $idx => $att): ?>
                                        <tr>
                                            <td style="font-size: 0.82rem; color: var(--slate-400);"><?php echo $idx + 1; ?></td>
                                            <td><strong style="font-family: monospace; color: var(--navy-900);"><?php echo e($att['student_id']); ?></strong></td>
                                            <td><span style="font-weight: 600; color: var(--navy-900);"><?php echo e($att['first_name'] . ' ' . $att['last_name']); ?></span></td>
                                            <td style="font-size: 0.88rem; color: var(--slate-600);"><?php echo e($att['email']); ?></td>
                                            <td style="font-size: 0.82rem; color: var(--slate-500); white-space: nowrap;"><?php echo date('M j, Y h:i A', strtotime($att['registration_time'])); ?></td>
                                            <?php if (is_admin()): ?>
                                                <td style="text-align: right; white-space: nowrap;">
                                                    <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Remove <?php echo addslashes($att['first_name'] . ' ' . $att['last_name']); ?> from this event?');">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="admin_remove_attendee">
                                                        <input type="hidden" name="target_user_id" value="<?php echo $att['user_id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">
                                                            Remove
                                                        </button>
                                                    </form>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Action & Logistics Card -->
        <div style="position: sticky; top: 90px;">
            <div class="card">
                <h3 style="font-size: 1.15rem; margin-bottom: 1.25rem;">Event Schedule &amp; Venue</h3>

                <div style="display: flex; flex-direction: column; gap: 1.2rem; margin-bottom: 1.75rem;">
                    <!-- Date -->
                    <div style="display: flex; gap: 0.85rem; align-items: flex-start;">
                        <div class="metric-icon-box metric-icon-navy" style="width: 38px; height: 38px; border-radius: 8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                        </div>
                        <div>
                            <span style="display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--slate-400);">Event Date</span>
                            <strong style="color: var(--navy-900); font-size: 0.95rem;"><?php echo date('l, F j, Y', strtotime($event['event_date'])); ?></strong>
                        </div>
                    </div>

                    <!-- Venue -->
                    <div style="display: flex; gap: 0.85rem; align-items: flex-start;">
                        <div class="metric-icon-box metric-icon-blue" style="width: 38px; height: 38px; border-radius: 8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                        </div>
                        <div>
                            <span style="display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--slate-400);">Venue</span>
                            <strong style="color: var(--navy-900); font-size: 0.95rem;"><?php echo e($event['event_venue']); ?></strong>
                        </div>
                    </div>

                    <!-- Available Slots -->
                    <div style="display: flex; gap: 0.85rem; align-items: flex-start;">
                        <div class="metric-icon-box metric-icon-emerald" style="width: 38px; height: 38px; border-radius: 8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <div style="flex: 1;">
                            <span style="display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--slate-400);">Slots &amp; Capacity</span>
                            <strong style="color: var(--navy-900); font-size: 0.95rem;">
                                <?php echo $event['registered_count']; ?> / <?php echo $event['max_participants'] > 0 ? $event['max_participants'] : 'Unlimited'; ?> registered
                            </strong>
                            <?php if ($slots_left !== null): ?>
                                <div style="font-size: 0.82rem; font-weight: 600; color: <?php echo $slots_left > 0 ? 'var(--emerald-600)' : 'var(--danger-text)'; ?>; margin-top: 0.15rem;">
                                    <?php echo $slots_left > 0 ? "$slots_left remaining slots available" : "Registration full - no slots left"; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($event['max_participants'] > 0): ?>
                                <div class="slots-track" style="margin-top: 0.4rem;">
                                    <div class="slots-fill <?php echo $pct >= 100 ? 'is-full' : ($pct > 75 ? 'is-high' : ''); ?>" style="width: <?php echo $pct; ?>%;"></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Registration Actions -->
                <?php if (!is_logged_in()): ?>
                    <div style="background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: var(--radius-md); padding: 1.25rem; text-align: center;">
                        <p style="font-size: 0.88rem; color: var(--slate-600); margin-bottom: 1rem;">Log in to your student account to register for this event.</p>
                        <a href="../auth/login.php" class="btn btn-primary btn-block" style="margin-bottom: 0.5rem;">Log In to Register</a>
                        <a href="../auth/register.php" class="btn btn-secondary btn-block">Create Account</a>
                    </div>
                <?php elseif ($is_registered): ?>
                    <div style="background: var(--success-bg); border: 1px solid var(--success-border); border-radius: var(--radius-md); padding: 1.25rem; text-align: center;">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--success-dot); color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.65rem auto;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </div>
                        <strong style="color: var(--success-text); font-size: 1rem; display: block;">You are Registered!</strong>
                        <p style="color: var(--success-text); font-size: 0.85rem; margin-top: 0.25rem; margin-bottom: 1.2rem;">Your pass is active. See you at <?php echo e($event['event_venue']); ?>.</p>
                        
                        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel your event registration? Your slot will be released.');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn btn-danger btn-sm btn-block">Cancel Registration</button>
                        </form>
                    </div>
                <?php elseif (!$event['registration_open']): ?>
                    <div style="background: var(--slate-100); border-radius: var(--radius-md); padding: 1rem; text-align: center;">
                        <p style="color: var(--slate-600); font-weight: 600; font-size: 0.92rem; margin: 0;">Registration is currently closed for this event.</p>
                    </div>
                <?php elseif ($is_full): ?>
                    <div style="background: var(--danger-bg); border: 1px solid var(--danger-border); border-radius: var(--radius-md); padding: 1rem; text-align: center;">
                        <strong style="color: var(--danger-text); font-size: 0.92rem; display: block;">Full Capacity Reached</strong>
                        <p style="color: var(--danger-text); font-size: 0.82rem; margin-top: 0.2rem; margin-bottom: 0;">All participant seats have been booked.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" onsubmit="return confirm('Confirm your registration for <?php echo addslashes($event['event_title']); ?>?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="register">
                        <button type="submit" class="btn btn-primary btn-block" style="padding: 0.8rem; font-size: 0.96rem;">
                            Register for This Event
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>