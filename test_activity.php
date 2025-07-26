<?php
/**
 * Script para testar o monitor simulando atividades
 */

echo "🧪 Simulador de Atividades CloudFarm Air License\n\n";

$devices = [
    ['android_id' => 'samsung_galaxy_s21_001', 'username' => 'joao@fazenda.com', 'device_info' => 'Samsung Galaxy S21'],
    ['android_id' => 'xiaomi_redmi_note_002', 'username' => 'maria@agro.com', 'device_info' => 'Xiaomi Redmi Note 12'],
    ['android_id' => 'iphone_12_pro_003', 'username' => 'carlos@rural.com', 'device_info' => 'iPhone 12 Pro'],
    ['android_id' => 'moto_g60_004', 'username' => 'ana@campo.com', 'device_info' => 'Motorola Moto G60'],
    ['android_id' => 'lg_k62_005', 'username' => 'pedro@sitio.com', 'device_info' => 'LG K62']
];

$baseUrl = 'http://107.189.20.223/cloudfarm-air-license';

echo "Executando testes...\n\n";

foreach ($devices as $i => $device) {
    echo "[$i] Testando dispositivo: {$device['username']} ({$device['device_info']})\n";
    
    // Ativação
    $postFields = http_build_query([
        'android_id' => $device['android_id'],
        'username' => $device['username'],
        'app_version' => '1.0.' . ($i + 1),
        'device_info' => $device['device_info']
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/activate.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $data = json_decode($response, true);
        echo "   ✅ Ativação: " . $data['status'] . " - " . $data['message'] . "\n";
    } else {
        echo "   ❌ Erro na ativação (HTTP $httpCode)\n";
    }
    
    // Aguardar um pouco antes da próxima
    sleep(1);
    
    // Verificação de licença
    $postFields = http_build_query([
        'android_id' => $device['android_id'],
        'username' => $device['username']
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/check.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $data = json_decode($response, true);
        echo "   ✅ Verificação: " . $data['status'] . " - " . $data['days_remaining'] . " dias restantes\n";
    } else {
        echo "   ❌ Erro na verificação (HTTP $httpCode)\n";
    }
    
    echo "\n";
    sleep(2); // Aguardar entre dispositivos
}

echo "🎉 Testes concluídos! Agora você pode ver a atividade no monitor.\n";
echo "📊 Execute: php monitor.php\n\n";
?> 