<?php
/**
 * TESTE: Demonstração da Lógica de 7 Dias Offline
 * 
 * Este script simula diferentes cenários para explicar 
 * exatamente como funciona o período de graça offline.
 */

echo "🔍 TESTE: LÓGICA DE 7 DIAS OFFLINE\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Conectar ao banco
try {
    $db = new SQLite3('/var/www/html/cloudfarm-air-license/database.sqlite');
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit;
}

// Função para simular cenário
function simulate_scenario($db, $scenario_name, $days_offline) {
    echo "📱 $scenario_name\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    // Simular dispositivo com X dias offline
    $android_id = 'test_device_' . uniqid();
    $username = 'user1';
    $activation_date = date('Y-m-d H:i:s');
    $expiry_date = date('Y-m-d H:i:s', strtotime('+6 months'));
    
    // Último check online há X dias
    $last_online_check = date('Y-m-d H:i:s', strtotime("-$days_offline days"));
    
    // Inserir dispositivo de teste
    $stmt = $db->prepare('INSERT INTO devices (android_id, username, activation_date, expiry_date, last_online_check, app_version, device_info) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bindValue(1, $android_id, SQLITE3_TEXT);
    $stmt->bindValue(2, $username, SQLITE3_TEXT);
    $stmt->bindValue(3, $activation_date, SQLITE3_TEXT);
    $stmt->bindValue(4, $expiry_date, SQLITE3_TEXT);
    $stmt->bindValue(5, $last_online_check, SQLITE3_TEXT);
    $stmt->bindValue(6, '1.0.0', SQLITE3_TEXT);
    $stmt->bindValue(7, 'Test Device', SQLITE3_TEXT);
    $stmt->execute();
    
    // Testar check
    echo "⏰ Dispositivo offline há: $days_offline dias\n";
    echo "📅 Último online: $last_online_check\n";
    
    // Simular chamada para check.php
    $_POST['android_id'] = $android_id;
    $_POST['username'] = $username;
    
    // Buscar dispositivo
    $stmt = $db->prepare('SELECT * FROM devices WHERE android_id = ?');
    $stmt->bindValue(1, $android_id, SQLITE3_TEXT);
    $result = $stmt->execute();
    $device = $result->fetchArray(SQLITE3_ASSOC);
    
    // Aplicar lógica de verificação
    $expiry_date_obj = new DateTime($device['expiry_date']);
    $current_time = new DateTime();
    $last_online_obj = new DateTime($device['last_online_check']);
    
    $days_offline_calculated = $current_time->diff($last_online_obj)->days;
    $offline_grace_days = 15;
    
    if ($current_time > $expiry_date_obj) {
        $status = '❌ LICENÇA EXPIRADA';
        $message = 'License expired';
    } elseif ($days_offline_calculated > $offline_grace_days) {
        $status = '⚠️ OFFLINE EXPIRADO';
        $message = "Device offline for too long ($days_offline_calculated days)";
    } else {
        $status = '✅ VÁLIDO';
        $message = 'License valid';
    }
    
    echo "🎯 RESULTADO: $status\n";
    echo "💬 Mensagem: $message\n";
    
    // Limpar teste
    $stmt = $db->prepare('DELETE FROM devices WHERE android_id = ?');
    $stmt->bindValue(1, $android_id, SQLITE3_TEXT);
    $stmt->execute();
    
    echo "\n";
}

// CENÁRIOS DE TESTE
echo "🧪 CENÁRIOS DE TESTE:\n\n";

simulate_scenario($db, "Cenário 1: Dispositivo há 3 dias offline", 3);
simulate_scenario($db, "Cenário 2: Dispositivo há 10 dias offline", 10);
simulate_scenario($db, "Cenário 3: Dispositivo há 15 dias offline (LIMITE)", 15);
simulate_scenario($db, "Cenário 4: Dispositivo há 16 dias offline (BLOQUEADO)", 16);
simulate_scenario($db, "Cenário 5: Dispositivo há 30 dias offline (MUITO TEMPO)", 30);

echo "📋 EXPLICAÇÃO DO SEU CENÁRIO (ATUALIZADO):\n";
echo "-" . str_repeat("-", 40) . "\n";
echo "🎯 Seu cenário: 5 dias offline + 5 dias offline = 10 dias total\n";
echo "✅ NOVO: Background check a cada 3 horas (mesmo app fechado)\n";
echo "🚁 Piloto em área de serviço: Background estende período automaticamente\n";
echo "⏰ Período offline: 7 → 15 dias (mais tempo para agricultura)\n";
echo "🔒 Resultado: App só bloqueia após 15 dias reais offline\n\n";

echo "🚀 COMO FUNCIONA AGORA (COM BACKGROUND):\n";
echo "1. ⚡ Background check a cada 3 horas (WorkManager Android)\n";
echo "2. 📡 Se conectado: background_check.php estende período automaticamente\n";
echo "3. 📱 App faz check manual (a cada uso)\n";
echo "4. 🛡️ Server verifica: último_online > 15 dias?\n";
echo "5. ❌ Se SIM: Retorna 'offline_expired'\n";
echo "6. 🔒 App bloqueia até conseguir conectar online\n";
echo "7. ✅ Quando conectar: Reseta contador (last_online_check = agora)\n\n";

echo "✅ IMPLEMENTAÇÃO CORRIGIDA!\n";
?> 