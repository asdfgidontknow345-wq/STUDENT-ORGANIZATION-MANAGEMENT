<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$isUserLoggedIn = is_logged_in();
$userIsAdmin = is_admin();

// Synchronize session with database record so all navigation and profile views stay 100% consistent
if ($isUserLoggedIn && isset($conn) && $conn instanceof PDO) {
    try {
        $u_stmt = $conn->prepare("SELECT user_id, student_id, first_name, last_name, email, role, is_verified FROM users WHERE user_id = ?");
        $u_stmt->execute([$_SESSION['user_id']]);
        $dbUser = $u_stmt->fetch(PDO::FETCH_ASSOC);
        if ($dbUser) {
            $_SESSION['first_name'] = $dbUser['first_name'];
            $_SESSION['last_name'] = $dbUser['last_name'];
            $_SESSION['name'] = trim($dbUser['first_name'] . ' ' . $dbUser['last_name']);
            $_SESSION['email'] = $dbUser['email'];
            $_SESSION['role'] = $dbUser['role'];
            $_SESSION['student_id'] = $dbUser['student_id'];
            $_SESSION['is_verified'] = $dbUser['is_verified'];
            $userIsAdmin = ($dbUser['role'] === 'admin');
        }
    } catch (Exception $e) {
        // Fallback to existing session values
    }
}

$userName = $isUserLoggedIn ? ($_SESSION['name'] ?? trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''))) : 'Guest';
$userEmail = $isUserLoggedIn ? ($_SESSION['email'] ?? '') : '';
$userRole = $isUserLoggedIn ? ($_SESSION['role'] ?? current_user_role()) : 'guest';

// Compute initials (e.g. "AC" for Alex Cruz)
$firstInitial = !empty($_SESSION['first_name']) ? strtoupper(substr(trim($_SESSION['first_name']), 0, 1)) : '';
$lastInitial = !empty($_SESSION['last_name']) ? strtoupper(substr(trim($_SESSION['last_name']), 0, 1)) : '';
$initial = ($firstInitial . $lastInitial) ?: (!empty($userName) ? strtoupper(substr(trim($userName), 0, 1)) : 'U');

// Page titles mapping for topbar breadcrumb
$pageTitles = [
    'index.php' => 'Portal Overview',
    'events.php' => 'Campus Events & Activities',
    'event_details.php' => 'Event Details & Passes',
    'my_registrations.php' => 'My Event Registrations',
    'profile.php' => 'Account Settings & Profile',
    'create_event.php' => 'Publish New Campus Event',
    'edit_event.php' => 'Edit Campus Event',
    'admin_dashboard.php' => 'Administration Dashboard',
    'admin_events.php' => 'Event Management Directory',
    'admin_organizations.php' => 'Organization Management',
    'admin_users.php' => 'Student & User Directory',
    'admin_registrations.php' => 'Master Registrations Log',
    'login.php' => 'Portal Sign In',
    'register.php' => 'New Student Registration',
    'resend_email.php' => 'Resend Verification',
    'verify.php' => 'Account Activation'
];
$currentTitle = $pageTitles[$currentPage] ?? 'University Portal';
?>

