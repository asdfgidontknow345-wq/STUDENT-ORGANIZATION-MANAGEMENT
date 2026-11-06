<?php
// Ensure session is active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Authentication and Role Checks
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function current_user_role() {
    return $_SESSION['role'] ?? 'guest';
}

function is_admin() {
    return is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function is_officer_or_admin() {
    return is_logged_in() && isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'officer']);
}

function require_auth() {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = "Authentication required. Please log in to proceed.";
        header("Location: ../auth/login.php");
        exit;
    }
}

function require_admin() {
    require_auth();
    if (!is_admin()) {
        http_response_code(403);
        if (file_exists(__DIR__ . '/../includes/header.php')) {
            include __DIR__ . '/../includes/header.php';
            include __DIR__ . '/../includes/navbar.php';
        }
        echo '<div class="container" style="max-width: 600px; margin-top: 4rem;">
            <div class="card" style="text-align: center; padding: 3rem 2rem;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                </div>
                <h2 style="color: #0f172a; margin-bottom: 0.5rem;">403 — Unauthorized Access</h2>
                <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 2rem;">
                    You do not have administrative privileges to access this area. This security event has been noted.
                </p>
                <a href="../pages/events.php" class="btn btn-primary">Return to Campus Events</a>
            </div>
        </div>';
        if (file_exists(__DIR__ . '/../includes/footer.php')) {
            include __DIR__ . '/../includes/footer.php';
        }
        exit;
    }
}

/**
 * Redirect Helper
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * CSRF Protection
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals(csrf_token(), $token)) {
            http_response_code(403);
            die("Security validation failed: Invalid or expired CSRF token. Please refresh the page and try again.");
        }
    }
}

/**
 * Flash messaging
 */
function set_flash($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

function get_flash($type = null) {
    if ($type !== null) {
        if (isset($_SESSION['flash_' . $type])) {
            $msg = $_SESSION['flash_' . $type];
            unset($_SESSION['flash_' . $type]);
            return $msg;
        }
        return null;
    }

    if (isset($_SESSION['flash_success'])) {
        $msg = $_SESSION['flash_success'];
        unset($_SESSION['flash_success']);
        return ['type' => 'success', 'message' => $msg];
    }
    if (isset($_SESSION['flash_error'])) {
        $msg = $_SESSION['flash_error'];
        unset($_SESSION['flash_error']);
        return ['type' => 'error', 'message' => $msg];
    }
    return null;
}

/**
 * XSS escaping shorthand
 */
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Organization Acronym & Badge Helpers
 */

/**
 * Generate an acronym from an organization name using the first letter of each meaningful word.
 * Automatically ignores common connector words such as "of", "and", "the", "for", "&", etc.
 *
 * Examples:
 * - Junior Philippine Institute of Accountants -> JPIA
 * - Association of Computer Engineering Students -> ACES
 * - Computer Society -> CS
 * - Junior Marketing Association -> JMA
 */
function get_org_acronym($name) {
    $name = trim((string)$name);
    if (empty($name)) return '';

    // If an explicit acronym exists in parentheses, e.g. "Computer Society (CS)", use it
    if (preg_match('/\(([A-Za-z0-9]{2,8})\)/', $name, $matches)) {
        return strtoupper($matches[1]);
    }

    // If already an acronym or short uppercase string, e.g. "IEEE", "ACM"
    if (preg_match('/^[A-Z0-9]{2,8}$/', $name)) {
        return $name;
    }

    $words = preg_split('/[\s\-_]+/', $name);
    if (count($words) === 1) {
        return $name;
    }

    $ignore = ['of', 'and', 'the', 'for', 'in', 'on', 'at', 'to', 'by', 'with', 'a', 'an', '&'];
    $letters = [];

    foreach ($words as $word) {
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', $word);
        if ($clean === '') continue;
        if (in_array(strtolower($clean), $ignore) && count($words) > 1) {
            continue;
        }
        $letters[] = strtoupper($clean[0]);
    }

    // Fallback if all words were filtered out
    if (empty($letters)) {
        foreach ($words as $word) {
            $clean = preg_replace('/[^a-zA-Z0-9]/', '', $word);
            if ($clean !== '') {
                $letters[] = strtoupper($clean[0]);
            }
        }
    }

    return implode('', $letters);
}

/**
 * Get organization display name: displays the full organization name if it fits within
 * the character threshold (default 20), or automatically generates and returns the acronym.
 */
function get_org_display_name($name, $max_len = 20) {
    $name = trim((string)$name);
    if (empty($name)) return '';

    if (mb_strlen($name) > $max_len) {
        $acronym = get_org_acronym($name);
        if (!empty($acronym) && mb_strlen($acronym) < mb_strlen($name)) {
            return $acronym;
        }
    }

    return $name;
}

/**
 * Render an organization badge with automatic acronym generation when the name is too long,
 * full organization name tooltip, and responsive styling.
 */
function render_org_badge($org_name, $max_len = 20, $extra_style = '', $icon_size = 12) {
    $fullName = trim((string)$org_name);
    if ($fullName === '') return '';

    $acronym = get_org_acronym($fullName);
    $isAcronym = (mb_strlen($fullName) > $max_len && !empty($acronym) && mb_strlen($acronym) < mb_strlen($fullName));
    $displayName = $isAcronym ? $acronym : $fullName;

    $titleAttr = e($fullName);
    $styleAttr = $extra_style ? ' style="' . e($extra_style) . '"' : '';
    $acronymClass = $isAcronym ? ' org-tag-acronym' : '';

    return '<span class="org-tag' . $acronymClass . '" title="' . $titleAttr . '" aria-label="' . $titleAttr . '"' . $styleAttr . '>'
        . '<svg width="' . intval($icon_size) . '" height="' . intval($icon_size) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">'
        . '<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>'
        . '</svg>'
        . '<span class="org-name-text">' . e($displayName) . '</span>'
        . '</span>';
}
?>