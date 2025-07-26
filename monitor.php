<?php
/**
 * CloudFarm Air License Monitor
 * Script para monitoramento em tempo real das licenças e atividades
 */

// Cores para o terminal
class Colors {
    const RESET = "\033[0m";
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const MAGENTA = "\033[35m";
    const CYAN = "\033[36m";
    const WHITE = "\033[37m";
    const BOLD = "\033[1m";
}

class LicenseMonitor {
    private $db;
    private $lastLogId = 0;
    
    public function __construct() {
        try {
            $this->db = new SQLite3('/var/www/html/cloudfarm-air-license/database.sqlite');
            
            // Pegar o último ID dos logs para monitorar apenas novos
            $result = $this->db->query('SELECT MAX(id) as max_id FROM access_logs');
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $this->lastLogId = $row['max_id'] ?? 0;
            
        } catch (Exception $e) {
            die(Colors::RED . "❌ Erro ao conectar banco: " . $e->getMessage() . Colors::RESET . "\n");
        }
    }
    
    public function clearScreen() {
        system('clear');
    }
    
    public function showHeader() {
        echo Colors::BOLD . Colors::CYAN . "
╔════════════════════════════════════════════════════════════════╗
║                CloudFarm Air License Monitor                   ║
║                     Monitoramento em Tempo Real                ║
╚════════════════════════════════════════════════════════════════╝
" . Colors::RESET . "\n";
        
        echo Colors::YELLOW . "📍 Localização: /var/www/html/cloudfarm-air-license/" . Colors::RESET . "\n";
        echo Colors::YELLOW . "⏰ Iniciado em: " . date('d/m/Y H:i:s') . Colors::RESET . "\n";
        echo Colors::YELLOW . "🔄 Atualizando a cada 3 segundos" . Colors::RESET . "\n\n";
    }
    
    public function getStats() {
        // Dispositivos ativos
        $result = $this->db->query('SELECT COUNT(*) as total FROM devices WHERE expiry_date > datetime("now")');
        $active = $result->fetchArray(SQLITE3_ASSOC)['total'];
        
        // Dispositivos expirados
        $result = $this->db->query('SELECT COUNT(*) as total FROM devices WHERE expiry_date <= datetime("now")');
        $expired = $result->fetchArray(SQLITE3_ASSOC)['total'];
        
        // Dispositivos offline há mais de 7 dias
        $result = $this->db->query('SELECT COUNT(*) as total FROM devices WHERE last_online_check < datetime("now", "-7 days") AND expiry_date > datetime("now")');
        $offline = $result->fetchArray(SQLITE3_ASSOC)['total'];
        
        // Total de logs hoje
        $result = $this->db->query('SELECT COUNT(*) as total FROM access_logs WHERE date(timestamp) = date("now")');
        $logsToday = $result->fetchArray(SQLITE3_ASSOC)['total'];
        
        return [
            'active' => $active,
            'expired' => $expired,
            'offline' => $offline,
            'logs_today' => $logsToday
        ];
    }
    
    public function showStats($stats) {
        echo Colors::BOLD . "📊 ESTATÍSTICAS GERAIS" . Colors::RESET . "\n";
        echo "┌─────────────────────────────────────────────────────────────────┐\n";
        
        $activeColor = $stats['active'] > 0 ? Colors::GREEN : Colors::YELLOW;
        $expiredColor = $stats['expired'] > 0 ? Colors::RED : Colors::GREEN;
        $offlineColor = $stats['offline'] > 0 ? Colors::YELLOW : Colors::GREEN;
        
        echo "│ " . Colors::GREEN . "✅ Dispositivos Ativos: " . $activeColor . sprintf("%3d", $stats['active']) . Colors::RESET . " │";
        echo " " . Colors::RED . "❌ Expirados: " . $expiredColor . sprintf("%3d", $stats['expired']) . Colors::RESET . " │";
        echo " " . Colors::YELLOW . "📴 Offline: " . $offlineColor . sprintf("%3d", $stats['offline']) . Colors::RESET . " │\n";
        
        echo "│ " . Colors::BLUE . "📈 Atividades Hoje: " . Colors::WHITE . sprintf("%3d", $stats['logs_today']) . Colors::RESET . "                                    │\n";
        echo "└─────────────────────────────────────────────────────────────────┘\n\n";
    }
    
    public function getRecentDevices() {
        $query = 'SELECT android_id, username, activation_date, expiry_date, last_online_check, 
                         CASE 
                             WHEN expiry_date <= datetime("now") THEN "EXPIRADO"
                             WHEN last_online_check < datetime("now", "-7 days") THEN "OFFLINE"
                             ELSE "ATIVO"
                         END as status
                  FROM devices 
                  ORDER BY last_online_check DESC 
                  LIMIT 5';
        
        $result = $this->db->query($query);
        $devices = [];
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $devices[] = $row;
        }
        
