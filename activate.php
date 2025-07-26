<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $db = new SQLite3('/var/www/html/cloudfarm-air-license/database.sqlite');

    $android_id = $_POST['android_id'] ?? '';
    $username = $_POST['username'] ?? '';
    $app_version = $_POST['app_version'] ?? '';
    $device_info = $_POST['device_info'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    if (empty($android_id) || empty($username)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit;
    }

    // Verificar se dispositivo já está registrado
    $stmt = $db->prepare('SELECT * FROM devices WHERE android_id = ?');
    $stmt->bindValue(1, $android_id, SQLITE3_TEXT);
    $result = $stmt->execute();
    $existing = $result->fetchArray(SQLITE3_ASSOC);

    if ($existing) {
        // Dispositivo já registrado - atualizar último check online
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE devices SET last_online_check = ? WHERE android_id = ?');
        $stmt->bindValue(1, $now, SQLITE3_TEXT);
        $stmt->bindValue(2, $android_id, SQLITE3_TEXT);
        $stmt->execute();

        // Verificar se ainda é válido
        $expiry_date = new DateTime($existing['expiry_date']);
        $current_time = new DateTime();

        if ($current_time > $expiry_date) {
            echo json_encode([
                'status' => 'expired',
                'message' => 'License expired',
                'expiry_date' => $existing['expiry_date']
            ]);
        } else {
            $days_remaining = $current_time->diff($expiry_date)->days;
            echo json_encode([
                'status' => 'valid',
                'message' => 'Device already activated',
                'expiry_date' => $existing['expiry_date'],
                'days_remaining' => $days_remaining,
                'username' => $existing['username'],
                'last_online_check' => $now,
                'offline_grace_days' => 7
            ]);
        }

        // Log da tentativa
        $stmt = $db->prepare('INSERT INTO access_logs (android_id, username, action, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->bindValue(1, $android_id, SQLITE3_TEXT);
        $stmt->bindValue(2, $username, SQLITE3_TEXT);
        $stmt->bindValue(3, 'check_existing', SQLITE3_TEXT);
        $stmt->bindValue(4, $ip_address, SQLITE3_TEXT);
        $stmt->execute();

    } else {
        // Primeiro uso - registrar dispositivo
        $activation_date = date('Y-m-d H:i:s');
        $expiry_date = date('Y-m-d H:i:s', strtotime('+1 year'));
        $last_online_check = $activation_date;

        $stmt = $db->prepare('INSERT INTO devices (android_id, username, activation_date, expiry_date, last_online_check, app_version, device_info) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bindValue(1, $android_id, SQLITE3_TEXT);
        $stmt->bindValue(2, $username, SQLITE3_TEXT);
        $stmt->bindValue(3, $activation_date, SQLITE3_TEXT);
        $stmt->bindValue(4, $expiry_date, SQLITE3_TEXT);
        $stmt->bindValue(5, $last_online_check, SQLITE3_TEXT);
        $stmt->bindValue(6, $app_version, SQLITE3_TEXT);
        $stmt->bindValue(7, $device_info, SQLITE3_TEXT);
        $result = $stmt->execute();

        if ($result) {
            $days_remaining = 365; // 1 ano
            echo json_encode([
                'status' => 'activated',
                'message' => 'Device activated successfully',
                'activation_date' => $activation_date,
                'expiry_date' => $expiry_date,
                'days_remaining' => $days_remaining,
                'username' => $username,
                'last_online_check' => $last_online_check,
                'offline_grace_days' => 7
            ]);

            // Log da ativação
            $stmt = $db->prepare('INSERT INTO access_logs (android_id, username, action, ip_address) VALUES (?, ?, ?, ?)');
            $stmt->bindValue(1, $android_id, SQLITE3_TEXT);
            $stmt->bindValue(2, $username, SQLITE3_TEXT);
            $stmt->bindValue(3, 'first_activation', SQLITE3_TEXT);
            $stmt->bindValue(4, $ip_address, SQLITE3_TEXT);
            $stmt->execute();

        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to activate device']);
        }
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?> 