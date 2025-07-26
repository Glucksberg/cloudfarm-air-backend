<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $db = new SQLite3('/var/www/html/cloudfarm-air-license/database.sqlite');

    // Contar dispositivos ativos
    $result = $db->query('SELECT COUNT(*) as total FROM devices WHERE expiry_date > datetime("now")');
    $active_devices = $result->fetchArray(SQLITE3_ASSOC)['total'];

    // Contar dispositivos expirados
    $result = $db->query('SELECT COUNT(*) as total FROM devices WHERE expiry_date <= datetime("now")');
    $expired_devices = $result->fetchArray(SQLITE3_ASSOC)['total'];

    // Dispositivos que não fazem check há mais de 7 dias
    $result = $db->query('SELECT COUNT(*) as total FROM devices WHERE last_online_check < datetime("now", "-7 days") AND expiry_date > datetime("now")');
    $offline_devices = $result->fetchArray(SQLITE3_ASSOC)['total'];

    echo json_encode([
        'status' => 'success',
        'server_time' => date('Y-m-d H:i:s'),
        'active_devices' => $active_devices,
        'expired_devices' => $expired_devices,
        'offline_devices' => $offline_devices,
        'offline_grace_period' => '7 days'
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?> 