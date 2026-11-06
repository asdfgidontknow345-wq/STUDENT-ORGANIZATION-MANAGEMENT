<?php
require_once '../config/db_connect.php';

// Strict RBAC: only Admin or Officer
if (!is_officer_or_admin()) {
    require_admin();
}

$event_id = intval($_GET['event_id'] ?? 0);
$export_all = isset($_GET['all']) && $_GET['all'] == 1;

if ($event_id > 0) {
    // Export specific event attendees
    $ev_stmt = $conn->prepare("SELECT event_title, event_date, event_venue FROM events WHERE event_id = ?");
    $ev_stmt->execute([$event_id]);
    $event = $ev_stmt->fetch();

    if (!$event) {
        die("Event not found.");
    }

    $filename = 'attendees_event_' . $event_id . '_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // CSV Header row
    fputcsv($output, ['Student ID', 'First Name', 'Last Name', 'Email Address', 'Registration Time']);

    $stmt = $conn->prepare("
        SELECT u.student_id, u.first_name, u.last_name, u.email, r.registration_time
        FROM registrations r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.event_id = ?
        ORDER BY r.registration_time ASC
    ");
    $stmt->execute([$event_id]);

    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['student_id'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['registration_time']
        ]);
    }

    fclose($output);
    exit;
} elseif ($export_all) {
    // Export all system registrations
    $filename = 'campus_all_registrations_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, ['Reg ID', 'Event Title', 'Organization', 'Event Date', 'Venue', 'Student ID', 'Student Name', 'Email', 'Registration Time']);

    $stmt = $conn->query("
        SELECT r.reg_id, e.event_title, o.org_name, e.event_date, e.event_venue,
               u.student_id, u.first_name, u.last_name, u.email, r.registration_time
        FROM registrations r
        JOIN events e ON r.event_id = e.event_id
        JOIN organizations o ON e.org_id = o.org_id
        JOIN users u ON r.user_id = u.user_id
        ORDER BY r.registration_time DESC
    ");

    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['reg_id'],
            $row['event_title'],
            $row['org_name'],
            $row['event_date'],
            $row['event_venue'],
            $row['student_id'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['email'],
            $row['registration_time']
        ]);
    }

    fclose($output);
    exit;
} else {
    redirect('admin_registrations.php');
}

