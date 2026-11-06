<?php
require_once '../config/db_connect.php';
require_auth(); // Student or Admin must be authenticated

$user_id = current_user_id();

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_event_id'])) {
    verify_csrf();
    $cancel_id = intval($_POST['cancel_event_id']);
    $del = $conn->prepare("DELETE FROM registrations WHERE user_id = ? AND event_id = ?");
    $del->execute([$user_id, $cancel_id]);
    set_flash('success', 'Your event registration has been successfully cancelled.');
    redirect('my_registrations.php');
}

// Fetch user registrations
$stmt = $conn->prepare("
    SELECT r.reg_id, r.registration_time, e.event_id, e.event_title, e.event_date, e.event_venue, o.org_name 
    FROM registrations r 
    JOIN events e ON r.event_id = e.event_id 
    JOIN organizations o ON e.org_id = o.org_id 
    WHERE r.user_id = ? 
    ORDER BY e.event_date ASC
");
$stmt->execute([$user_id]);
$registrations = $stmt->fetchAll();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; font-weight: 700; color: var(--blue-600); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Student Event Passes
            </div>
            <h1 style="font-size: 2.1rem; color: var(--navy-900); letter-spacing: -0.02em;">My Event Registrations</h1>
            <p style="color: var(--slate-500); font-size: 0.95rem; margin-top: 0.2rem;">Manage your active event reservations, schedules, and venue details.</p>
        </div>
        <a href="events.php" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Browse More Events
        </a>
    </div>

    <?php if ($flash = get_flash()): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <?php if (empty($registrations)): ?>
            <div style="text-align: center; padding: 4.5rem 1.5rem;">
                <div style="width: 60px; height: 60px; border-radius: 50%; background: var(--blue-50); color: var(--blue-600); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto;">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                </div>
                <h3 style="font-size: 1.25rem; margin-bottom: 0.35rem; color: var(--navy-900);">No Active Registrations</h3>
                <p style="color: var(--slate-500); font-size: 0.92rem; max-width: 440px; margin: 0 auto 1.75rem auto;">
                    You haven't signed up for any student organization events yet. Browse available campus events and reserve your seat!
                </p>
                <a href="events.php" class="btn btn-primary" style="padding: 0.75rem 1.6rem;">Explore Campus Events</a>
            </div>
        <?php else: ?>
            <div style="padding: 1.2rem 1.5rem; background: var(--slate-50); border-bottom: 1px solid var(--slate-200); display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.9rem; font-weight: 700; color: var(--navy-900);">
                    Registered Events (<?php echo count($registrations); ?>)
                </span>
                <span class="status-badge status-open" style="font-size: 0.78rem;">
                    Active Attendance Confirmed
                </span>
            </div>

            <div class="table-wrapper" style="border: none; border-radius: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Event &amp; Host Organization</th>
                            <th>Schedule Date</th>
                            <th>Venue</th>
                            <th>Registered On</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $reg): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; font-size: 0.98rem; color: var(--navy-900); margin-bottom: 0.25rem;">
                                        <a href="event_details.php?id=<?php echo $reg['event_id']; ?>">
                                            <?php echo e($reg['event_title']); ?>
                                        </a>
                                    </div>
                                    <?php echo render_org_badge($reg['org_name'], 20, 'font-size: 0.75rem;'); ?>
                                </td>
                                <td>
                                    <div class="meta-row" style="font-weight: 600; color: var(--slate-700);">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                                        <?php echo date('D, M j, Y', strtotime($reg['event_date'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="meta-row" style="color: var(--slate-600);">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                        <?php echo e($reg['event_venue']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size: 0.85rem; color: var(--slate-500);">
                                        <?php echo date('M j, Y h:i A', strtotime($reg['registration_time'])); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                        <a href="event_details.php?id=<?php echo $reg['event_id']; ?>" class="btn btn-secondary btn-sm">
                                            View Details
                                        </a>
                                        <form method="POST" onsubmit="return confirm('Cancel your registration for this event? Your reserved slot will be released.');" style="display: inline; margin: 0;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="cancel_event_id" value="<?php echo $reg['event_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                Cancel Reg
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