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
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    if (empty($android_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing android_id']);
        exit;
    }

    // Buscar dispositivo
    $stmt = $db->prepare('SELECT * FROM devices WHERE android_id = ?');
    $stmt->bindValue(1, $android_id, SQLITE3_TEXT);
    $result = $stmt->execute();
    $device = $result->fetchArray(SQLITE3_ASSOC);

    if (!$device) {
        echo json_encode([
            'status' => 'not_registered',
            'message' => 'Device not registered'
        ]);
        exit;
    }

    // Atualizar último check online
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare('UPDATE devices SET last_online_check = ? WHERE android_id = ?');
    $stmt->bindValue(1, $now, SQLITE3_TEXT);
    $stmt->bindValue(2, $android_id, SQLITE3_TEXT);
    $stmt->execute();

    // Verificar validade da licença
    $expiry_date = new DateTime($device['expiry_date']);
    $current_time = new DateTime();
    $last_online = new DateTime($device['last_online_check']);
    
    // Verificar se passou do período de graça offline (15 dias)
    $days_offline = $current_time->diff($last_online)->days;
    $offline_grace_days = 15;

    if ($current_time > $expiry_date) {
        echo json_encode([
            'status' => 'expired',
            'message' => 'License expired',
            'expiry_date' => $device['expiry_date']
        ]);
    } elseif ($days_offline > $offline_grace_days) {
        // AQUI ESTÁ A CORREÇÃO: Verificar período offline
        echo json_encode([
            'status' => 'offline_expired',
            'message' => 'Device offline for too long (' . $days_offline . ' days)',
            'expiry_date' => $device['expiry_date'],
            'last_online_check' => $device['last_online_check'],
            'days_offline' => $days_offline,
            'offline_grace_days' => $offline_grace_days,
            'username' => $device['username']
        ]);
    } else {
        $days_remaining = $current_time->diff($expiry_date)->days;
        echo json_encode([
            'status' => 'valid',
            'message' => 'License valid',
            'expiry_date' => $device['expiry_date'],
            'days_remaining' => $days_remaining,
            'username' => $device['username'],
            'last_online_check' => $now,
            'days_offline' => $days_offline,
            'offline_grace_days' => $offline_grace_days
        ]);
    }

    // Log da verificação
    $stmt = $db->prepare('INSERT INTO access_logs (android_id, username, action, ip_address) VALUES (?, ?, ?, ?)');
    $stmt->bindValue(1, $android_id, SQLITE3_TEXT);
    $stmt->bindValue(2, $username ?: $device['username'], SQLITE3_TEXT);
    $stmt->bindValue(3, 'license_check', SQLITE3_TEXT);
    $stmt->bindValue(4, $ip_address, SQLITE3_TEXT);
    $stmt->execute();

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?> 