<?php
/**
 * BACKGROUND CHECK - Endpoint Leve para Verificações Automáticas
 * 
 * Este endpoint é chamado automaticamente pelo app a cada 3 horas
 * em background, mesmo com o app fechado, para "estender" o período
 * offline quando o dispositivo estiver em área de serviço.
 * 
 * Funcionalidade:
 * - Verificação rápida e leve
 * - Atualiza apenas last_online_check se dispositivo registrado
 * - Não faz login completo
 * - Permite que piloto "estenda" período offline automaticamente
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $db = new SQLite3('/var/www/html/cloudfarm-air-license/database.sqlite');

    $android_id = $_POST['android_id'] ?? '';
    $username = $_POST['username'] ?? '';
    $app_version = $_POST['app_version'] ?? '1.0.0';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    if (empty($android_id)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing android_id',
            'background_check' => true
        ]);
        exit;
    }

    // Buscar dispositivo (se existir)
    $stmt = $db->prepare('SELECT * FROM devices WHERE android_id = ?');
    $stmt->bindValue(1, $android_id, SQLITE3_TEXT);
    $result = $stmt->execute();
    $device = $result->fetchArray(SQLITE3_ASSOC);

    if (!$device) {
        // Dispositivo não registrado - background check não pode fazer nada
        echo json_encode([
            'status' => 'not_registered',
            'message' => 'Device not registered - use login first',
            'background_check' => true
        ]);
        exit;
    }

    // Verificar se usuário ainda está ativo
    if (!empty($username)) {
        $stmt = $db->prepare('SELECT active FROM users WHERE username = ?');
        $stmt->bindValue(1, $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$user || !$user['active']) {
            echo json_encode([
                'status' => 'user_inactive',
                'message' => 'User is inactive',
                'background_check' => true
            ]);
            exit;
        }
    }

    // ✅ PRINCIPAL: Atualizar last_online_check (estender período offline)
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare('UPDATE devices SET last_online_check = ?, app_version = ? WHERE android_id = ?');
    $stmt->bindValue(1, $now, SQLITE3_TEXT);
    $stmt->bindValue(2, $app_version, SQLITE3_TEXT);
    $stmt->bindValue(3, $android_id, SQLITE3_TEXT);
    $stmt->execute();

    // Verificar status atual da licença
    $expiry_date = new DateTime($device['expiry_date']);
    $current_time = new DateTime();
    $last_online = new DateTime($now); // Agora está online
    
    $offline_grace_days = 15;
    $days_offline = 0; // Acabou de conectar

    if ($current_time > $expiry_date) {
        $status = 'expired';
        $message = 'License expired';
    } else {
        $status = 'extended';
        $message = 'Offline period extended - background check successful';
        $days_remaining = $current_time->diff($expiry_date)->days;
    }

    // Resposta leve para background
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'background_check' => true,
        'timestamp' => $now,
        'last_online_check' => $now,
        'days_offline' => $days_offline,
        'offline_grace_days' => $offline_grace_days,
        'days_remaining' => $days_remaining ?? 0
    ]);

    // Log discreto do background check
    $stmt = $db->prepare('INSERT INTO access_logs (android_id, username, action, ip_address) VALUES (?, ?, ?, ?)');
    $stmt->bindValue(1, $android_id, SQLITE3_TEXT);
    $stmt->bindValue(2, $username ?: $device['username'], SQLITE3_TEXT);
    $stmt->bindValue(3, 'background_check', SQLITE3_TEXT);
    $stmt->bindValue(4, $ip_address, SQLITE3_TEXT);
    $stmt->execute();

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'background_check' => true
    ]);
}
?> 