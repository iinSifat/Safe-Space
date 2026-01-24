<?php
/**
 * CLI-only seeder for local/dev.
 * - Verifies/creates a specific professional account
 * - Creates a few dummy client accounts
 * - Seeds professional_sessions for demo/testing
 *
 * Usage (PowerShell):
 *   C:\xampp\php\php.exe .\scripts\seed_professional_demo.php
 */

require_once __DIR__ . '/../config/config.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

$email = 'albir1@gmail.com';
$password = 'Albir@123';
$usernameFallback = 'albir1';
$fullName = 'Albir';

$db = Database::getInstance();
$conn = $db->getConnection();

function cli_out($msg) {
    echo $msg . PHP_EOL;
}

function get_user_by_email($conn, $email) {
    $stmt = $conn->prepare('SELECT user_id, username, user_type, is_verified, is_active FROM users WHERE email = ? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function ensure_user($conn, $email, $username, $passwordPlain, $userType, $fullName = null) {
    $existing = get_user_by_email($conn, $email);
    $hash = password_hash($passwordPlain, PASSWORD_BCRYPT, ['cost' => 12]);

    if (!$existing) {
        $stmt = $conn->prepare('INSERT INTO users (username, email, password_hash, full_name, user_type, is_anonymous, is_verified, is_active) VALUES (?, ?, ?, ?, ?, 0, 1, 1)');
        if (!$stmt) throw new Exception('Failed to prepare INSERT users');
        $stmt->bind_param('sssss', $username, $email, $hash, $fullName, $userType);
        $stmt->execute();
        $stmt->close();

        return (int)$conn->insert_id;
    }

    $userId = (int)$existing['user_id'];

    // Ensure role + verification + (optionally) reset password for the requested account.
    $stmt = $conn->prepare('UPDATE users SET user_type = ?, is_verified = 1, is_active = 1, password_hash = ?, full_name = COALESCE(NULLIF(?, \'\'), full_name) WHERE user_id = ?');
    if (!$stmt) throw new Exception('Failed to prepare UPDATE users');
    $fullName = (string)($fullName ?? '');
    $stmt->bind_param('sssi', $userType, $hash, $fullName, $userId);
    $stmt->execute();
    $stmt->close();

    return $userId;
}

function ensure_professional_profile($conn, $userId, $fullName, $specialization) {
    $stmt = $conn->prepare('SELECT professional_id FROM professionals WHERE user_id = ? LIMIT 1');
    if (!$stmt) throw new Exception('Failed to prepare SELECT professionals');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        // Required fields in schema are NOT NULL.
        $licenseNumber = 'DEMO-ALBIR-001';
        $licenseCountry = 'BD';
        $degree = 'MSc Psychology';
        $years = 5;
        $fee = 800.00;

        $ins = $conn->prepare("INSERT INTO professionals (user_id, full_name, specialization, license_number, license_country, degree, years_of_experience, consultation_fee, is_accepting_patients, verification_status, verified_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'verified', NOW())");
        if (!$ins) throw new Exception('Failed to prepare INSERT professionals');
        $ins->bind_param('isssssid', $userId, $fullName, $specialization, $licenseNumber, $licenseCountry, $degree, $years, $fee);
        $ins->execute();
        $ins->close();
        return;
    }

    $upd = $conn->prepare("UPDATE professionals SET full_name = ?, specialization = ?, is_accepting_patients = 1, verification_status = 'verified', verified_at = COALESCE(verified_at, NOW()) WHERE user_id = ?");
    if (!$upd) throw new Exception('Failed to prepare UPDATE professionals');
    $upd->bind_param('ssi', $fullName, $specialization, $userId);
    $upd->execute();
    $upd->close();
}

function ensure_demo_client($conn, $email, $username) {
    $row = get_user_by_email($conn, $email);
    if ($row) {
        // keep existing password
        return (int)$row['user_id'];
    }

    $hash = password_hash('Client@123', PASSWORD_BCRYPT, ['cost' => 12]);
    $fullName = $username;
    $userType = 'patient';
    $stmt = $conn->prepare('INSERT INTO users (username, email, password_hash, full_name, user_type, is_anonymous, is_verified, is_active) VALUES (?, ?, ?, ?, ?, 0, 1, 1)');
    if (!$stmt) throw new Exception('Failed to prepare INSERT demo client');
    $stmt->bind_param('sssss', $username, $email, $hash, $fullName, $userType);
    $stmt->execute();
    $stmt->close();
    return (int)$conn->insert_id;
}

