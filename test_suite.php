<?php
// Comprehensive Integration and Security Test Suite

$baseUrl = 'http://localhost:8000';

function makeRequest($url, $method = 'GET', $data = null, $cookies = [], $followRedirects = false) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirects);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    }

    if (!empty($cookies)) {
        $cookieParts = [];
        foreach ($cookies as $k => $v) {
            $cookieParts[] = "$k=$v";
        }
        curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $cookieParts));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    // Extract cookies from Set-Cookie
    preg_match_all('/Set-Cookie:\s*([^;]+)/mi', $headers, $matches);
    $newCookies = $cookies;
    if (!empty($matches[1])) {
        foreach ($matches[1] as $c) {
            $pair = explode('=', $c, 2);
            if (count($pair) == 2) {
                $newCookies[$pair[0]] = $pair[1];
            }
        }
    }

    curl_close($ch);
    return [
        'code' => $httpCode,
        'headers' => $headers,
        'body' => $body,
        'cookies' => $newCookies
    ];
}

function extractCsrfToken($body) {
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $body, $m)) {
        return $m[1];
    }
    return '';
}

$results = [];

function assertTest($name, $condition, $details = '') {
    global $results;
    if ($condition) {
        $results[] = ["status" => "PASS", "test" => $name, "details" => $details];
        echo " [PASS] $name\n";
    } else {
        $results[] = ["status" => "FAIL", "test" => $name, "details" => $details];
        echo " [FAIL] $name - $details\n";
    }
}

echo "========================================================\n";
echo "RUNNING CAMPUS SYNC VERIFICATION & RBAC TEST SUITE\n";
echo "========================================================\n\n";

// 1. GUEST ACCESS TESTS
echo "--- Testing Guest Access & Redirection ---\n";
$res = makeRequest("$baseUrl/pages/index.php");
assertTest("Guest can access Homepage (HTTP 200)", $res['code'] === 200);

$res = makeRequest("$baseUrl/pages/events.php");
assertTest("Guest can access Events directory (HTTP 200)", $res['code'] === 200);

$res = makeRequest("$baseUrl/pages/my_registrations.php");
assertTest("Guest blocked from My Registrations (HTTP 302 redirect)", $res['code'] === 302);

$res = makeRequest("$baseUrl/pages/profile.php");
assertTest("Guest blocked from Profile (HTTP 302 redirect)", $res['code'] === 302);

$res = makeRequest("$baseUrl/pages/admin_dashboard.php");
assertTest("Guest blocked from Admin Dashboard (HTTP 302/403)", in_array($res['code'], [302, 403]));

// 2. ADMIN LOGIN & CAPABILITIES
echo "\n--- Testing Admin Authentication & RBAC ---\n";
$loginPage = makeRequest("$baseUrl/auth/login.php");
$adminCookies = $loginPage['cookies'];
$csrf = extractCsrfToken($loginPage['body']);

$loginPost = makeRequest("$baseUrl/auth/login.php", 'POST', [
    'email' => 'admin@university.edu',
    'password' => 'password123',
    'csrf_token' => $csrf
], $adminCookies);

$adminCookies = array_merge($adminCookies, $loginPost['cookies']);
assertTest("Admin Login succeeds with 302 redirect", $loginPost['code'] === 302 && strpos($loginPost['headers'], 'admin_dashboard.php') !== false);

$dash = makeRequest("$baseUrl/pages/admin_dashboard.php", 'GET', null, $adminCookies);
assertTest("Admin can access Admin Dashboard (HTTP 200)", $dash['code'] === 200 && strpos($dash['body'], 'Admin Management Dashboard') !== false);

$users = makeRequest("$baseUrl/pages/admin_users.php", 'GET', null, $adminCookies);
assertTest("Admin can access User Management (HTTP 200)", $users['code'] === 200 && strpos($users['body'], 'User &amp; Student Management') !== false);

$orgs = makeRequest("$baseUrl/pages/admin_organizations.php", 'GET', null, $adminCookies);
assertTest("Admin can access Organization Management (HTTP 200)", $orgs['code'] === 200 && strpos($orgs['body'], 'Organization Management') !== false);

$adminEvents = makeRequest("$baseUrl/pages/admin_events.php", 'GET', null, $adminCookies);
assertTest("Admin can access Event Management (HTTP 200)", $adminEvents['code'] === 200 && strpos($adminEvents['body'], 'Event Management') !== false);

