<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $db = new SQLite3('/var/www/html/cloudfarm-air-license/database.sqlite');

    // Criar tabela de dispositivos
    $db->exec('CREATE TABLE IF NOT EXISTS devices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        android_id TEXT UNIQUE NOT NULL,
        username TEXT NOT NULL,
        activation_date DATETIME NOT NULL,
        expiry_date DATETIME NOT NULL,
        last_online_check DATETIME NOT NULL,
        app_version TEXT,
        device_info TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    // Criar tabela de logs
    $db->exec('CREATE TABLE IF NOT EXISTS access_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        android_id TEXT NOT NULL,
        username TEXT NOT NULL,
        action TEXT NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        ip_address TEXT
    )');

    echo json_encode(['status' => 'success', 'message' => 'Database created successfully']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?> 