function insert_session($conn, $proUserId, $clientUserId, $clientAlias, $status, $primaryConcern, $riskLevel, $scheduledAt = null, $notes = null, $riskAssessment = 'low', $followUp = 0, $isEmergency = 0) {
    $stmt = $conn->prepare('INSERT INTO professional_sessions (professional_user_id, client_user_id, client_alias, status, primary_concern, risk_level, scheduled_at, private_notes, risk_assessment, follow_up_required, is_emergency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) throw new Exception('Failed to prepare INSERT professional_sessions');

    // allow NULL datetime
    $scheduledAtVal = $scheduledAt;
    $notesVal = $notes;
    $followUp = (int)$followUp;
    $isEmergency = (int)$isEmergency;

    $stmt->bind_param('iissssssiii', $proUserId, $clientUserId, $clientAlias, $status, $primaryConcern, $riskLevel, $scheduledAtVal, $notesVal, $riskAssessment, $followUp, $isEmergency);
    $stmt->execute();
    $stmt->close();
}

try {
    // Ensure sessions table exists (best-effort)
    if (function_exists('ensure_professional_sessions_table')) {
        ensure_professional_sessions_table();
    }

    cli_out('Seeding professional demo data...');

    $proUser = get_user_by_email($conn, $email);
    if (!$proUser) {
        cli_out('Professional account not found. Creating it...');
    } else {
        cli_out('Professional account found. Updating verification + password...');
    }

    $proUserId = ensure_user($conn, $email, $usernameFallback, $password, 'professional', $fullName);
    ensure_professional_profile($conn, $proUserId, $fullName, 'Clinical Psychology');

    cli_out('Verified professional: ' . $email . ' (user_id=' . $proUserId . ')');

    // Seed a few client accounts
    $clients = [
        ['email' => 'demo.client1@safespace.local', 'username' => 'demo_client1'],
        ['email' => 'demo.client2@safespace.local', 'username' => 'demo_client2'],
        ['email' => 'demo.client3@safespace.local', 'username' => 'demo_client3'],
    ];

    $clientIds = [];
    foreach ($clients as $c) {
        $cid = ensure_demo_client($conn, $c['email'], $c['username']);
        $clientIds[] = $cid;
        cli_out('Ensured demo client: ' . $c['email'] . ' (user_id=' . $cid . ')');
    }

    // Seed sessions only if there are none yet (avoid endless duplicates)
    $countStmt = $conn->prepare('SELECT COUNT(*) AS c FROM professional_sessions WHERE professional_user_id = ?');
    $countStmt->bind_param('i', $proUserId);
    $countStmt->execute();
    $existingCount = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $countStmt->close();

    if ($existingCount > 0) {
        cli_out('Sessions already exist for this professional (' . $existingCount . '). Skipping session inserts.');
        cli_out('Done.');
        exit(0);
    }

    $now = new DateTime('now', new DateTimeZone('UTC'));
    $tomorrow10 = (clone $now)->modify('+1 day')->setTime(10, 0, 0)->format('Y-m-d H:i:s');
    $tomorrow14 = (clone $now)->modify('+1 day')->setTime(14, 0, 0)->format('Y-m-d H:i:s');

    $alias1 = function_exists('professional_client_alias') ? professional_client_alias($clientIds[0]) : ('Client-' . $clientIds[0]);
    $alias2 = function_exists('professional_client_alias') ? professional_client_alias($clientIds[1]) : ('Client-' . $clientIds[1]);
    $alias3 = function_exists('professional_client_alias') ? professional_client_alias($clientIds[2]) : ('Client-' . $clientIds[2]);

    insert_session($conn, $proUserId, $clientIds[0], $alias1, 'requested', 'Anxiety (work stress)', 'medium', null, null, 'low', 0, 0);
    insert_session($conn, $proUserId, $clientIds[1], $alias2, 'accepted', 'Sleep difficulty', 'low', $tomorrow10, 'Initial intake planned. Focus on sleep hygiene + stressors. Not medical advice.', 'low', 0, 0);
    insert_session($conn, $proUserId, $clientIds[2], $alias3, 'accepted', 'Crisis ideation mention', 'high', $tomorrow14, 'Flagged for close monitoring. Provided crisis resources and safety planning guidance.', 'high', 1, 1);

    cli_out('Inserted 3 demo sessions (requested/accepted/high-risk follow-up).');
    cli_out('Done.');
} catch (Throwable $e) {
    cli_out('Seed failed: ' . $e->getMessage());
    exit(1);
}
