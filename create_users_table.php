<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $db = new SQLite3('/var/www/html/cloudfarm-air-license/database.sqlite');

    // Criar tabela de usuários
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        display_name TEXT NOT NULL,
        active BOOLEAN DEFAULT 1,
        max_devices INTEGER DEFAULT 1,
        license_duration_months INTEGER DEFAULT 12,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    // Migrar usuários existentes (se ainda não existirem)
    $existing = $db->query('SELECT COUNT(*) as total FROM users')->fetchArray(SQLITE3_ASSOC)['total'];
    
    if ($existing == 0) {
        // Inserir os 3 usuários originais
        $users = [
            ['user1', 'KLG747', 'Usuário 1'],
            ['user2', 'KLG787', 'Usuário 2'], 
            ['user3', 'KLG788', 'Usuário 3']
        ];
        
        foreach ($users as $user) {
            $stmt = $db->prepare('INSERT INTO users (username, password, display_name) VALUES (?, ?, ?)');
            $stmt->bindValue(1, $user[0], SQLITE3_TEXT);
            $stmt->bindValue(2, $user[1], SQLITE3_TEXT);
            $stmt->bindValue(3, $user[2], SQLITE3_TEXT);
            $stmt->execute();
        }
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Tabela de usuários criada e usuários originais migrados',
            'users_created' => count($users)
        ]);
    } else {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Tabela de usuários já existe',
            'existing_users' => $existing
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?> 