<!-- SIDEBAR NAVIGATION DRAWER -->
<aside class="app-sidebar" id="appSidebar">
    <!-- Brand Header -->
    <div class="sidebar-header">
        <a href="<?php echo $userIsAdmin ? '../pages/admin_dashboard.php' : '../pages/index.php'; ?>" class="sidebar-brand">
            <div class="brand-emblem">
                <img src="../assets/images/logo.jpg" alt="CampusSync Logo" class="brand-logo-img" onerror="this.onerror=null; this.src='../1.jpg';">
            </div>
            <div class="brand-info">
                <div class="brand-name">Campus<span>Sync</span></div>
                <span class="brand-sub"><?php echo $userIsAdmin ? 'Admin Console' : 'Event Portal'; ?></span>
            </div>
        </a>

        <!-- Button to Hide/Collapse Sidebar (Inside Sidebar Header) -->
        <button type="button" class="sidebar-hide-btn" id="sidebarHideBtn" onclick="toggleSidebar()" title="Hide Sidebar (Ctrl+B)" aria-label="Hide sidebar">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#334155" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display: block; width: 18px; height: 18px; stroke: #334155; fill: none;">
                <rect width="18" height="18" x="3" y="3" rx="3" ry="3"></rect>
                <line x1="9" y1="3" x2="9" y2="21"></line>
            </svg>
        </button>
    </div>

    <!-- Role Status Banner -->
    <div class="sidebar-badge-bar">
        <span class="status-badge <?php echo $userIsAdmin ? 'status-open' : ($isUserLoggedIn ? 'status-pending' : 'status-closed'); ?>" style="font-size: 0.72rem; width: 100%; display: flex; justify-content: center; padding: 0.3rem 0.6rem;">
            <?php echo $userIsAdmin ? '🛡️ ADMINISTRATOR' : ($isUserLoggedIn ? '🎓 STUDENT PORTAL' : '👤 GUEST VISITOR'); ?>
        </span>
    </div>

    <!-- Sidebar Navigation Menu -->
    <nav class="sidebar-nav">
        <?php if ($userIsAdmin): ?>
            <!-- ADMIN NAVIGATION TABS -->
            <div class="sidebar-heading">CORE MANAGEMENT</div>

            <a href="../pages/admin_dashboard.php" class="sidebar-tab <?php echo $currentPage === 'admin_dashboard.php' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                <span>Dashboard</span>
            </a>

            <a href="../pages/admin_events.php" class="sidebar-tab <?php echo in_array($currentPage, ['admin_events.php', 'create_event.php', 'edit_event.php']) ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                <span>Events</span>
            </a>

            <a href="../pages/admin_organizations.php" class="sidebar-tab <?php echo $currentPage === 'admin_organizations.php' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>Organizations</span>
            </a>

            <a href="../pages/admin_users.php" class="sidebar-tab <?php echo $currentPage === 'admin_users.php' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Students &amp; Users</span>
            </a>

            <a href="../pages/admin_registrations.php" class="sidebar-tab <?php echo in_array($currentPage, ['admin_registrations.php', 'print_attendees.php']) ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                <span>Master Registrations</span>
            </a>

            <div class="sidebar-heading" style="margin-top: 0.5rem;">QUICK ACTIONS</div>

            <a href="../pages/create_event.php" class="sidebar-tab <?php echo $currentPage === 'create_event.php' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span>Post New Event</span>
            </a>

            <a href="../pages/events.php" class="sidebar-tab">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
                <span>Live Student View</span>
            </a>

            <a href="../pages/export_attendees.php?all=1" class="sidebar-tab">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span>Export Master CSV</span>
            </a>

            <div class="sidebar-heading" style="margin-top: 0.5rem;">PREFERENCES</div>

            <a href="../pages/profile.php" class="sidebar-tab <?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>My Profile</span>
            </a>

        <?php elseif ($isUserLoggedIn): ?>
            <!-- STUDENT NAVIGATION TABS -->
            <div class="sidebar-heading">DISCOVER &amp; ATTEND</div>

            <a href="../pages/index.php" class="sidebar-tab <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>Portal Home</span>
            </a>

            <a href="../pages/events.php" class="sidebar-tab <?php echo in_array($currentPage, ['events.php', 'event_details.php']) ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                <span>Campus Events</span>
            </a>

            <div class="sidebar-heading" style="margin-top: 0.5rem;">STUDENT ACTIVITIES</div>

            <a href="../pages/my_registrations.php" class="sidebar-tab <?php echo $currentPage === 'my_registrations.php' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <span>My Registrations</span>
            </a>

            <a href="../pages/profile.php" class="sidebar-tab <?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>My Profile</span>
            </a>

        <?php else: ?>
            <!-- GUEST / VISITOR TABS -->
            <div class="sidebar-heading">EXPLORE</div>

            <a href="../pages/index.php" class="sidebar-tab <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>Portal Home</span>
            </a>

            <a href="../pages/events.php" class="sidebar-tab <?php echo in_array($currentPage, ['events.php', 'event_details.php']) ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                <span>Campus Events</span>
            </a>

            <div class="sidebar-heading" style="margin-top: 0.5rem;">ACCESS &amp; SIGN IN</div>

            <a href="../auth/login.php" class="sidebar-tab <?php echo $currentPage === 'login.php' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                <span>Log In</span>
            </a>

            <a href="../auth/register.php" class="sidebar-tab <?php echo $currentPage === 'register.php' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                <span>Create Student Account</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- Sidebar Footer / User Profile Card -->
    <div class="sidebar-footer">
        <?php if ($isUserLoggedIn): ?>
            <div class="sidebar-user-card">
                <div class="sidebar-user-avatar" style="<?php echo $userIsAdmin ? 'background: linear-gradient(135deg, #1e3a8a, #3b82f6);' : ''; ?>">
                    <?php echo $initial; ?>
                </div>
                <div class="sidebar-user-meta">
                    <div class="sidebar-user-name" title="<?php echo htmlspecialchars($userName); ?>">
                        <?php echo htmlspecialchars($userName); ?>
                    </div>
                    <div class="sidebar-user-role">
                        <?php echo htmlspecialchars(ucfirst($userRole)); ?> Account
                    </div>
                </div>
                <a href="../auth/logout.php" class="sidebar-logout-btn" title="Sign Out">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="9" x2="9" y2="9"/></svg>
                </a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <a href="../auth/login.php" class="btn btn-secondary btn-sm btn-block">Log In</a>
                <a href="../auth/register.php" class="btn btn-primary btn-sm btn-block">Register</a>
            </div>
        <?php endif; ?>
    </div>
