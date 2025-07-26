# 📋 CloudFarm Air License System - Documentação Técnica

## 🎯 **VISÃO GERAL**

Sistema de licenciamento baseado em Android ID para controle de acesso ao aplicativo CloudFarm Air. Implementa ativação, verificação e monitoramento de licenças com período de 1 ano por dispositivo.

**Status:** ✅ **Sistema 100% funcional e testado**  
**Data de Implementação:** 25/07/2025  
**Tecnologias:** PHP 8.3, SQLite3, Apache  

---

## 🗂️ **ÁRVORE DE ARQUIVOS**

```
/var/www/html/cloudfarm-air-license/
├── database.sqlite          # Banco SQLite (20KB) - CRIADO ✅
├── create_tables.php        # Script de inicialização (1.267 bytes)
├── activate.php             # Endpoint de ativação (4.681 bytes)
├── check.php                # Endpoint de verificação (2.602 bytes)
├── status.php               # Endpoint de estatísticas (1.284 bytes)
├── .htaccess                # Proteção de segurança (337 bytes)
└── readme.md                # Documentação original (323 linhas)
```

---

## 🔗 **ENDPOINTS DA API**

### 1. **POST /cloudfarm-air-license/activate.php** - Ativação de Dispositivos

**Descrição:** Registra um novo dispositivo ou verifica um dispositivo já registrado.

**Parâmetros POST:**
```json
{
  "android_id": "abc123xyz789",      // OBRIGATÓRIO
  "username": "usuario@email.com",   // OBRIGATÓRIO
  "app_version": "1.0.0",           // OPCIONAL
  "device_info": "Samsung Galaxy S21" // OPCIONAL
}
```

**Respostas Possíveis:**

**Primeira ativação (status: "activated"):**
```json
{
  "status": "activated",
  "message": "Device activated successfully",
  "activation_date": "2025-07-25 19:00:00",
  "expiry_date": "2026-07-25 19:00:00",
  "days_remaining": 365,
  "username": "usuario@email.com",
  "last_online_check": "2025-07-25 19:00:00",
  "offline_grace_days": 7
}
```

**Dispositivo existente válido (status: "valid"):**
```json
{
  "status": "valid",
  "message": "Device already activated",
  "expiry_date": "2026-07-25 19:00:00",
  "days_remaining": 200,
  "username": "usuario@email.com",
  "last_online_check": "2025-07-25 19:00:00",
  "offline_grace_days": 7
}
```

**Licença expirada (status: "expired"):**
```json
{
  "status": "expired",
  "message": "License expired",
  "expiry_date": "2025-07-24 19:00:00"
}
```

### 2. **POST /cloudfarm-air-license/check.php** - Verificação de Licença

**Descrição:** Verifica se um dispositivo tem licença válida e atualiza o último check online.

**Parâmetros POST:**
```json
{
  "android_id": "abc123xyz789",      // OBRIGATÓRIO
  "username": "usuario@email.com"    // OPCIONAL
}
```

**Respostas Possíveis:**

**Licença válida (status: "valid"):**
```json
{
  "status": "valid",
  "message": "License valid",
  "expiry_date": "2026-07-25 19:00:00",
  "days_remaining": 200,
  "username": "usuario@email.com",
  "last_online_check": "2025-07-25 19:00:00",
  "offline_grace_days": 7
}
```

**Licença expirada (status: "expired"):**
```json
{
  "status": "expired",
  "message": "License expired",
  "expiry_date": "2025-07-24 19:00:00"
}
```

**Dispositivo não registrado (status: "not_registered"):**
```json
{
  "status": "not_registered",
  "message": "Device not registered"
}
```

### 3. **GET /cloudfarm-air-license/status.php** - Estatísticas do Sistema

**Descrição:** Retorna estatísticas gerais do sistema de licenciamento.

**Resposta:**
```json
{
  "status": "success",
  "server_time": "2025-07-25 19:00:03",
  "active_devices": 0,
  "expired_devices": 0,
  "offline_devices": 0,
  "offline_grace_period": "7 days"
}
```

