<?php
/**
 * CloudFarm Air License - Painel Administrativo
 */

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

try {
    $db = new SQLite3('/var/www/html/cloudfarm-air-license/database.sqlite');
} catch (Exception $e) {
    die("Erro ao conectar banco: " . $e->getMessage());
}

// Processar ações
$message = '';
$messageType = '';

if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'delete_device':
            $androidId = $_POST['android_id'] ?? '';
            if ($androidId) {
                $stmt = $db->prepare('DELETE FROM devices WHERE android_id = ?');
                $stmt->bindValue(1, $androidId, SQLITE3_TEXT);
                $result = $stmt->execute();
                
                $stmt = $db->prepare('DELETE FROM access_logs WHERE android_id = ?');
                $stmt->bindValue(1, $androidId, SQLITE3_TEXT);
                $stmt->execute();
                
                $message = "Dispositivo removido com sucesso!";
                $messageType = "success";
            }
            break;
            
        case 'extend_user_license':
            $userId = (int)($_POST['user_id'] ?? 0);
            $months = (int)($_POST['months'] ?? 12);
            if ($userId) {
                // Estender licença do usuário (não do dispositivo)
                $stmt = $db->prepare('UPDATE users SET license_duration_months = license_duration_months + ? WHERE id = ?');
                $stmt->bindValue(1, $months, SQLITE3_INTEGER);
                $stmt->bindValue(2, $userId, SQLITE3_INTEGER);
                $stmt->execute();
                
                $message = "Licença do usuário estendida por $months meses!";
                $messageType = "success";
            }
            break;
            
        case 'create_license':
            $username = $_POST['username'] ?? '';
            $androidId = $_POST['new_android_id'] ?? '';
            $months = (int)($_POST['license_months'] ?? 12);
            
            if ($username && $androidId) {
                $activationDate = date('Y-m-d H:i:s');
                $expiryDate = date('Y-m-d H:i:s', strtotime("+$months months"));
                
                $stmt = $db->prepare('INSERT INTO devices (android_id, username, activation_date, expiry_date, last_online_check, app_version, device_info) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->bindValue(1, $androidId, SQLITE3_TEXT);
                $stmt->bindValue(2, $username, SQLITE3_TEXT);
                $stmt->bindValue(3, $activationDate, SQLITE3_TEXT);
                $stmt->bindValue(4, $expiryDate, SQLITE3_TEXT);
                $stmt->bindValue(5, $activationDate, SQLITE3_TEXT);
                $stmt->bindValue(6, '1.0.0', SQLITE3_TEXT);
                $stmt->bindValue(7, 'Manual Admin', SQLITE3_TEXT);
                
                if ($stmt->execute()) {
                    $message = "Licença criada manualmente com sucesso!";
                    $messageType = "success";
                } else {
                    $message = "Erro ao criar licença!";
                    $messageType = "error";
                }
            }
            break;
            
        case 'create_user':
            $username = $_POST['new_username'] ?? '';
            $password = $_POST['new_password'] ?? '';
            $displayName = $_POST['display_name'] ?? '';
            $maxDevices = (int)($_POST['max_devices'] ?? 1);
            $licenseDuration = (int)($_POST['license_duration'] ?? 12);
            
            if ($username && $password && $displayName) {
                $stmt = $db->prepare('INSERT INTO users (username, password, display_name, max_devices, license_duration_months) VALUES (?, ?, ?, ?, ?)');
                $stmt->bindValue(1, $username, SQLITE3_TEXT);
                $stmt->bindValue(2, $password, SQLITE3_TEXT);
                $stmt->bindValue(3, $displayName, SQLITE3_TEXT);
                $stmt->bindValue(4, $maxDevices, SQLITE3_INTEGER);
                $stmt->bindValue(5, $licenseDuration, SQLITE3_INTEGER);
                
                if ($stmt->execute()) {
                    $message = "Usuário '$username' criado com sucesso!";
                    $messageType = "success";
                } else {
                    $message = "Erro ao criar usuário - Username pode já existir!";
                    $messageType = "error";
                }
            }
            break;
            
        case 'toggle_user':
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId) {
                $stmt = $db->prepare('UPDATE users SET active = NOT active WHERE id = ?');
                $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
                $stmt->execute();
                
                $message = "Status do usuário alterado!";
                $messageType = "success";
            }
            break;
            
        case 'delete_user':
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId) {
                $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
                $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
                $stmt->execute();
                
                $message = "Usuário removido com sucesso!";
                $messageType = "success";
            }
            break;
    }
}

// Obter estatísticas de usuários
$result = $db->query('SELECT COUNT(*) as total FROM users WHERE active = 1');
$activeUsers = $result->fetchArray(SQLITE3_ASSOC)['total'];

$result = $db->query('SELECT COUNT(*) as total FROM users WHERE active = 0');
$inactiveUsers = $result->fetchArray(SQLITE3_ASSOC)['total'];

// Usuários com licença expirada (criado há mais tempo que duração da licença)
$result = $db->query('SELECT COUNT(*) as total FROM users WHERE datetime(created_at, "+" || license_duration_months || " months") < datetime("now") AND active = 1');
$expiredUsers = $result->fetchArray(SQLITE3_ASSOC)['total'];

// Obter dispositivos
$devices = [];
$result = $db->query('SELECT * FROM devices ORDER BY last_online_check DESC');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $devices[] = $row;
}

// Obter logs recentes
$logs = [];
$result = $db->query('SELECT * FROM access_logs ORDER BY timestamp DESC LIMIT 10');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $logs[] = $row;
}

