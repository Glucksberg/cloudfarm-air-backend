# 🔋 **IMPLEMENTAÇÃO: Background Check Android**

## 🎯 **Objetivo**
Implementar verificação automática a cada 3 horas, mesmo com app fechado, para estender automaticamente o período offline quando o dispositivo estiver em área de serviço.

---

## ⚙️ **Arquitetura**

```
🔄 A cada 3 horas (WorkManager)
     ↓
📡 Verificar conectividade
     ↓
✅ Se conectado → POST background_check.php
     ↓
🔄 Reset last_online_check no servidor
     ↓
📈 Piloto ganha +15 dias offline automaticamente
```

---

## 🛠️ **Implementação Android**

### **1. Adicionar Dependências (build.gradle)**

```gradle
dependencies {
    // WorkManager para background tasks
    implementation 'androidx.work:work-runtime-ktx:2.8.1'
    
    // Network checking
    implementation 'androidx.lifecycle:lifecycle-process:2.6.2'
}
```

### **2. Adicionar Permissões (AndroidManifest.xml)**

```xml
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
<uses-permission android:name="android.permission.INTERNET" />

<!-- Para manter background job ativo -->
<uses-permission android:name="android.permission.WAKE_LOCK" />
```

### **3. Criar BackgroundCheckWorker.kt**

```kotlin
import android.content.Context
import androidx.work.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.util.concurrent.TimeUnit

class BackgroundCheckWorker(
    context: Context,
    workerParams: WorkerParameters
) : CoroutineWorker(context, workerParams) {

    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        try {
            // 1. Verificar conectividade
            if (!isNetworkAvailable()) {
                return@withContext Result.success()
            }

            // 2. Obter dados salvos
            val sharedPrefs = applicationContext.getSharedPreferences("license_data", Context.MODE_PRIVATE)
            val androidId = sharedPrefs.getString("android_id", null)
            val username = sharedPrefs.getString("username", null)
            
            if (androidId.isNullOrEmpty()) {
                return@withContext Result.success() // Nada para fazer
            }

            // 3. Fazer background check
            val success = performBackgroundCheck(androidId, username)
            
            if (success) {
                // 4. Salvar último check bem-sucedido
                sharedPrefs.edit()
                    .putLong("last_background_check", System.currentTimeMillis())
                    .apply()
                    
                return@withContext Result.success()
            } else {
                return@withContext Result.retry()
            }
            
        } catch (e: Exception) {
            return@withContext Result.failure()
        }
    }

    private fun isNetworkAvailable(): Boolean {
        val connectivityManager = applicationContext.getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
        val network = connectivityManager.activeNetwork ?: return false
        val networkCapabilities = connectivityManager.getNetworkCapabilities(network) ?: return false
        return networkCapabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
    }

    private suspend fun performBackgroundCheck(androidId: String, username: String?): Boolean {
        return try {
            val client = OkHttpClient.Builder()
                .connectTimeout(10, TimeUnit.SECONDS)
                .writeTimeout(10, TimeUnit.SECONDS)
                .readTimeout(10, TimeUnit.SECONDS)
                .build()

            val formBody = FormBody.Builder()
                .add("android_id", androidId)
                .add("username", username ?: "")
                .add("app_version", BuildConfig.VERSION_NAME)
                .build()

            val request = Request.Builder()
                .url("http://107.189.20.223/cloudfarm-air-license/background_check.php")
                .post(formBody)
                .build()

            val response = client.newCall(request).execute()
            val responseBody = response.body?.string()
            
            // Log do resultado (opcional)
            if (BuildConfig.DEBUG) {
                Log.d("BackgroundCheck", "Response: $responseBody")
            }
            
            response.isSuccessful
            
        } catch (e: Exception) {
            false
        }
    }
}
```

### **4. Configurar WorkManager (Application.kt ou MainActivity.kt)**

```kotlin
class MyApplication : Application() {
    override fun onCreate() {
        super.onCreate()
        setupBackgroundCheck()
    }

    private fun setupBackgroundCheck() {
        val constraints = Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED)
            .setRequiresBatteryNotLow(false) // Permitir mesmo com bateria baixa
            .build()

        val backgroundWork = PeriodicWorkRequestBuilder<BackgroundCheckWorker>(
            3, TimeUnit.HOURS, // A cada 3 horas
            15, TimeUnit.MINUTES // Flexibilidade de ±15 min
        )
        .setConstraints(constraints)
        .setBackoffCriteria(
            BackoffPolicy.EXPONENTIAL,
            30, TimeUnit.SECONDS
        )
        .build()

        WorkManager.getInstance(this)
            .enqueueUniquePeriodicWork(
                "background_license_check",
                ExistingPeriodicWorkPolicy.KEEP,
                backgroundWork
            )
    }
}
```