</aside>

<!-- Backdrop overlay for mobile drawer -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

<!-- MAIN APPLICATION WRAPPER (Top header + Main Content Area) -->
<div class="app-main-wrapper">
    <!-- Topbar Header -->
    <header class="app-topbar">
        <div class="app-topbar-left">
            <!-- Sidebar Toggle Button (Shown when sidebar is collapsed/mobile) -->
            <button type="button" class="sidebar-toggle-btn" id="sidebarToggle" onclick="toggleSidebar()" title="Toggle Sidebar (Ctrl+B)" aria-label="Toggle sidebar navigation">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#334155" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display: block; width: 18px; height: 18px; stroke: #334155; fill: none;">
                    <rect width="18" height="18" x="3" y="3" rx="3" ry="3"></rect>
                    <line x1="9" y1="3" x2="9" y2="21"></line>
                </svg>
            </button>

            <!-- Page Title Breadcrumb -->
            <div class="topbar-breadcrumb">
                <span class="topbar-sub">CampusSync</span>
                <span class="topbar-sep">/</span>
                <span class="topbar-current"><?php echo htmlspecialchars($currentTitle); ?></span>
            </div>
        </div>

        <div class="app-topbar-right">
            <?php if ($isUserLoggedIn): ?>
                <a href="../pages/profile.php" class="topbar-profile-pill" title="View Profile">
                    <div class="user-avatar-circle" style="width: 28px; height: 28px; font-size: 0.75rem; <?php echo $userIsAdmin ? 'background: linear-gradient(135deg, #1e3a8a, #3b82f6);' : ''; ?>">
                        <?php echo $initial; ?>
                    </div>
                    <span style="font-size: 0.86rem; font-weight: 600; color: var(--navy-900); max-width: 140px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?php echo htmlspecialchars($_SESSION['first_name'] ?? $userName); ?>
                    </span>
                </a>
            <?php else: ?>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="../auth/login.php" class="btn btn-secondary btn-sm" style="padding: 0.4rem 0.85rem; font-size: 0.84rem;">Log In</a>
                    <a href="../auth/register.php" class="btn btn-primary btn-sm" style="padding: 0.4rem 0.85rem; font-size: 0.84rem;">Register</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Content Container (opened here, closed in footer.php) -->
    <main class="app-main-content">

<script>
function toggleSidebar() {
    const layout = document.getElementById('appLayout') || document.querySelector('.app-layout');
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const isMobile = window.innerWidth < 992;

    if (isMobile) {
        if (sidebar) sidebar.classList.toggle('is-open');
        if (backdrop) backdrop.classList.toggle('is-open');
    } else {
        if (layout) {
            layout.classList.toggle('sidebar-collapsed');
            const isCollapsed = layout.classList.contains('sidebar-collapsed');
            try {
                localStorage.setItem('sidebar_collapsed', isCollapsed ? '1' : '0');
            } catch(e) {}
        }
    }
}

// Keyboard shortcuts: Ctrl+B to toggle sidebar, Escape to close mobile sidebar
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'b' || e.key === 'B')) {
        e.preventDefault();
        toggleSidebar();
    } else if (e.key === 'Escape') {
        const sidebar = document.getElementById('appSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar && sidebar.classList.contains('is-open')) {
            sidebar.classList.remove('is-open');
            if (backdrop) backdrop.classList.remove('is-open');
        }
    }
});
</script>