// Obter usuários (se tabela existir)
$users = [];
try {
    $result = $db->query('SELECT * FROM users ORDER BY created_at DESC');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }
} catch (Exception $e) {
    // Tabela users ainda não existe
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CloudFarm Air License - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; color: #3498db; }
        .section { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .section h3 { color: #2c3e50; margin-bottom: 15px; border-bottom: 2px solid #3498db; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 2px; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-primary { background: #3498db; color: white; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .status-active { color: #27ae60; font-weight: bold; }
        .status-expired { color: #e74c3c; font-weight: bold; }
        .status-offline { color: #f39c12; font-weight: bold; }
        .message { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .credentials { background: #e8f4f8; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .credentials h4 { color: #2c3e50; margin-bottom: 10px; }
        .cred-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
        .cred-item { background: white; padding: 10px; border-radius: 4px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 CloudFarm Air License - Painel Administrativo</h1>
            <p>Gerenciamento de Licenças e Dispositivos</p>
            <p><strong>Servidor:</strong> <?= date('d/m/Y H:i:s') ?></p>
        </div>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($users)): ?>
            <div class="credentials">
                <h4>📱 Credenciais Disponíveis no App (Sistema Antigo)</h4>
                <div class="cred-list">
                    <div class="cred-item"><strong>user1</strong><br>Senha: KLG747</div>
                    <div class="cred-item"><strong>user2</strong><br>Senha: KLG787</div>
                    <div class="cred-item"><strong>user3</strong><br>Senha: KLG788</div>
                </div>
                <p style="text-align: center; margin-top: 10px; color: #e74c3c;">
                    ⚠️ <strong>Sistema de autenticação não migrado!</strong><br>
                    <a href="create_users_table.php" target="_blank" style="color: #3498db;">Clique aqui para migrar</a>
                </p>
            </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= $activeUsers ?></div>
                <div>Usuários Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $expiredUsers ?></div>
                <div>Licenças Expiradas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $inactiveUsers ?></div>
                <div>Usuários Inativos</div>
            </div>
        </div>

        <?php if (!empty($users)): ?>
            <div class="section">
                <h3>👥 Gerenciar Usuários</h3>
                
                <div style="margin-bottom: 20px;">
                    <h4>➕ Criar Novo Usuário</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_user">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Username:</label>
                                <input type="text" name="new_username" placeholder="Ex: user4" required>
                            </div>
                            <div class="form-group">
                                <label>Senha:</label>
                                <input type="text" name="new_password" placeholder="Ex: KLG789" required>
                            </div>
                            <div class="form-group">
                                <label>Nome de Exibição:</label>
                                <input type="text" name="display_name" placeholder="Ex: Usuário 4" required>
                            </div>
                            <div class="form-group">
                                <label>Máx. Dispositivos:</label>
                                <select name="max_devices">
                                    <option value="1" selected>1 dispositivo</option>
                                    <option value="2">2 dispositivos</option>
                                    <option value="3">3 dispositivos</option>
                                    <option value="5">5 dispositivos</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Duração da Licença:</label>
                                <select name="license_duration">
                                    <option value="1">1 mês</option>
                                    <option value="3">3 meses</option>
                                    <option value="6">6 meses</option>
                                    <option value="12" selected>12 meses</option>
                                    <option value="24">24 meses</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success">Criar Usuário</button>
                    </form>
                </div>

                <h4>📋 Usuários Existentes</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Senha</th>
                            <th>Nome</th>
                            <th>Status</th>
                            <th>Máx. Dispositivos</th>
                            <th>Duração Licença</th>
                            <th>Criado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr style="<?= $user['active'] ? '' : 'opacity: 0.6;' ?>">
                                <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                                <td><code><?= htmlspecialchars($user['password']) ?></code></td>
                                <td><?= htmlspecialchars($user['display_name']) ?></td>
                                <td>
                                    <?= $user['active'] ? 
                                        '<span class="status-active">ATIVO</span>' : 
                                        '<span class="status-expired">INATIVO</span>' 
                                    ?>
                                </td>
                                <td><?= $user['max_devices'] ?></td>
                                <td><?= $user['license_duration_months'] ?> meses</td>
                                <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="extend_user_license">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <select name="months" style="width: 80px; padding: 4px;">
                                            <option value="1">1m</option>
                                            <option value="3">3m</option>
                                            <option value="6">6m</option>
                                            <option value="12" selected>12m</option>
                                        </select>
                                        <button type="submit" class="btn btn-success">Estender</button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_user">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn <?= $user['active'] ? 'btn-warning' : 'btn-success' ?>">
                                            <?= $user['active'] ? 'Desativar' : 'Ativar' ?>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja remover este usuário?')">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-danger">Remover</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>





        <div class="section">
            <h3>📋 Últimas Atividades</h3>
            <?php if (empty($logs)): ?>
                <p>Nenhuma atividade registrada ainda.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Android ID</th>
                            <th>Username</th>
                            <th>Ação</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i:s', strtotime($log['timestamp'])) ?></td>
                                <td><?= substr($log['android_id'], 0, 16) ?>...</td>
                                <td><?= htmlspecialchars($log['username']) ?></td>
                                <td>
                                    <?php
                                    switch ($log['action']) {
                                        case 'first_activation':
                                            echo '🎉 Nova Ativação';
                                            break;
                                        case 'check_existing':
                                            echo '🔄 Check Existente';
                                            break;
                                        case 'license_check':
                                            echo '✅ Verificação';
                                            break;
                                        default:
                                            echo $log['action'];
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($log['ip_address']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 