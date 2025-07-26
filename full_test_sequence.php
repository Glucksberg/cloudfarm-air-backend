<?php
/**
 * TESTE COMPLETO: Login + Background Check
 * 
 * Este script testa toda a sequência:
 * 1. Ativar usuário
 * 2. Fazer login (registrar dispositivo)
 * 3. Testar background check
 * 4. Verificar resultados
 */

echo "🔬 TESTE COMPLETO: LOGIN + BACKGROUND CHECK\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Conectar ao banco
try {
    $db = new SQLite3('/var/www/html/cloudfarm-air-license/database.sqlite');
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit;
}

// Dados de teste
$test_android_id = 'test_device_' . date('His');
$test_username = 'user1';
$test_password = 'KLG747';

echo "📋 DADOS DE TESTE:\n";
echo "-" . str_repeat("-", 30) . "\n";
echo "Android ID: $test_android_id\n";
echo "Username: $test_username\n";
echo "Password: $test_password\n\n";

// PASSO 1: Ativar usuário
echo "1️⃣ ATIVANDO USUÁRIO...\n";
$stmt = $db->prepare('UPDATE users SET active = 1 WHERE username = ?');
$stmt->bindValue(1, $test_username, SQLITE3_TEXT);
$stmt->execute();

$stmt = $db->prepare('SELECT active FROM users WHERE username = ?');
$stmt->bindValue(1, $test_username, SQLITE3_TEXT);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

if ($user && $user['active']) {
    echo "   ✅ Usuário $test_username ativado\n\n";
} else {
    echo "   ❌ Falha ao ativar usuário\n";
    exit;
}

// PASSO 2: Fazer login (registrar dispositivo)
echo "2️⃣ FAZENDO LOGIN (REGISTRAR DISPOSITIVO)...\n";

