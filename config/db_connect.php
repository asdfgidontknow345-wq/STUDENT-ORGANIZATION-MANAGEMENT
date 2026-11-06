<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/security.php';

$host = "localhost";
$dbname = "student_org_system";
$username = "root";
$password = "";

$conn = null;
$db_driver = 'mysql';

try {
    // 1. Try MySQL connection
    $conn = new PDO("mysql:host=$host;charset=utf8", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 2
    ]);
    
    // Ensure database exists
    $conn->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->exec("USE `$dbname`");
    
    // Check if tables exist, if not initialize them
    $check = $conn->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$check) {
        initMySqlDatabase($conn);
    }
} catch(PDOException $e) {
    // 2. Seamless fallback to SQLite if MySQL is not running
    $db_driver = 'sqlite';
    $sqlitePath = __DIR__ . '/database.sqlite';
    $needInit = !file_exists($sqlitePath) || filesize($sqlitePath) === 0;
    
    $conn = new PDO("sqlite:" . $sqlitePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $conn->exec("PRAGMA foreign_keys = ON;");
    
    if ($needInit) {
        initSqliteDatabase($conn);
    }
}

// Ensure optional columns exist without breaking changes
try {
    $conn->exec("ALTER TABLE organizations ADD COLUMN officer_id INTEGER NULL");
} catch(Exception $e) {
    // Column already exists
}

function initSqliteDatabase($pdo) {
    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id TEXT UNIQUE NOT NULL,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        verification_code TEXT NULL,
        is_verified INTEGER DEFAULT 0,
        role TEXT DEFAULT 'student',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS organizations (
        org_id INTEGER PRIMARY KEY AUTOINCREMENT,
        org_name TEXT NOT NULL,
        org_description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS events (
        event_id INTEGER PRIMARY KEY AUTOINCREMENT,
        org_id INTEGER NOT NULL,
        event_title TEXT NOT NULL,
        event_description TEXT,
        event_date TEXT NOT NULL,
        event_venue TEXT NOT NULL,
        registration_open INTEGER DEFAULT 1,
        max_participants INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (org_id) REFERENCES organizations(org_id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS registrations (
        reg_id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        event_id INTEGER NOT NULL,
        registration_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
        UNIQUE (user_id, event_id)
    );
    ";
    $pdo->exec($sql);
    seedInitialData($pdo);
}

function initMySqlDatabase($pdo) {
    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        user_id INT PRIMARY KEY AUTO_INCREMENT,
        student_id VARCHAR(20) UNIQUE NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        verification_code VARCHAR(255) NULL,
        is_verified TINYINT(1) DEFAULT 0,
        role ENUM('student','officer','admin') DEFAULT 'student',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS organizations (
        org_id INT PRIMARY KEY AUTO_INCREMENT,
        org_name VARCHAR(100) NOT NULL,
        org_description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS events (
        event_id INT PRIMARY KEY AUTO_INCREMENT,
        org_id INT NOT NULL,
        event_title VARCHAR(150) NOT NULL,
        event_description TEXT,
        event_date DATE NOT NULL,
        event_venue VARCHAR(100) NOT NULL,
        registration_open TINYINT(1) DEFAULT 1,
        max_participants INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (org_id) REFERENCES organizations(org_id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS registrations (
        reg_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        event_id INT NOT NULL,
        registration_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
        UNIQUE KEY unique_reg (user_id, event_id)
    );
    ";
    $pdo->exec($sql);
    seedInitialData($pdo);
}

function seedInitialData($pdo) {
    // Organizations
    $orgs = [
        ['Computer Society', 'Official student organization for Information Technology and Computer Science students.'],
        ['Junior Philippine Institute of Accountants', 'Fostering excellence, integrity, and competence among accounting students.'],
        ['Arts & Culture Guild', 'Promoting artistic expression, music, theater, and creative university culture.'],
        ['Supreme Student Council', 'The highest governing student council dedicated to student representation and advocacy.']
    ];
    $stmt = $pdo->prepare("INSERT INTO organizations (org_name, org_description) VALUES (?, ?)");
    foreach ($orgs as $org) {
        $stmt->execute($org);
    }

    // Events
    $events = [
        [1, 'Tech Career Summit 2026', 'Connect with tech leaders, learn resume writing for developers, and explore internship opportunities.', '2026-11-15', 'Main University Auditorium', 1, 150],
        [1, 'Annual Hackathon: CodeForGood', 'A 24-hour coding sprint building impactful tech solutions for local communities.', '2026-11-28', 'Innovation & IT Lab Hub', 1, 60],
        [2, 'Financial Literacy & Investment 101', 'Learn personal finance, budgeting, and ethical investment strategies for college students.', '2026-11-20', 'Business Hall Room 302', 1, 100],
        [3, 'Campus Performing Arts Showcase', 'An evening celebrating student drama, contemporary music, and dance performances.', '2026-12-05', 'University Amphitheater', 1, 200],
        [4, 'Student Leadership & Governance Forum', 'Engage with university leaders on campus initiatives and student welfare policies.', '2026-12-10', 'Student Activity Center', 1, 120]
    ];
    $stmt = $pdo->prepare("INSERT INTO events (org_id, event_title, event_description, event_date, event_venue, registration_open, max_participants) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($events as $event) {
        $stmt->execute($event);
    }

    // Demo Users
    $demoPassword = password_hash('password123', PASSWORD_DEFAULT);
    $users = [
        ['2026-00001', 'Alex', 'Cruz', 'student@university.edu', $demoPassword, null, 1, 'student'],
        ['2026-ADMIN', 'Admin', 'User', 'admin@university.edu', $demoPassword, null, 1, 'admin'],
        ['2026-00002', 'Maria', 'Santos', 'maria@university.edu', $demoPassword, null, 1, 'student']
    ];
    $stmt = $pdo->prepare("INSERT INTO users (student_id, first_name, last_name, email, password, verification_code, is_verified, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($users as $user) {
        $stmt->execute($user);
    }

    // Demo Registration
    $reg = $pdo->prepare("INSERT INTO registrations (user_id, event_id) VALUES (?, ?)");
    $reg->execute([1, 1]); // Alex Cruz registered for Tech Career Summit
}
?>