---

## 🗄️ **ESTRUTURA DO BANCO DE DADOS**

### Tabela: `devices`
```sql
CREATE TABLE devices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    android_id TEXT UNIQUE NOT NULL,        -- ID único do dispositivo Android
    username TEXT NOT NULL,                 -- Email/usuário do sistema
    activation_date DATETIME NOT NULL,      -- Data da primeira ativação
    expiry_date DATETIME NOT NULL,          -- Data de expiração (1 ano)
    last_online_check DATETIME NOT NULL,    -- Último check online
    app_version TEXT,                       -- Versão do app
    device_info TEXT,                       -- Informações do dispositivo
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### Tabela: `access_logs`
```sql
CREATE TABLE access_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    android_id TEXT NOT NULL,               -- ID do dispositivo
    username TEXT NOT NULL,                 -- Usuário
    action TEXT NOT NULL,                   -- Ação realizada
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address TEXT                         -- IP da requisição
);
```

**Tipos de ações registradas nos logs:**
- `first_activation` - Primeira ativação do dispositivo
- `check_existing` - Verificação de dispositivo existente
- `license_check` - Verificação de licença

---

## 🔧 **INTEGRAÇÃO NO PROJETO PRINCIPAL**

### **Configuração Base:**

**URL Base do Sistema:**
```
http://localhost/cloudfarm-air-license/
# ou
https://seu-dominio.com/cloudfarm-air-license/
```

**Headers Necessários:**
```http
Content-Type: application/x-www-form-urlencoded
```

**Métodos HTTP:**
- `activate.php` e `check.php`: **POST**
- `status.php`: **GET**

### **Exemplo de Implementação (JavaScript/TypeScript):**

```javascript
class CloudFarmLicenseAPI {
    constructor(baseUrl) {
        this.baseUrl = baseUrl;
    }

    async activateDevice(androidId, username, appVersion = '', deviceInfo = '') {
        const response = await fetch(`${this.baseUrl}/activate.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                android_id: androidId,
                username: username,
                app_version: appVersion,
                device_info: deviceInfo
            })
        });
        return await response.json();
    }

    async checkLicense(androidId, username = '') {
        const response = await fetch(`${this.baseUrl}/check.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                android_id: androidId,
                username: username
            })
        });
        return await response.json();
    }

    async getStatus() {
        const response = await fetch(`${this.baseUrl}/status.php`);
        return await response.json();
    }
}
```

### **Exemplo de Uso no Android (Java/Kotlin):**

```java
public class LicenseManager {
    private static final String BASE_URL = "https://seu-dominio.com/cloudfarm-air-license/";
    
    public void activateDevice(String androidId, String username, 
                              String appVersion, String deviceInfo) {
        OkHttpClient client = new OkHttpClient();
        
        RequestBody formBody = new FormBody.Builder()
            .add("android_id", androidId)
            .add("username", username)
            .add("app_version", appVersion)
            .add("device_info", deviceInfo)
            .build();
            
        Request request = new Request.Builder()
            .url(BASE_URL + "activate.php")
            .post(formBody)
            .build();
            
        // Executar requisição...
    }
}
```

---

## 🛡️ **SISTEMA DE SEGURANÇA**

### **Medidas Implementadas:**
- ✅ **CORS** configurado para todas as origens
- ✅ **Prepared Statements** (proteção contra SQL injection)
- ✅ **Proteção do banco** via .htaccess
- ✅ **Logs de auditoria** de todas as operações
- ✅ **Validação de dados** de entrada
- ✅ **Controle de métodos HTTP**

### **Arquivo .htaccess:**
```apache
# Proteger arquivo do banco
<Files "database.sqlite">
    Order allow,deny
    Deny from all
</Files>

# Permitir apenas POST nos scripts principais
<Files "activate.php">
    <RequireAll>
        Require method POST
    </RequireAll>
