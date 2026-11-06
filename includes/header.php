<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusSync &mdash; University Student Org &amp; Event Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="../assets/images/logo.jpg">
    <link rel="apple-touch-icon" href="../assets/images/logo.jpg">
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/styles.css') ? filemtime(__DIR__ . '/../assets/css/styles.css') : time(); ?>">
</head>
<body>
<div class="app-layout" id="appLayout">
<script>
// Prevent layout flicker on page navigation
try {
    if (window.innerWidth >= 992 && localStorage.getItem('sidebar_collapsed') === '1') {
        document.getElementById('appLayout').classList.add('sidebar-collapsed');
    }
} catch(e) {}
</script>
