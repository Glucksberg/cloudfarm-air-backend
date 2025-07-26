<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $db = new SQLite3('/var/www/html/cloudfarm-air-license/database.sqlite');

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $android_id = $_POST['android_id'] ?? '';
    $app_version = $_POST['app_version'] ?? '1.0.0';
    $device_info = $_POST['device_info'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    if (empty($username) || empty($password) || empty($android_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields: username, password, android_id']);
        exit;
    }

    // Verificar credenciais do usuário
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ? AND password = ? AND active = 1');
    $stmt->bindValue(1, $username, SQLITE3_TEXT);
    $stmt->bindValue(2, $password, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        // Log da tentativa de login inválida
        $stmt = $db->prepare('INSERT INTO access_logs (android_id, username, action, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->bindValue(1, $android_id, SQLITE3_TEXT);
        $stmt->bindValue(2, $username, SQLITE3_TEXT);
        $stmt->bindValue(3, 'invalid_login', SQLITE3_TEXT);
        $stmt->bindValue(4, $ip_address, SQLITE3_TEXT);
        $stmt->execute();

        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid credentials'
        ]);
        exit;
    }

    // Verificar se dispositivo já está registrado
    $stmt = $db->prepare('SELECT * FROM devices WHERE android_id = ?');
    $stmt->bindValue(1, $android_id, SQLITE3_TEXT);
    $result = $stmt->execute();
    $existing_device = $result->fetchArray(SQLITE3_ASSOC);

    if ($existing_device) {
        // Dispositivo já registrado - atualizar último check e verificar se mudou de usuário
        $now = date('Y-m-d H:i:s');
        
        // Se mudou de usuário, atualizar
        if ($existing_device['username'] !== $username) {
            $stmt = $db->prepare('UPDATE devices SET username = ?, last_online_check = ? WHERE android_id = ?');
            $stmt->bindValue(1, $username, SQLITE3_TEXT);
            $stmt->bindValue(2, $now, SQLITE3_TEXT);
            $stmt->bindValue(3, $android_id, SQLITE3_TEXT);
            $stmt->execute();
        } else {
            $stmt = $db->prepare('UPDATE devices SET last_online_check = ? WHERE android_id = ?');
            $stmt->bindValue(1, $now, SQLITE3_TEXT);
            $stmt->bindValue(2, $android_id, SQLITE3_TEXT);
            $stmt->execute();
        }

        // Verificar se licença ainda é válida
        $expiry_date = new DateTime($existing_device['expiry_date']);
        $current_time = new DateTime();

        if ($current_time > $expiry_date) {
            echo json_encode([
                'status' => 'expired',
                'message' => 'License expired',
                'expiry_date' => $existing_device['expiry_date'],
                'user_info' => [
                    'username' => $username,
                    'display_name' => $user['display_name']
                ]
            ]);
        } else {
            $days_remaining = $current_time->diff($expiry_date)->days;
            echo json_encode([
                'status' => 'success',
                'message' => 'Login successful - Device already registered',
                'license_info' => [
                    'expiry_date' => $existing_device['expiry_date'],
                    'days_remaining' => $days_remaining,
                    'last_online_check' => $now,
                    'offline_grace_days' => 7
                ],
                'user_info' => [
                    'username' => $username,
                    'display_name' => $user['display_name']
                ]
            ]);
        }

        // Log da tentativa
        $stmt = $db->prepare('INSERT INTO access_logs (android_id, username, action, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->bindValue(1, $android_id, SQLITE3_TEXT);
        $stmt->bindValue(2, $username, SQLITE3_TEXT);
        $stmt->bindValue(3, 'login_existing_device', SQLITE3_TEXT);
        $stmt->bindValue(4, $ip_address, SQLITE3_TEXT);
        $stmt->execute();

    } else {
        // Primeiro login - apenas registrar nos logs
        $now = date('Y-m-d H:i:s');
        $license_months = $user['license_duration_months'];
        $user_expiry = date('Y-m-d H:i:s', strtotime($user['created_at'] . " +{$license_months} months"));
        
        // Verificar se licença do usuário ainda é válida
        $current_time = new DateTime();
        $expiry_date = new DateTime($user_expiry);
        
        if ($current_time > $expiry_date) {
            echo json_encode([
                'status' => 'expired',
                'message' => 'User license expired',
                'expiry_date' => $user_expiry,
                'user_info' => [
                    'username' => $username,
                    'display_name' => $user['display_name']
                ]
            ]);
        } else {
            $days_remaining = $current_time->diff($expiry_date)->days;
            echo json_encode([
                'status' => 'success',
                'message' => 'Login successful',
                'license_info' => [
                    'expiry_date' => $user_expiry,
                    'days_remaining' => $days_remaining,
                    'last_online_check' => $now,
                    'offline_grace_days' => 7
                ],
                'user_info' => [
                    'username' => $username,
                    'display_name' => $user['display_name']
                ]
            ]);
        }

        // Log apenas para auditoria (sem criar registro de dispositivo)
        $stmt = $db->prepare('INSERT INTO access_logs (android_id, username, action, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->bindValue(1, $android_id, SQLITE3_TEXT);
        $stmt->bindValue(2, $username, SQLITE3_TEXT);
        $stmt->bindValue(3, 'user_login', SQLITE3_TEXT);
        $stmt->bindValue(4, $ip_address, SQLITE3_TEXT);
        $stmt->execute();
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?> 