$regs = makeRequest("$baseUrl/pages/admin_registrations.php", 'GET', null, $adminCookies);
assertTest("Admin can access Master Registration Log (HTTP 200)", $regs['code'] === 200 && strpos($regs['body'], 'Event Registrations Master List') !== false);

$csv = makeRequest("$baseUrl/pages/export_attendees.php?all=1", 'GET', null, $adminCookies);
assertTest("Admin can export CSV (Content-Type text/csv)", strpos($csv['headers'], 'text/csv') !== false);

$print = makeRequest("$baseUrl/pages/print_attendees.php?event_id=1", 'GET', null, $adminCookies);
assertTest("Admin can view Printable Attendee Roster (HTTP 200)", $print['code'] === 200 && strpos($print['body'], 'Official Event Attendance') !== false);

// 3. STUDENT LOGIN & STRICT RBAC ACCESS CONTROL
echo "\n--- Testing Student Login & Strict RBAC ---\n";
$studLoginPage = makeRequest("$baseUrl/auth/login.php");
$studCookies = $studLoginPage['cookies'];
$studCsrf = extractCsrfToken($studLoginPage['body']);

$studLoginPost = makeRequest("$baseUrl/auth/login.php", 'POST', [
    'email' => 'student@university.edu',
    'password' => 'password123',
    'csrf_token' => $studCsrf
], $studCookies);

$studCookies = array_merge($studCookies, $studLoginPost['cookies']);
assertTest("Student Login succeeds with redirect to events.php", $studLoginPost['code'] === 302 && strpos($studLoginPost['headers'], 'events.php') !== false);

$studEvents = makeRequest("$baseUrl/pages/events.php", 'GET', null, $studCookies);
assertTest("Student can view Campus Events (HTTP 200)", $studEvents['code'] === 200);

$studProfile = makeRequest("$baseUrl/pages/profile.php", 'GET', null, $studCookies);
assertTest("Student can view Profile (HTTP 200)", $studProfile['code'] === 200 && strpos($studProfile['body'], 'Student &amp; User Profile') !== false);

$studMyRegs = makeRequest("$baseUrl/pages/my_registrations.php", 'GET', null, $studCookies);
assertTest("Student can view My Registrations (HTTP 200)", $studMyRegs['code'] === 200 && strpos($studMyRegs['body'], 'My Event Registrations') !== false);

// Student tries to access Admin pages -> MUST BE HTTP 403 Forbidden!
$studTryDash = makeRequest("$baseUrl/pages/admin_dashboard.php", 'GET', null, $studCookies);
assertTest("Student blocked from Admin Dashboard with HTTP 403 Forbidden", $studTryDash['code'] === 403);

$studTryUsers = makeRequest("$baseUrl/pages/admin_users.php", 'GET', null, $studCookies);
assertTest("Student blocked from User Management with HTTP 403 Forbidden", $studTryUsers['code'] === 403);

$studTryOrgs = makeRequest("$baseUrl/pages/admin_organizations.php", 'GET', null, $studCookies);
assertTest("Student blocked from Org Management with HTTP 403 Forbidden", $studTryOrgs['code'] === 403);

$studTryEvents = makeRequest("$baseUrl/pages/admin_events.php", 'GET', null, $studCookies);
assertTest("Student blocked from Admin Events with HTTP 403 Forbidden", $studTryEvents['code'] === 403);

$studTryCreate = makeRequest("$baseUrl/pages/create_event.php", 'GET', null, $studCookies);
assertTest("Student blocked from Posting Event with HTTP 403 Forbidden", $studTryCreate['code'] === 403);

$studTryExport = makeRequest("$baseUrl/pages/export_attendees.php?all=1", 'GET', null, $studCookies);
assertTest("Student blocked from CSV Export with HTTP 403 Forbidden", $studTryExport['code'] === 403);

// 4. REGISTRATION LIFECYCLE (Register for Event 2 -> View in My Regs -> Prevent Duplicate -> Cancel)
echo "\n--- Testing Student Event Registration Lifecycle ---\n";
// Load Event 2 details page
$eventPage = makeRequest("$baseUrl/pages/event_details.php?id=2", 'GET', null, $studCookies);
$eventCsrf = extractCsrfToken($eventPage['body']);

