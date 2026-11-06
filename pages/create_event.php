<?php
require_once '../config/db_connect.php';

// Strict RBAC access control: only Admin or Officer can create events
if (!is_officer_or_admin()) {
    require_admin(); // triggers 403 Forbidden with clean template
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $org_id = intval($_POST['org_id'] ?? 0);
    $event_title = trim($_POST['event_title'] ?? '');
    $event_description = trim($_POST['event_description'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $event_venue = trim($_POST['event_venue'] ?? '');
    $max_participants = intval($_POST['max_participants'] ?? 0);

    if (empty($event_title) || empty($event_date) || empty($event_venue) || $org_id <= 0) {
        $error = "Please fill in all required fields.";
    } elseif ($max_participants < 0) {
        $error = "Participant limit cannot be negative.";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO events (org_id, event_title, event_description, event_date, event_venue, registration_open, max_participants) 
                VALUES (?, ?, ?, ?, ?, 1, ?)
            ");
            $stmt->execute([$org_id, $event_title, $event_description, $event_date, $event_venue, $max_participants]);
            $new_id = $conn->lastInsertId();
            
            set_flash('success', 'Event successfully created and published.');
            redirect("event_details.php?id=$new_id");
        } catch (PDOException $e) {
            $error = "Failed to create event: " . $e->getMessage();
        }
    }
}

$orgs = $conn->query("SELECT * FROM organizations ORDER BY org_name ASC")->fetchAll();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container" style="max-width: 780px;">
    <div style="margin-bottom: 1.5rem;">
        <a href="<?php echo is_admin() ? 'admin_events.php' : 'events.php'; ?>" style="display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 600; color: var(--slate-500); font-size: 0.9rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Events Directory
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <div><?php echo e($error); ?></div>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 2.25rem;">
        <div style="margin-bottom: 1.75rem;">
            <span style="font-size: 0.8rem; font-weight: 700; color: var(--blue-600); text-transform: uppercase; letter-spacing: 0.05em;">Student Leader Portal</span>
            <h1 style="font-size: 1.85rem; color: var(--navy-900); letter-spacing: -0.02em; margin-top: 0.2rem;">Post a New Campus Event</h1>
            <p style="color: var(--slate-500); font-size: 0.92rem; margin-top: 0.25rem;">Publish an event on behalf of an accredited student organization.</p>
        </div>

        <form method="POST">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label>Hosting Organization <span style="color: var(--danger-dot);">*</span></label>
                <select name="org_id" required>
                    <option value="">-- Choose student organization --</option>
                    <?php foreach ($orgs as $org): ?>
                        <option value="<?php echo $org['org_id']; ?>">
                            <?php echo e($org['org_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Event Title <span style="color: var(--danger-dot);">*</span></label>
                <input type="text" name="event_title" placeholder="e.g. Annual IT Tech Summit 2026" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Event Date <span style="color: var(--danger-dot);">*</span></label>
                    <input type="date" name="event_date" required>
                </div>

                <div class="form-group">
                    <label>Participant Limit (0 for unlimited)</label>
                    <input type="number" name="max_participants" min="0" value="100">
                </div>
            </div>

            <div class="form-group">
                <label>Event Venue / Location <span style="color: var(--danger-dot);">*</span></label>
                <input type="text" name="event_venue" placeholder="e.g. University Main Auditorium or Online (Zoom)" required>
            </div>

            <div class="form-group">
                <label>Event Description <span style="color: var(--danger-dot);">*</span></label>
                <textarea name="event_description" rows="5" placeholder="Provide complete event agenda, requirements, prerequisites, and speaker info..." required></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.75rem;">
                <a href="<?php echo is_admin() ? 'admin_events.php' : 'events.php'; ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" style="padding: 0.7rem 1.6rem;">Publish Event</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>