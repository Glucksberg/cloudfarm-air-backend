<?php
/**
 * TESTE: Background Check Automático
 * 
 * Simula o comportamento do background check que rodará
 * a cada 3 horas no Android, mesmo com app fechado.
 */

echo "🔋 TESTE: BACKGROUND CHECK AUTOMÁTICO\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Conectar ao banco
try {
    $db = new SQLite3('/var/www/html/cloudfarm-air-license/database.sqlite');
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit;
}

// Criar dispositivo de teste
$android_id = 'test_background_' . uniqid();
$username = 'user1';
$activation_date = date('Y-m-d H:i:s');
$expiry_date = date('Y-m-d H:i:s', strtotime('+6 months'));

// Dispositivo offline há 10 dias
$last_online_check = date('Y-m-d H:i:s', strtotime('-10 days'));

echo "📱 CENÁRIO DE TESTE:\n";
echo "-" . str_repeat("-", 30) . "\n";
echo "Android ID: $android_id\n";
echo "Username: $username\n";
echo "Último online: $last_online_check (10 dias atrás)\n";
echo "Status inicial: OFFLINE há 10 dias\n\n";

// Inserir dispositivo de teste
$stmt = $db->prepare('INSERT INTO devices (android_id, username, activation_date, expiry_date, last_online_check, app_version, device_info) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->bindValue(1, $android_id, SQLITE3_TEXT);
$stmt->bindValue(2, $username, SQLITE3_TEXT);
$stmt->bindValue(3, $activation_date, SQLITE3_TEXT);
$stmt->bindValue(4, $expiry_date, SQLITE3_TEXT);  
$stmt->bindValue(5, $last_online_check, SQLITE3_TEXT);
$stmt->bindValue(6, '1.0.0', SQLITE3_TEXT);
$stmt->bindValue(7, 'Test Device Background', SQLITE3_TEXT);
$stmt->execute();

echo "✅ Dispositivo criado no banco\n\n";

// Simular background check
echo "🔋 SIMULANDO BACKGROUND CHECK:\n";
echo "-" . str_repeat("-", 30) . "\n";

// Preparar dados POST
$postData = [
    'android_id' => $android_id,
    'username' => $username,
    'app_version' => '1.0.0'
];

// Simular chamada HTTP para background_check.php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/cloudfarm-air-license/background_check.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    echo "❌ Erro na requisição cURL\n";
} else {
    echo "📡 Resposta HTTP: $httpCode\n";
    echo "📋 Resposta JSON:\n";
    $responseData = json_decode($response, true);
    
    if ($responseData) {
        foreach ($responseData as $key => $value) {
            echo "   $key: $value\n";
        }
    } else {
        echo "   Raw: $response\n";
    }
}

echo "\n";

// Verificar estado final no banco
echo "📊 VERIFICANDO ESTADO FINAL:\n";
echo "-" . str_repeat("-", 30) . "\n";

$stmt = $db->prepare('SELECT * FROM devices WHERE android_id = ?');
$stmt->bindValue(1, $android_id, SQLITE3_TEXT);
$result = $stmt->execute();
$device = $result->fetchArray(SQLITE3_ASSOC);

if ($device) {
    echo "Último online (antes): $last_online_check\n";
    echo "Último online (depois): {$device['last_online_check']}\n";
    
    $before = new DateTime($last_online_check);
    $after = new DateTime($device['last_online_check']);
    $diff = $before->diff($after);
    
    if ($diff->days > 0 || $diff->h > 0 || $diff->i > 0) {
        echo "✅ SUCESSO: Background check atualizou timestamp!\n";
        echo "📈 Período offline resetado de 10 dias para 0 dias\n";
    } else {
        echo "❌ FALHA: Timestamp não foi atualizado\n";
    }
} else {
    echo "❌ Dispositivo não encontrado\n";
}

echo "\n";

// Verificar logs
echo "📝 LOGS DE ACESSO:\n";
echo "-" . str_repeat("-", 30) . "\n";

$stmt = $db->query("SELECT * FROM access_logs WHERE android_id = '$android_id' ORDER BY timestamp DESC LIMIT 3");
while ($log = $stmt->fetchArray(SQLITE3_ASSOC)) {
    echo "⏰ {$log['timestamp']} - {$log['action']} ({$log['ip_address']})\n";
}

// Limpar teste
$stmt = $db->prepare('DELETE FROM devices WHERE android_id = ?');
$stmt->bindValue(1, $android_id, SQLITE3_TEXT);
$stmt->execute();

$stmt = $db->prepare('DELETE FROM access_logs WHERE android_id = ?');
$stmt->bindValue(1, $android_id, SQLITE3_TEXT);
$stmt->execute();

echo "\n🧹 Dados de teste removidos\n";

echo "\n🎯 CONCLUSÃO:\n";
echo "Se o background check funcionou corretamente:\n";
echo "- ✅ Timestamp foi atualizado\n";
echo "- ✅ Período offline foi resetado\n";
echo "- ✅ Piloto terá mais 15 dias de uso offline\n";
echo "- ✅ Funciona mesmo sem abrir o app!\n";
?> 