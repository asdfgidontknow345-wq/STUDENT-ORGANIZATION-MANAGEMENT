<?php
require_once '../config/db_connect.php';
require_admin(); // Strict RBAC access control

$event_id = intval($_GET['id'] ?? 0);
if ($event_id <= 0) {
    set_flash('error', 'Invalid event selected for editing.');
    redirect('admin_events.php');
}

// Fetch existing event
$stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    set_flash('error', 'Event not found.');
    redirect('admin_events.php');
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
    $registration_open = isset($_POST['registration_open']) ? 1 : 0;

    if (empty($event_title) || empty($event_date) || empty($event_venue) || $org_id <= 0) {
        $error = "Please fill in all required fields.";
    } elseif ($max_participants < 0) {
        $error = "Participant limit cannot be negative.";
    } else {
        try {
            $upd = $conn->prepare("
                UPDATE events 
                SET org_id = ?, event_title = ?, event_description = ?, event_date = ?, 
                    event_venue = ?, registration_open = ?, max_participants = ?
                WHERE event_id = ?
            ");
            $upd->execute([
                $org_id, $event_title, $event_description, $event_date,
                $event_venue, $registration_open, $max_participants, $event_id
            ]);

            set_flash('success', 'Event updated successfully.');
            redirect('admin_events.php');
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$orgs = $conn->query("SELECT * FROM organizations ORDER BY org_name ASC")->fetchAll();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container" style="max-width: 780px;">
    <div style="margin-bottom: 1.5rem;">
        <a href="admin_events.php" style="display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 600; color: var(--slate-500); font-size: 0.9rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Event Management
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?php echo e($error); ?>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 2.25rem;">
        <div style="margin-bottom: 1.75rem;">
            <span style="font-size: 0.8rem; font-weight: 700; color: var(--blue-600); text-transform: uppercase; letter-spacing: 0.05em;">Event Editor</span>
            <h1 style="font-size: 1.85rem; color: var(--navy-900); letter-spacing: -0.02em; margin-top: 0.2rem;">Edit Campus Event</h1>
            <p style="color: var(--slate-500); font-size: 0.92rem; margin-top: 0.25rem;">Modify schedule, capacity limits, and registration state.</p>
        </div>

        <form method="POST">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label>Hosting Organization <span style="color: var(--danger-dot);">*</span></label>
                <select name="org_id" required>
                    <?php foreach ($orgs as $org): ?>
                        <option value="<?php echo $org['org_id']; ?>" <?php echo $org['org_id'] == $event['org_id'] ? 'selected' : ''; ?>>
                            <?php echo e($org['org_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Event Title <span style="color: var(--danger-dot);">*</span></label>
                <input type="text" name="event_title" value="<?php echo e($event['event_title']); ?>" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Event Date <span style="color: var(--danger-dot);">*</span></label>
                    <input type="date" name="event_date" value="<?php echo e($event['event_date']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Participant Capacity (0 for unlimited)</label>
                    <input type="number" name="max_participants" min="0" value="<?php echo e($event['max_participants']); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Event Venue / Location <span style="color: var(--danger-dot);">*</span></label>
                <input type="text" name="event_venue" value="<?php echo e($event['event_venue']); ?>" required>
            </div>

            <div class="form-group">
                <label>Event Description</label>
                <textarea name="event_description" rows="5" required><?php echo e($event['event_description']); ?></textarea>
            </div>

            <div class="form-group" style="background: var(--slate-50); padding: 1rem 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--slate-200);">
                <label style="display: flex; align-items: center; gap: 0.65rem; margin: 0; cursor: pointer;">
                    <input type="checkbox" name="registration_open" value="1" <?php echo $event['registration_open'] ? 'checked' : ''; ?> style="width: auto; height: auto;">
                    <span style="font-weight: 600; color: var(--navy-900);">Registration Open</span>
                </label>
                <span style="font-size: 0.82rem; color: var(--slate-500); display: block; margin-top: 0.25rem; margin-left: 1.7rem;">
                    When unchecked, students cannot register for this event.
                </span>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.75rem;">
                <a href="admin_events.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" style="padding: 0.7rem 1.6rem;">Update Event</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