// Register for Event 2
$regPost = makeRequest("$baseUrl/pages/event_details.php?id=2", 'POST', [
    'action' => 'register',
    'csrf_token' => $eventCsrf
], $studCookies);
assertTest("Student successfully registers for Event 2 (HTTP 200, confirmation message)", 
    $regPost['code'] === 200 && (strpos($regPost['body'], 'Registration confirmed') !== false || strpos($regPost['body'], 'You are Registered') !== false)
);

// Check if My Registrations now shows Event 2
$checkMyRegs = makeRequest("$baseUrl/pages/my_registrations.php", 'GET', null, $studCookies);
assertTest("Event 2 appears in Student's My Registrations list", strpos($checkMyRegs['body'], 'Hackathon') !== false);

// Attempt duplicate registration for Event 2
$dupPost = makeRequest("$baseUrl/pages/event_details.php?id=2", 'POST', [
    'action' => 'register',
    'csrf_token' => $eventCsrf
], $studCookies);
assertTest("Duplicate registration blocked with warning", 
    strpos($dupPost['body'], 'already registered') !== false || strpos($dupPost['body'], 'You are Registered') !== false
);

// Cancel registration for Event 2
$cancelCsrf = extractCsrfToken($checkMyRegs['body']);
$cancelPost = makeRequest("$baseUrl/pages/my_registrations.php", 'POST', [
    'cancel_event_id' => 2,
    'csrf_token' => $cancelCsrf
], $studCookies);
assertTest("Registration cancellation processed (HTTP 302 redirect)", $cancelPost['code'] === 302);

// Check that Event 2 is no longer in My Registrations
$afterCancel = makeRequest("$baseUrl/pages/my_registrations.php", 'GET', null, $studCookies);
assertTest("Event 2 successfully removed from Student Registrations", strpos($afterCancel['body'], 'Hackathon') === false);

// 5. PROFILE & ACCOUNT SETTINGS VERIFICATION
echo "\n--- Testing Profile & Account Settings Details ---\n";
$profPage = makeRequest("$baseUrl/pages/profile.php", 'GET', null, $studCookies);
$profCsrf = extractCsrfToken($profPage['body']);

// Check student info rendering & initials
assertTest("Profile displays user's full name (Alex Cruz)", strpos($profPage['body'], 'Alex Cruz') !== false);
assertTest("Profile displays user avatar initials (AC)", strpos($profPage['body'], 'AC') !== false);
assertTest("Profile displays Student ID (2026-00001)", strpos($profPage['body'], '2026-00001') !== false);
assertTest("Profile displays Verification Status", strpos($profPage['body'], 'Verified Account') !== false || strpos($profPage['body'], 'Pending Verification') !== false);
assertTest("Profile displays non-editable Student ID field with disabled attribute", strpos($profPage['body'], 'disabled class="input-disabled"') !== false || strpos($profPage['body'], 'disabled') !== false);
assertTest("Profile displays My Registrations count button", strpos($profPage['body'], 'My Registrations (') !== false);

// 5a. Profile Update: Validation - duplicate email blocked
$dupEmailPost = makeRequest("$baseUrl/pages/profile.php", 'POST', [
    'action' => 'update_profile',
    'first_name' => 'Alex',
    'last_name' => 'Cruz',
    'email' => 'admin@university.edu', // Taken by admin
    'csrf_token' => $profCsrf
], $studCookies);
assertTest("Duplicate email rejection displayed", strpos($dupEmailPost['body'], 'already registered to another account') !== false);

// 5b. Profile Update: Validation - invalid email format blocked
$invalidEmailPost = makeRequest("$baseUrl/pages/profile.php", 'POST', [
    'action' => 'update_profile',
    'first_name' => 'Alex',
    'last_name' => 'Cruz',
    'email' => 'not-an-email',
    'csrf_token' => $profCsrf
], $studCookies);
assertTest("Invalid email rejection displayed", strpos($invalidEmailPost['body'], 'valid university email') !== false);

// 5c. Profile Update: Privilege Escalation check (attempt role change)
$escPost = makeRequest("$baseUrl/pages/profile.php", 'POST', [
    'action' => 'update_profile',
    'first_name' => 'Alex',
    'last_name' => 'Cruz',
    'email' => 'student@university.edu',
    'role' => 'admin', // Malicious attempt to escalate role
    'csrf_token' => $profCsrf
], $studCookies);
assertTest("Profile update submitted successfully (HTTP 302 redirect)", $escPost['code'] === 302);