### **5. Iniciar Background Check após Login**

```kotlin
// Em AuthRepository.kt após login bem-sucedido
private fun startBackgroundChecks() {
    val context = /* obter context */
    val workManager = WorkManager.getInstance(context)
    
    // Cancelar work anterior se existir
    workManager.cancelUniqueWork("background_license_check")
    
    // Iniciar novo
    (context.applicationContext as MyApplication).setupBackgroundCheck()
}
```

---

## 📊 **Fluxo Completo**

### **🔄 Cronograma Típico (Piloto Agricultura)**

| **Tempo** | **Ação** | **Background Check** | **Status** |
|-----------|----------|---------------------|------------|
| **00:00** | Piloto inicia voo | ✅ Online (reset) | 15 dias restantes |
| **03:00** | Piloto em campo remoto | ❌ Sem conexão | 15 dias restantes |
| **06:00** | Passa por cidade | ✅ **AUTO-RESET** | **15 dias restantes** |
| **09:00** | Campo remoto novamente | ❌ Sem conexão | 15 dias restantes |
| **(+14 dias)** | Ainda em campo | ❌ Sem conexão | 1 dia restante |
| **(+15 dias)** | Sem conexão | ❌ App bloqueia | **BLOQUEADO** |

### **✅ BENEFÍCIOS:**
- **Piloto não precisa lembrar** de abrir app
- **Extensão automática** quando há sinal
- **15 dias reais** de uso offline garantidos
- **Funciona em background** (app fechado)

---

## 🧪 **Testes**

### **Testar Backend:**
```bash
# Copiar arquivos para VPS
sudo cp /root/CloudFarmAir_backend/cloudfarm-air-license/*.php /var/www/html/cloudfarm-air-license/
sudo chown www-data:www-data /var/www/html/cloudfarm-air-license/*.php

# Testar endpoints
php /var/www/html/cloudfarm-air-license/test_offline_logic.php
php /var/www/html/cloudfarm-air-license/test_background_check.php
```

### **Testar Android:**
```kotlin
// Forçar execução imediata do background check
WorkManager.getInstance(this)
    .enqueue(OneTimeWorkRequestBuilder<BackgroundCheckWorker>().build())
```

---

## ⚡ **Otimizações**

### **1. Battery Optimization**
```kotlin
// Solicitar exclusão da otimização de bateria
val intent = Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS)
intent.data = Uri.parse("package:$packageName")
startActivity(intent)
```

### **2. Retry Strategy**
```kotlin
.setBackoffCriteria(
    BackoffPolicy.EXPONENTIAL,
    30, TimeUnit.SECONDS
)
```

### **3. Network-Only Execution**
```kotlin
.setRequiredNetworkType(NetworkType.CONNECTED)
```

---

## 📱 **Estados de Resposta**

```kotlin
when (response.status) {
    "extended" -> {
        // ✅ Sucesso: Período offline resetado
        Log.d("Background", "Período offline estendido automaticamente")
    }
    "not_registered" -> {
        // ⚠️ Dispositivo não registrado
        // Cancelar background checks até próximo login
    }
    "expired" -> {
        // ❌ Licença expirada
        // Continuar tentando (para quando renovar)
    }
}
```

---

## 🚨 **IMPORTANTE PARA IMPLEMENTAÇÃO**

### **✅ Fazer:**
1. **Implementar NetworkCallback** para detectar conectividade
2. **Salvar timestamp** do último background check bem-sucedido
3. **Mostrar status** no app (ex: "Última verificação: 2h atrás")
4. **Testar em dispositivo real** (não emulador)

### **❌ Evitar:**
1. **Não fazer** background checks muito frequentes (< 3h)
2. **Não ignorar** restrições de bateria do sistema
3. **Não fazer** requests longos (timeout 10s máximo)

---

## 🎯 **Resultado Final**

🚁 **Para o Piloto:**
- **App funciona por 15 dias** sem conexão
- **Extensão automática** quando passa por área de serviço
- **Não precisa lembrar** de abrir app
- **Foco no trabalho**, não na tecnologia

🛡️ **Para o Sistema:**
- **Verificação segura** server-side
- **Impossível burlar** período offline
- **Logs detalhados** para auditoria
- **Controle preciso** de licenças

---

## ✅ **IMPLEMENTAÇÃO COMPLETA**
**Backend:** ✅ Pronto (15 dias + background_check.php)  
**Android:** 📋 Documentado (usar código acima)  
**Testes:** ✅ Disponíveis 