</Files>

<Files "check.php">
    <RequireAll>
        Require method POST
    </RequireAll>
</Files>
```

---

## 📊 **LÓGICA DE NEGÓCIO**

### **Estados de Licença:**
- `activated` - Primeira ativação realizada com sucesso
- `valid` - Licença válida e ativa
- `expired` - Licença expirada
- `not_registered` - Dispositivo não registrado no sistema
- `error` - Erro no sistema

### **Regras Aplicadas:**
- **Período de licença:** 1 ano a partir da primeira ativação
- **Período de graça offline:** 7 dias sem conexão
- **Identificação única:** Android ID do dispositivo
- **Auto-renovação do último check:** Atualizado a cada verificação
- **Logs completos:** Todas as operações são registradas

### **Fluxo de Funcionamento:**

1. **Primeira Instalação:**
   - App captura Android ID
   - Chama `activate.php`
   - Sistema registra dispositivo com licença de 1 ano
   - Retorna dados de ativação

2. **Verificações Subsequentes:**
   - App chama `activate.php` ou `check.php`
   - Sistema atualiza `last_online_check`
   - Verifica se licença ainda é válida
   - Retorna status atual

3. **Controle Offline:**
   - App pode funcionar por até 7 dias sem conexão
   - Após 7 dias offline, deve verificar licença online
   - Se licença expirou, bloqueia acesso

---

## 🚀 **COMANDOS DE MANUTENÇÃO**

### **Inicialização do Sistema:**
```bash
cd /var/www/html/cloudfarm-air-license/
php create_tables.php
```

### **Verificar Status:**
```bash
php status.php
```

### **Backup do Banco:**
```bash
cp database.sqlite database_backup_$(date +%Y%m%d_%H%M%S).sqlite
```

### **Consultas Úteis no SQLite:**
```sql
-- Ver todos os dispositivos ativos
SELECT * FROM devices WHERE expiry_date > datetime('now');

-- Ver dispositivos offline há mais de 7 dias
SELECT * FROM devices 
WHERE last_online_check < datetime('now', '-7 days') 
AND expiry_date > datetime('now');

-- Ver logs recentes
SELECT * FROM access_logs 
ORDER BY timestamp DESC 
LIMIT 10;
```

---

## 🔍 **TROUBLESHOOTING**

### **Problemas Comuns:**

**1. Erro "Database not found":**
- Executar `php create_tables.php`
- Verificar permissões da pasta

**2. CORS Error:**
- Headers já estão configurados nos arquivos PHP
- Verificar se o servidor permite CORS

**3. Banco não criado:**
- Verificar se PHP tem extensão SQLite3 habilitada
- Verificar permissões de escrita na pasta

**4. Verificar extensões PHP:**
```bash
php -m | grep sqlite
```

---

## 📈 **MONITORAMENTO**

### **Métricas Importantes:**
- Total de dispositivos ativos
- Dispositivos próximos ao vencimento
- Dispositivos offline há mais de 7 dias
- Logs de erro/tentativas de acesso

### **Alertas Recomendados:**
- Dispositivos com licença expirando em 30 dias
- Múltiplas tentativas de ativação do mesmo dispositivo
- Picos de ativação (possível distribuição não autorizada)

---

## 📝 **CHANGELOG**

### **v1.0 - 25/07/2025**
- ✅ Sistema inicial implementado
- ✅ Banco de dados SQLite criado
- ✅ Endpoints de ativação e verificação
- ✅ Sistema de logs implementado
- ✅ Proteções de segurança aplicadas
- ✅ Documentação técnica criada

---

**Localização Física:** `/var/www/html/cloudfarm-air-license/`  
**Documentação:** `/root/CloudFarmAir_backend/docs/cloudfarm-air-license.md`  
**Contato:** Sistema desenvolvido seguindo especificações do projeto CloudFarm Air

---

*Sistema pronto para integração e uso em produção! 🎉* 