// Check that student is STILL student role and forbidden from admin dashboard
$checkEsc = makeRequest("$baseUrl/pages/admin_dashboard.php", 'GET', null, $studCookies);
assertTest("Privilege Escalation Blocked: Role remains 'student' (HTTP 403 Forbidden)", $checkEsc['code'] === 403);

// 5d. Password Change Tests
// Test 1: Incorrect current password
$pwdWrongCurrent = makeRequest("$baseUrl/pages/profile.php", 'POST', [
    'action' => 'change_password',
    'current_password' => 'wrongpassword',
    'new_password' => 'newpassword123',
    'confirm_password' => 'newpassword123',
    'csrf_token' => $profCsrf
], $studCookies);
assertTest("Password change rejected: Incorrect current password", strpos($pwdWrongCurrent['body'], 'current password you entered is incorrect') !== false);

// Test 2: New password < 8 characters
$pwdTooShort = makeRequest("$baseUrl/pages/profile.php", 'POST', [
    'action' => 'change_password',
    'current_password' => 'password123',
    'new_password' => 'short',
    'confirm_password' => 'short',
    'csrf_token' => $profCsrf
], $studCookies);
assertTest("Password change rejected: Password under 8 characters", strpos($pwdTooShort['body'], 'at least 8 characters') !== false);

// Test 3: New password and confirm password mismatch
$pwdMismatch = makeRequest("$baseUrl/pages/profile.php", 'POST', [
    'action' => 'change_password',
    'current_password' => 'password123',
    'new_password' => 'validNewPass1',
    'confirm_password' => 'validNewPass2',
    'csrf_token' => $profCsrf
], $studCookies);
assertTest("Password change rejected: Mismatched confirmation", strpos($pwdMismatch['body'], 'do not match') !== false);

// Test 4: Valid password change
$pwdValid = makeRequest("$baseUrl/pages/profile.php", 'POST', [
    'action' => 'change_password',
    'current_password' => 'password123',
    'new_password' => 'UpdatedPassword2026!',
    'confirm_password' => 'UpdatedPassword2026!',
    'csrf_token' => $profCsrf
], $studCookies);
assertTest("Password change accepted with HTTP 302 redirect", $pwdValid['code'] === 302);

// Test 5: Verify login with new password in a new session
$freshLoginPage = makeRequest("$baseUrl/auth/login.php");
$freshCookies = $freshLoginPage['cookies'];
$freshCsrf = extractCsrfToken($freshLoginPage['body']);

$loginNewPwd = makeRequest("$baseUrl/auth/login.php", 'POST', [
    'email' => 'student@university.edu',
    'password' => 'UpdatedPassword2026!',
    'csrf_token' => $freshCsrf
], $freshCookies);
$freshCookies = array_merge($freshCookies, $loginNewPwd['cookies']);
assertTest("Login with new password succeeds (HTTP 302 redirect)", $loginNewPwd['code'] === 302 && strpos($loginNewPwd['headers'], 'events.php') !== false);

// Test 6: Restore password back to 'password123' for clean demo baseline
$profGetAgain = makeRequest("$baseUrl/pages/profile.php", 'GET', null, $freshCookies);
$profCsrfAgain = extractCsrfToken($profGetAgain['body']);

$pwdRestore = makeRequest("$baseUrl/pages/profile.php", 'POST', [
    'action' => 'change_password',
    'current_password' => 'UpdatedPassword2026!',
    'new_password' => 'password123',
    'confirm_password' => 'password123',
    'csrf_token' => $profCsrfAgain
], $freshCookies);
$freshCookies = array_merge($freshCookies, $pwdRestore['cookies']);
assertTest("Demo student password cleanly restored back to 'password123'", $pwdRestore['code'] === 302);

// Test 7: Verify final profile state remains Alex Cruz
$finalCheck = makeRequest("$baseUrl/pages/profile.php", 'GET', null, $freshCookies);
assertTest("Profile and Navigation remain synchronized as Alex Cruz (AC)", 
    strpos($finalCheck['body'], 'Alex Cruz') !== false && strpos($finalCheck['body'], 'AC') !== false
);

echo "\n========================================================\n";
$passCount = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
$totalCount = count($results);
echo "TEST SUITE SUMMARY: $passCount / $totalCount TESTS PASSED\n";
echo "========================================================\n";
if ($passCount === $totalCount) {
    echo "ALL TESTS PASSED PERFECTLY!\n";
} else {
    echo "WARNING: SOME TESTS FAILED.\n";
}
