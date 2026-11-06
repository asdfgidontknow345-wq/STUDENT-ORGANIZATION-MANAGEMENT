<?php
require_once '../config/db_connect.php';

if (!is_officer_or_admin()) {
    require_admin();
}

$event_id = intval($_GET['event_id'] ?? 0);
if ($event_id <= 0) {
    die("Invalid event ID.");
}

$stmt = $conn->prepare("
    SELECT e.*, o.org_name 
    FROM events e
    JOIN organizations o ON e.org_id = o.org_id
    WHERE e.event_id = ?
");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    die("Event not found.");
}

// Fetch attendee list
$att_stmt = $conn->prepare("
    SELECT u.student_id, u.first_name, u.last_name, u.email, r.registration_time
    FROM registrations r
    JOIN users u ON r.user_id = u.user_id
    WHERE r.event_id = ?
    ORDER BY u.last_name ASC, u.first_name ASC
");
$att_stmt->execute([$event_id]);
$attendees = $att_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Event Attendance Roster - <?php echo e($event['event_title']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #0f172a;
            background: #ffffff;
            margin: 0;
            padding: 2.5rem;
            font-size: 13px;
            line-height: 1.5;
        }
        .header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .title {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 0.25rem 0;
            text-transform: uppercase;
            letter-spacing: -0.01em;
        }
        .subtitle {
            font-size: 12px;
            color: #475569;
            margin: 0;
        }
        .event-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }
        .event-meta-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 0.2rem;
        }
        .event-meta-val {
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
            text-align: left;
        }
        th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .signature-col {
            width: 140px;
        }
        .no-print {
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.75rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #0f172a;
        }
        .btn-primary {
            background: #1e3a8a;
            border-color: #1e3a8a;
            color: #ffffff;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0.5cm; }
            th { background-color: #e2e8f0 !important; color: #000000 !important; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">Print Official Roster</button>
        <a href="export_attendees.php?event_id=<?php echo $event['event_id']; ?>" class="btn">Download CSV</a>
        <a href="event_details.php?id=<?php echo $event['event_id']; ?>" class="btn">Back to Event</a>
    </div>

    <div class="header">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 58px; height: 58px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 3px; display: flex; align-items: center; justify-content: center; background: #ffffff; flex-shrink: 0;">
                <img src="../assets/images/logo.jpg" alt="University Logo" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.onerror=null; this.src='../1.jpg';">
            </div>
            <div>
                <div class="subtitle" style="font-weight: 700; color: #1e3a8a; letter-spacing: 0.05em; text-transform: uppercase;">University Student Affairs &amp; Activity Council</div>
                <h1 class="title">Official Event Attendance &amp; Check-In Roster</h1>
                <p class="subtitle">Generated on <?php echo date('F j, Y, g:i A'); ?> | Official Accreditation Record</p>
            </div>
        </div>
        <div style="text-align: right;">
            <span style="font-family: 'JetBrains Mono', monospace; font-size: 11px; background: #e2e8f0; padding: 4px 8px; border-radius: 4px;">
                EVENT ID #<?php echo $event['event_id']; ?>
            </span>
        </div>
    </div>

    <div class="event-box">
        <div>
            <div class="event-meta-label">Event Title</div>
            <div class="event-meta-val"><?php echo e($event['event_title']); ?></div>
        </div>
        <div>
            <div class="event-meta-label">Host Organization</div>
            <div class="event-meta-val"><?php echo e($event['org_name']); ?></div>
        </div>
        <div>
            <div class="event-meta-label">Date &amp; Venue</div>
            <div class="event-meta-val"><?php echo date('M j, Y', strtotime($event['event_date'])); ?> &bull; <?php echo e($event['event_venue']); ?></div>
        </div>
        <div>
            <div class="event-meta-label">Total Confirmed Attendees</div>
            <div class="event-meta-val" style="color: #1e3a8a; font-size: 16px; font-weight: 800;">
                <?php echo count($attendees); ?> 
                <?php if ($event['max_participants'] > 0): ?>
                    <span style="font-size: 11px; font-weight: normal; color: #64748b;">(Cap: <?php echo $event['max_participants']; ?>)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (empty($attendees)): ?>
        <p style="text-align: center; color: #64748b; padding: 2rem 0;">No student registrations recorded for this event yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 35px; text-align: center;">#</th>
                    <th style="width: 110px;">Student ID</th>
                    <th>Full Name</th>
                    <th>University Email</th>
                    <th>Registration Date</th>
                    <th class="signature-col">Signature / Check-In</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($attendees as $att): ?>
                    <tr>
                        <td style="text-align: center; color: #64748b; font-size: 11px;"><?php echo $i++; ?></td>
                        <td style="font-family: 'JetBrains Mono', monospace; font-weight: 700;"><?php echo e($att['student_id']); ?></td>
                        <td style="font-weight: 600;"><?php echo e($att['last_name'] . ', ' . $att['first_name']); ?></td>
                        <td style="color: #475569;"><?php echo e($att['email']); ?></td>
                        <td style="font-size: 11px; color: #64748b;"><?php echo date('M j, Y g:i A', strtotime($att['registration_time'])); ?></td>
                        <td class="signature-col" style="background: #ffffff;"></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div style="margin-top: 3rem; display: flex; justify-content: space-between;">
        <div style="width: 200px; border-top: 1px solid #0f172a; padding-top: 0.5rem; text-align: center; font-size: 11px; color: #475569;">
            Organization Representative
        </div>
        <div style="width: 200px; border-top: 1px solid #0f172a; padding-top: 0.5rem; text-align: center; font-size: 11px; color: #475569;">
            Student Affairs Officer
        </div>
    </div>

</body>
</html>