        return $devices;
    }
    
    public function showRecentDevices($devices) {
        echo Colors::BOLD . "📱 ÚLTIMOS DISPOSITIVOS ATIVOS" . Colors::RESET . "\n";
        echo "┌─────────────────────────────────────────────────────────────────┐\n";
        
        if (empty($devices)) {
            echo "│ " . Colors::YELLOW . "Nenhum dispositivo registrado ainda" . Colors::RESET . "                          │\n";
        } else {
            foreach ($devices as $device) {
                $statusColor = Colors::GREEN;
                $statusIcon = "✅";
                
                if ($device['status'] == 'EXPIRADO') {
                    $statusColor = Colors::RED;
                    $statusIcon = "❌";
                } elseif ($device['status'] == 'OFFLINE') {
                    $statusColor = Colors::YELLOW;
                    $statusIcon = "📴";
                }
                
                $androidId = substr($device['android_id'], 0, 12) . "...";
                $username = substr($device['username'], 0, 20);
                $lastOnline = date('d/m H:i', strtotime($device['last_online_check']));
                
                echo "│ " . $statusColor . $statusIcon . " " . sprintf("%-15s", $androidId) . 
                     " │ " . sprintf("%-20s", $username) . 
                     " │ " . $lastOnline . Colors::RESET . " │\n";
            }
        }
        
        echo "└─────────────────────────────────────────────────────────────────┘\n\n";
    }
    
    public function getNewLogs() {
        $query = 'SELECT * FROM access_logs 
                  WHERE id > ? 
                  ORDER BY timestamp DESC';
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(1, $this->lastLogId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $newLogs = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $newLogs[] = $row;
            $this->lastLogId = max($this->lastLogId, $row['id']);
        }
        
        return array_reverse($newLogs); // Mostrar mais antigos primeiro
    }
    
    public function showNewLogs($logs) {
        if (!empty($logs)) {
            echo Colors::BOLD . "🔥 ATIVIDADES EM TEMPO REAL" . Colors::RESET . "\n";
            
            foreach ($logs as $log) {
                $time = date('H:i:s', strtotime($log['timestamp']));
                $androidId = substr($log['android_id'], 0, 12) . "...";
                $username = substr($log['username'], 0, 20);
                
                $actionColor = Colors::BLUE;
                $actionIcon = "🔍";
                $actionText = $log['action'];
                
                switch ($log['action']) {
                    case 'first_activation':
                        $actionColor = Colors::GREEN;
                        $actionIcon = "🎉";
                        $actionText = "NOVA ATIVAÇÃO";
                        break;
                    case 'check_existing':
                        $actionColor = Colors::YELLOW;
                        $actionIcon = "🔄";
                        $actionText = "CHECK EXISTENTE";
                        break;
                    case 'license_check':
                        $actionColor = Colors::BLUE;
                        $actionIcon = "✅";
                        $actionText = "VERIFICAÇÃO";
                        break;
                }
                
                echo $actionColor . "[$time] " . $actionIcon . " " . $actionText . 
                     " │ " . $androidId . " │ " . $username . 
                     " │ IP: " . $log['ip_address'] . Colors::RESET . "\n";
            }
            echo "\n";
        }
    }
    
    public function showLastActivity() {
        $result = $this->db->query('SELECT timestamp FROM access_logs ORDER BY timestamp DESC LIMIT 1');
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($row) {
            $lastActivity = $row['timestamp'];
            $diff = time() - strtotime($lastActivity);
            
            if ($diff < 60) {
                echo Colors::GREEN . "🟢 Última atividade: há " . $diff . " segundos" . Colors::RESET . "\n";
            } elseif ($diff < 3600) {
                echo Colors::YELLOW . "🟡 Última atividade: há " . round($diff/60) . " minutos" . Colors::RESET . "\n";
            } else {
                echo Colors::RED . "🔴 Última atividade: há " . round($diff/3600) . " horas" . Colors::RESET . "\n";
            }
        } else {
            echo Colors::YELLOW . "🟡 Nenhuma atividade registrada ainda" . Colors::RESET . "\n";
        }
        
        echo Colors::RESET . "🕐 Atualizado em: " . date('d/m/Y H:i:s') . "\n";
        echo Colors::MAGENTA . "Pressione Ctrl+C para sair" . Colors::RESET . "\n\n";
    }
    
    public function monitor() {
        while (true) {
            $this->clearScreen();
            $this->showHeader();
            
            // Estatísticas
            $stats = $this->getStats();
            $this->showStats($stats);
            
            // Dispositivos recentes
            $devices = $this->getRecentDevices();
            $this->showRecentDevices($devices);
            
            // Novos logs
            $newLogs = $this->getNewLogs();
            $this->showNewLogs($newLogs);
            
            // Última atividade
            $this->showLastActivity();
            
            // Aguardar 3 segundos
            sleep(3);
        }
    }
}

// Verificar se está sendo executado via CLI
if (php_sapi_name() !== 'cli') {
    die("Este script deve ser executado via linha de comando (CLI)\n");
}

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Capturar Ctrl+C para sair graciosamente
pcntl_signal(SIGINT, function() {
    echo Colors::YELLOW . "\n\n🛑 Monitor interrompido pelo usuário" . Colors::RESET . "\n";
    echo Colors::GREEN . "✅ CloudFarm Air License Monitor finalizado" . Colors::RESET . "\n\n";
    exit(0);
});

// Iniciar monitor
echo Colors::GREEN . "🚀 Iniciando CloudFarm Air License Monitor..." . Colors::RESET . "\n\n";
sleep(1);

$monitor = new LicenseMonitor();
$monitor->monitor();
?> 