$login_data = [
    'username' => $test_username,
    'password' => $test_password,
    'android_id' => $test_android_id,
    'app_version' => '1.0.0',
    'device_info' => 'Test Device for Background Check'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/cloudfarm-air-license/login.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($login_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$login_response = curl_exec($ch);
$login_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$login_curl_error = curl_error($ch);
curl_close($ch);

echo "   📡 HTTP Code: $login_http_code\n";
echo "   🔍 Raw Response: $login_response\n";

if ($login_response) {
    $login_result = json_decode($login_response, true);
    if ($login_result && $login_result['status'] === 'success') {
        echo "   ✅ Login realizado com sucesso\n";
        echo "   📅 Expiry: {$login_result['license_info']['expiry_date']}\n";
        echo "   ⏰ Offline Grace: {$login_result['license_info']['offline_grace_days']} dias\n\n";
    } else {
        echo "   ❌ Falha no login\n";
        echo "   📋 Status: " . ($login_result['status'] ?? 'N/A') . "\n";
        echo "   💬 Message: " . ($login_result['message'] ?? 'N/A') . "\n";
        echo "   🔍 JSON Error: " . json_last_error_msg() . "\n";
        exit;
    }
} else {
    echo "   ❌ Erro na requisição de login\n";
    echo "   🔍 cURL Error: $login_curl_error\n";
    exit;
}

// PASSO 2.5: Verificar se dispositivo foi registrado no login
echo "2️⃣.5 VERIFICANDO SE DISPOSITIVO FOI REGISTRADO...\n";

$stmt = $db->prepare('SELECT * FROM devices WHERE android_id = ?');
$stmt->bindValue(1, $test_android_id, SQLITE3_TEXT);
$result = $stmt->execute();
$device_check = $result->fetchArray(SQLITE3_ASSOC);

if ($device_check) {
    echo "   ✅ Dispositivo encontrado no banco\n";
    echo "   📱 Android ID: {$device_check['android_id']}\n";
    echo "   👤 Username: {$device_check['username']}\n";
    echo "   📅 Expiry: {$device_check['expiry_date']}\n\n";
} else {
    echo "   ❌ PROBLEMA: Dispositivo NÃO foi registrado no login!\n";
    echo "   🔍 Verificando todos os dispositivos no banco:\n";
    
    $all_devices = $db->query('SELECT android_id, username FROM devices ORDER BY activation_date DESC LIMIT 5');
    while ($dev = $all_devices->fetchArray(SQLITE3_ASSOC)) {
        echo "       📱 {$dev['android_id']} → {$dev['username']}\n";
    }
    echo "\n   ⚠️ Continuando teste mesmo assim...\n\n";
}

// PASSO 3: Simular dispositivo offline por alguns dias
echo "3️⃣ SIMULANDO DISPOSITIVO OFFLINE (10 DIAS)...\n";

$old_timestamp = date('Y-m-d H:i:s', strtotime('-10 days'));
$stmt = $db->prepare('UPDATE devices SET last_online_check = ? WHERE android_id = ?');
$stmt->bindValue(1, $old_timestamp, SQLITE3_TEXT);
$stmt->bindValue(2, $test_android_id, SQLITE3_TEXT);
$result = $stmt->execute();
$affected_rows = $db->changes();

echo "   ⏰ Último online alterado para: $old_timestamp\n";
echo "   📊 Linhas afetadas: $affected_rows\n";
echo "   📊 Status: OFFLINE há 10 dias\n\n";

// PASSO 4: Testar background check
echo "4️⃣ TESTANDO BACKGROUND CHECK...\n";

$bg_data = [
    'android_id' => $test_android_id,
    'username' => $test_username,
    'app_version' => '1.0.0'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/cloudfarm-air-license/background_check.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($bg_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$bg_response = curl_exec($ch);
$bg_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   📡 HTTP Code: $bg_http_code\n";
if ($bg_response) {
    $bg_result = json_decode($bg_response, true);
    if ($bg_result) {
        echo "   📋 Status: {$bg_result['status']}\n";
        echo "   💬 Message: {$bg_result['message']}\n";
        echo "   🔋 Background Check: " . ($bg_result['background_check'] ? 'SIM' : 'NÃO') . "\n";
        
        if (isset($bg_result['days_offline'])) {
            echo "   📊 Days Offline: {$bg_result['days_offline']}\n";
        }
        if (isset($bg_result['offline_grace_days'])) {
            echo "   ⏰ Grace Days: {$bg_result['offline_grace_days']}\n";
        }
    } else {
        echo "   ❌ Resposta inválida: $bg_response\n";
    }
} else {
    echo "   ❌ Erro na requisição de background check\n";
}

echo "\n";

// PASSO 5: Verificar estado final no banco
echo "5️⃣ VERIFICANDO ESTADO FINAL...\n";

$stmt = $db->prepare('SELECT * FROM devices WHERE android_id = ?');
$stmt->bindValue(1, $test_android_id, SQLITE3_TEXT);
$result = $stmt->execute();
$device = $result->fetchArray(SQLITE3_ASSOC);

if ($device) {
    echo "   📅 Último online (antes): $old_timestamp\n";
    echo "   📅 Último online (depois): {$device['last_online_check']}\n";
    
    $before = new DateTime($old_timestamp);
    $after = new DateTime($device['last_online_check']);
    $diff = $before->diff($after);
    
    if ($diff->days > 0 || $diff->h > 0 || $diff->i > 0) {
        echo "   ✅ SUCESSO: Background check atualizou timestamp!\n";
        echo "   📈 Período offline resetado!\n";
    } else {
        echo "   ❌ FALHA: Timestamp não foi atualizado\n";
    }
} else {
    echo "   ❌ Dispositivo não encontrado\n";
}

echo "\n";

// PASSO 6: Verificar logs
echo "6️⃣ VERIFICANDO LOGS...\n";

$stmt = $db->query("SELECT * FROM access_logs WHERE android_id = '$test_android_id' ORDER BY timestamp DESC LIMIT 5");
$log_count = 0;
while ($log = $stmt->fetchArray(SQLITE3_ASSOC)) {
    echo "   📝 {$log['timestamp']} - {$log['action']} - {$log['username']}\n";
    $log_count++;
}

if ($log_count === 0) {
    echo "   ⚠️ Nenhum log encontrado\n";
}

echo "\n";

// LIMPEZA
echo "🧹 LIMPANDO DADOS DE TESTE...\n";

$stmt = $db->prepare('DELETE FROM devices WHERE android_id = ?');
$stmt->bindValue(1, $test_android_id, SQLITE3_TEXT);
$stmt->execute();

$stmt = $db->prepare('DELETE FROM access_logs WHERE android_id = ?');
$stmt->bindValue(1, $test_android_id, SQLITE3_TEXT);
$stmt->execute();

echo "   ✅ Dados de teste removidos\n\n";

// RESUMO
echo "🎯 RESUMO DO TESTE:\n";
echo "-" . str_repeat("-", 30) . "\n";
if (isset($bg_result) && $bg_result['status'] === 'extended') {
    echo "✅ TESTE PASSOU - Background check funcionando!\n";
    echo "✅ Período offline resetado automaticamente\n";
    echo "✅ Sistema pronto para uso em produção\n";
} else {
    echo "❌ TESTE FALHOU - Verificar implementação\n";
    echo "❌ Background check não funcionou como esperado\n";
}

echo "\n🚀 Sistema de 15 dias offline + background check implementado!\n";
?> 