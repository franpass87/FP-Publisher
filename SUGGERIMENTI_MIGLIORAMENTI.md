# Suggerimenti di Miglioramento - FP Digital Publisher

> **Analisi tecnica completa del plugin e raccomandazioni per ottimizzazioni funzionali e logiche**
> 
> Data analisi: 2025-10-05  
> Versione plugin: 0.1.1  
> Linee di codice PHP: ~13.500

---

## 📋 Indice

1. [Architettura e Design Pattern](#1-architettura-e-design-pattern)
2. [Performance e Scalabilità](#2-performance-e-scalabilità)
3. [Sicurezza](#3-sicurezza)
4. [Gestione Errori e Resilienza](#4-gestione-errori-e-resilienza)
5. [Database e Data Management](#5-database-e-data-management)
6. [API e Integrazioni](#6-api-e-integrazioni)
7. [Monitoraggio e Osservabilità](#7-monitoraggio-e-osservabilità)
8. [User Experience](#8-user-experience)
9. [Developer Experience](#9-developer-experience)
10. [Testing e Quality Assurance](#10-testing-e-quality-assurance)

---

## 1. Architettura e Design Pattern

### 🔴 **PRIORITÀ ALTA**

#### 1.1 Implementare Event Sourcing per il Sistema di Code

**Problema attuale:**
La coda aggiorna direttamente lo stato dei job senza mantenere una storia completa delle transizioni.

**Suggerimento:**
```php
// Creare una tabella per gli eventi della coda
CREATE TABLE fp_pub_job_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(64) NOT NULL, -- 'claimed', 'completed', 'failed', 'retried'
    payload_json LONGTEXT NULL,
    occurred_at DATETIME NOT NULL,
    metadata_json LONGTEXT NULL,
    KEY job_id (job_id),
    KEY event_type (event_type),
    KEY occurred_at (occurred_at)
);
```

**Benefici:**
- Debug completo del ciclo di vita dei job
- Audit trail per compliance
- Ricostruzione dello stato da eventi storici
- Analytics sul comportamento della coda

---

#### 1.2 Introdurre Pattern Repository per l'Accesso ai Dati

**Problema attuale:**
Accesso diretto a `$wpdb` sparso in tutto il codice (es. `Queue.php`, `Alerts.php`, `Approvals.php`).

**Suggerimento:**
```php
namespace FP\Publisher\Infra\Repositories;

interface JobRepository {
    public function findById(int $id): ?Job;
    public function findDue(DateTimeImmutable $before, int $limit): array;
    public function save(Job $job): void;
    public function claim(Job $job): ?Job;
}

final class WpdbJobRepository implements JobRepository {
    public function __construct(private wpdb $wpdb) {}
    
    public function findById(int $id): ?Job {
        // Implementazione con query builder
    }
}
```

**Benefici:**
- Testabilità migliorata (mock dei repository)
- Separazione delle responsabilità
- Facilità di migrazione a diversi storage
- Query più manutenibili

---

#### 1.3 Implementare Service Layer Pattern

**Problema attuale:**
Logica di business mista con accesso dati e API REST in `Routes.php` (1.525 righe).

**Suggerimento:**
```php
// Separare la logica business
namespace FP\Publisher\Services\Planning;

final class PlanService {
    public function __construct(
        private PlanRepository $plans,
        private ApprovalService $approvals,
        private EventDispatcher $events
    ) {}
    
    public function createDraft(CreatePlanCommand $command): Plan {
        // Validazione
        // Business logic
        // Persistenza
        // Eventi
    }
}

// Routes.php diventa più snello
final class Routes {
    public static function createPlan(WP_REST_Request $request): WP_REST_Response {
        $service = Container::get(PlanService::class);
        $command = CreatePlanCommand::fromRequest($request);
        $plan = $service->createDraft($command);
        return new WP_REST_Response($plan->toArray(), 201);
    }
}
```

---

### 🟡 **PRIORITÀ MEDIA**

#### 1.4 Dependency Injection Container

**Problema attuale:**
Uso di metodi statici e accoppiamento forte (es. `Logger::get()`, `Options::get()`).

**Suggerimento:**
```php
// Utilizzare un DI container (es. PHP-DI, Pimple)
$container = new Container();

$container->set(LoggerInterface::class, function() {
    return new StructuredLogger();
});

$container->set(QueueService::class, function($c) {
    return new QueueService(
        $c->get(LoggerInterface::class),
        $c->get(OptionsInterface::class)
    );
});

// Usare constructor injection invece di static calls
final class Worker {
    public function __construct(
        private LoggerInterface $logger,
        private SchedulerInterface $scheduler
    ) {}
}
```

**Benefici:**
- Testabilità enormemente migliorata
- Configurazione centralizzata
- Facilità di swap di implementazioni

---

## 2. Performance e Scalabilità

### 🔴 **PRIORITÀ ALTA**

#### 2.1 Implementare Caching Multi-Layer

**Problema attuale:**
Solo 8 utilizzi di transient/cache in tutto il codebase. Molte query ripetitive.

**Suggerimento:**
```php
// Object cache per queries frequenti
final class CachedPlanRepository implements PlanRepository {
    public function __construct(
        private PlanRepository $inner,
        private CacheInterface $cache
    ) {}
    
    public function findById(int $id): ?Plan {
        return $this->cache->remember(
            "plan:{$id}",
            3600,
            fn() => $this->inner->findById($id)
        );
    }
}

// Implementare cache warming per best time suggestions
final class BestTime {
    public static function warmCache(): void {
        foreach (Options::get('channels') as $channel) {
            self::getCachedSuggestions($channel);
        }
    }
}
```

**Aree da cachare:**
- Opzioni plugin (già in option cache ma aggiungere object cache)
- Token di accesso (con TTL based su expires_at)
- Suggerimenti best time per canale
- Conteggi statistiche dashboard
- Risultati blackout windows

---

#### 2.2 Query Optimization e Indexing

**Problema attuale:**
Alcune query potrebbero beneficiare di indici composti.

**Suggerimento:**
```sql
-- Per Queue::dueJobs()
ALTER TABLE wp_fp_pub_jobs 
ADD INDEX status_run_at_id (status, run_at, id);

-- Per Alerts::collectFailedJobs()
ALTER TABLE wp_fp_pub_jobs 
ADD INDEX status_updated_at (status, updated_at);

-- Per la paginate con filtri
ALTER TABLE wp_fp_pub_jobs 
ADD INDEX channel_status_run_at (channel, status, run_at);
```

---

#### 2.3 Batch Processing per Housekeeping

**Problema attuale:**
Housekeeping processa solo 250 record per esecuzione, potrebbe non tenere il passo con alto volume.

**Suggerimento:**
```php
final class Housekeeping {
    private const BATCH_LIMIT = 250;
    private const MAX_ITERATIONS = 10; // Nuvo
    
    public static function run(): void {
        $archived = 0;
        $purged = 0;
        
        // Iterare finché ci sono record da processare (max 10 iterazioni)
        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $archivedBatch = self::archiveJobsBatch();
            $purgedBatch = self::purgeExpiredAssetsBatch();
            
            $archived += $archivedBatch;
            $purged += $purgedBatch;
            
            if ($archivedBatch === 0 && $purgedBatch === 0) {
                break; // Tutto pulito
            }
        }
        
        Logger::get()->info('Housekeeping completed', [
            'archived_jobs' => $archived,
            'purged_assets' => $purged,
            'iterations' => $i + 1
        ]);
    }
}
```

---

#### 2.4 Connection Pooling per Worker

**Problema attuale:**
Ogni job processing potrebbe aprire nuove connessioni DB.

**Suggerimento:**
```php
final class Worker {
    public static function process(): void {
        global $wpdb;
        
        // Riutilizzare la connessione esistente
        $limit = max(1, (int) Options::get('queue.max_concurrent', 5));
        $jobs = Scheduler::getRunnableJobs(Dates::now('UTC'), $limit);
        
        foreach ($jobs as $job) {
            try {
                do_action('fp_publisher_process_job', $job);
            } catch (Throwable $e) {
                // Loggare e continuare
                Logger::get()->error('Job processing failed', [
                    'job_id' => $job['id'],
                    'error' => $e->getMessage()
                ]);
            }
            
            // Prevenire memory leak
            wp_cache_flush();
        }
        
        // Chiudere connessioni non necessarie
        $wpdb->close();
    }
}
```

---

### 🟡 **PRIORITÀ MEDIA**

#### 2.5 Lazy Loading per Payload JSON

**Problema attuale:**
Payload JSON viene sempre decodificato anche se non necessario.

**Suggerimento:**
```php
final class Job {
    private ?array $cachedPayload = null;
    
    public function __construct(
        public readonly int $id,
        private readonly string $payloadJson,
        // ... altri campi
    ) {}
    
    public function payload(): array {
        if ($this->cachedPayload === null) {
            $this->cachedPayload = json_decode($this->payloadJson, true) ?? [];
        }
        return $this->cachedPayload;
    }
}
```

---

## 3. Sicurezza

### 🔴 **PRIORITÀ ALTA**

#### 3.1 Rate Limiting per API REST

**Problema attuale:**
Nessun rate limiting sugli endpoint REST, possibili abusi.

**Suggerimento:**
```php
final class RateLimiter {
    public static function check(string $key, int $maxRequests, int $windowSeconds): bool {
        $transientKey = 'fp_pub_rl_' . md5($key);
        $requests = get_transient($transientKey) ?: [];
        $now = time();
        
        // Rimuovere richieste fuori dalla finestra
        $requests = array_filter($requests, fn($ts) => $ts > $now - $windowSeconds);
        
        if (count($requests) >= $maxRequests) {
            return false; // Rate limit exceeded
        }
        
        $requests[] = $now;
        set_transient($transientKey, $requests, $windowSeconds);
        return true;
    }
}

// In Routes.php
public static function authorize(WP_REST_Request $request, string $capability): bool {
    $userId = get_current_user_id();
    $rateLimitKey = "user:{$userId}:" . $request->get_route();
    
    if (!RateLimiter::check($rateLimitKey, 60, 60)) { // 60 req/min
        throw new RuntimeException(__('Rate limit exceeded. Try again later.', 'fp-publisher'));
    }
    
    return Capabilities::userCan($capability);
}
```

---

#### 3.2 Input Validation più Robusta

**Problema attuale:**
Validazione principalmente via `sanitize_*`, mancano controlli strutturali.

**Suggerimento:**
```php
namespace FP\Publisher\Support;

final class Validator {
    // Aggiungere metodi specifici per domini complessi
    public static function url(mixed $value, bool $required = true): string {
        $url = is_string($value) ? trim($value) : '';
        
        if ($url === '' && !$required) {
            return '';
        }
        
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ValidationException('Invalid URL format');
        }
        
        // Whitelist di schemi
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new ValidationException('Only HTTP(S) URLs are allowed');
        }
        
        return esc_url_raw($url);
    }
    
    public static function json(mixed $value, int $maxDepth = 512): array {
        if (!is_string($value)) {
            throw new ValidationException('JSON must be a string');
        }
        
        try {
            $decoded = json_decode($value, true, $maxDepth, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new ValidationException('JSON must decode to an array');
            }
            return $decoded;
        } catch (JsonException $e) {
            throw new ValidationException('Invalid JSON: ' . $e->getMessage());
        }
    }
}
```

---

#### 3.3 SQL Injection Prevention

**Problema attuale:**
Alcune query usano interpolazione diretta di variabili sanitizzate ma non prepare.

**Suggerimento:**
```php
// ❌ EVITARE (in Housekeeping.php:112)
$wpdb->query("DELETE FROM {$jobsTable} WHERE id IN ({$idList})");

// ✅ PREFERIRE
$placeholders = implode(',', array_fill(0, count($ids), '%d'));
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$jobsTable} WHERE id IN ($placeholders)",
    ...$ids
));
```

---

#### 3.4 CSRF Protection Migliorata

**Problema attuale:**
Verifica nonce presente ma potrebbe essere più rigorosa.

**Suggerimento:**
```php
final class Routes {
    private const NONCE_ACTION = 'fp_publisher_api';
    
    public static function verifyNonce(WP_REST_Request $request): bool {
        // Accettare sia header che cookie
        $nonce = $request->get_header('X-WP-Nonce') 
               ?? $request->get_param('_wpnonce');
        
        if (!$nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            throw new SecurityException(__('Invalid security token', 'fp-publisher'));
        }
        
        // Verificare anche origin per richieste critiche
        $origin = $request->get_header('Origin');
        if ($origin && !self::isAllowedOrigin($origin)) {
            throw new SecurityException(__('Invalid request origin', 'fp-publisher'));
        }
        
        return true;
    }
    
    private static function isAllowedOrigin(string $origin): bool {
        $allowed = [
            home_url(),
            admin_url(),
            site_url()
        ];
        
        return in_array(rtrim($origin, '/'), $allowed, true);
    }
}
```

---

### 🟡 **PRIORITÀ MEDIA**

#### 3.5 Encryption Key Rotation

**Problema attuale:**
Chiave di encryption basata su `AUTH_KEY`, nessun meccanismo di rotazione.

**Suggerimento:**
```php
final class Options {
    private const ENCRYPTION_VERSION = 'v2'; // Incrementare ad ogni rotazione
    
    private static function encodeToken(string $token, string $service): string {
        $version = self::ENCRYPTION_VERSION;
        $key = self::encryptionKey($version);
        
        // ... encryption logic ...
        
        return "sodium:{$version}:" . base64_encode($nonce . $ciphertext);
    }
    
    private static function encryptionKey(string $version): string {
        $siteKey = defined('AUTH_KEY') && AUTH_KEY !== '' ? AUTH_KEY : '';
        $versionSalt = get_option('fp_pub_enc_salt_' . $version);
        
        if (!$versionSalt) {
            $versionSalt = wp_generate_password(32, true, true);
            update_option('fp_pub_enc_salt_' . $version, $versionSalt, false);
        }
        
        $combined = $siteKey . '|' . $versionSalt . '|' . $version;
        return sodium_crypto_generichash($combined, '', self::keyLength());
    }
}
```

---

## 4. Gestione Errori e Resilienza

### 🔴 **PRIORITÀ ALTA**

#### 4.1 Circuit Breaker per Connettori Esterni

**Problema attuale:**
Nessun circuit breaker, se un'API è down il sistema continua a tentare.

**Suggerimento:**
```php
namespace FP\Publisher\Support;

final class CircuitBreaker {
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';
    
    public function __construct(
        private string $service,
        private int $failureThreshold = 5,
        private int $timeoutSeconds = 60
    ) {}
    
    public function call(callable $callback): mixed {
        $state = $this->getState();
        
        if ($state === self::STATE_OPEN) {
            if (!$this->shouldAttemptReset()) {
                throw new CircuitBreakerOpenException(
                    "Circuit breaker is OPEN for {$this->service}"
                );
            }
            $this->setState(self::STATE_HALF_OPEN);
        }
        
        try {
            $result = $callback();
            $this->onSuccess();
            return $result;
        } catch (Throwable $e) {
            $this->onFailure();
            throw $e;
        }
    }
    
    private function onSuccess(): void {
        $this->setState(self::STATE_CLOSED);
        delete_transient($this->getFailureCountKey());
    }
    
    private function onFailure(): void {
        $count = (int) get_transient($this->getFailureCountKey()) + 1;
        set_transient($this->getFailureCountKey(), $count, $this->timeoutSeconds);
        
        if ($count >= $this->failureThreshold) {
            $this->setState(self::STATE_OPEN);
            Logger::get()->critical("Circuit breaker opened for {$this->service}", [
                'failures' => $count,
                'threshold' => $this->failureThreshold
            ]);
        }
    }
    
    private function getState(): string {
        return get_transient($this->getStateKey()) ?: self::STATE_CLOSED;
    }
    
    private function setState(string $state): void {
        set_transient($this->getStateKey(), $state, $this->timeoutSeconds);
    }
    
    private function shouldAttemptReset(): bool {
        $openedAt = get_transient($this->getOpenedAtKey());
        return $openedAt && (time() - $openedAt) >= $this->timeoutSeconds;
    }
    
    private function getStateKey(): string {
        return "fp_pub_cb_state_{$this->service}";
    }
    
    private function getFailureCountKey(): string {
        return "fp_pub_cb_failures_{$this->service}";
    }
    
    private function getOpenedAtKey(): string {
        return "fp_pub_cb_opened_{$this->service}";
    }
}

// Uso nei dispatcher
final class MetaDispatcher {
    private static function handlePublish(array $job, array $payload, string $channel): void {
        $breaker = new CircuitBreaker('meta_api', 5, 120);
        
        try {
            $result = $breaker->call(function() use ($channel, $payload) {
                return $channel === self::CHANNEL_FACEBOOK
                    ? Client::publishFacebookPost($payload)
                    : Client::publishInstagramMedia($payload);
            });
            
            // ... resto della logica
        } catch (CircuitBreakerOpenException $e) {
            // Circuit breaker aperto, schedulare retry più lungo
            Queue::markFailed($job, $e->getMessage(), true);
        }
    }
}
```

**Benefici:**
- Riduzione carico su API esterne in fail
- Protezione del sistema da cascading failures
- Recovery automatico
- Metriche di affidabilità

---

#### 4.2 Retry con Exponential Backoff e Jitter (già implementato ma migliorabile)

**Miglioramento attuale:**
```php
final class Queue {
    private static function calculateBackoff(int $attempts, array $config): int {
        $base = (int) ($config['base'] ?? 60);
        $factor = (float) ($config['factor'] ?? 2.0);
        $maxDelay = (int) ($config['max'] ?? 3600);
        
        // ✅ Miglioramento: decorrelation jitter (AWS recommendation)
        $prevDelay = $base;
        for ($i = 1; $i < $attempts; $i++) {
            $prevDelay = min($maxDelay, (int)($prevDelay * $factor));
        }
        
        try {
            // Decorrelated jitter: random tra base e prevDelay * 3
            $jitter = random_int($base, min($maxDelay, $prevDelay * 3));
        } catch (Exception) {
            $jitter = $prevDelay;
        }
        
        return min($maxDelay, $jitter);
    }
}
```

---

#### 4.3 Dead Letter Queue

**Problema attuale:**
Job falliti restano nella tabella principale, nessuna separazione.

**Suggerimento:**
```php
// Aggiungere tabella DLQ
CREATE TABLE wp_fp_pub_jobs_dlq (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_job_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(64) NOT NULL,
    payload_json LONGTEXT NULL,
    final_error TEXT NOT NULL,
    total_attempts SMALLINT UNSIGNED NOT NULL,
    first_attempt_at DATETIME NOT NULL,
    moved_to_dlq_at DATETIME NOT NULL,
    metadata_json LONGTEXT NULL,
    KEY channel (channel),
    KEY moved_to_dlq_at (moved_to_dlq_at)
);

final class Queue {
    public static function markFailed(array $job, string $error, bool $retryable = false): void {
        // ... logica esistente ...
        
        // Se non retryable e ha raggiunto max attempts, spostare in DLQ
        if (!$retryable || $attempts >= $maxAttempts) {
            self::moveToDLQ($job, $error, $attempts);
        }
        
        // ... resto della logica
    }
    
    private static function moveToDLQ(array $job, string $error, int $attempts): void {
        global $wpdb;
        
        $dlqTable = $wpdb->prefix . 'fp_pub_jobs_dlq';
        $wpdb->insert($dlqTable, [
            'original_job_id' => (int) $job['id'],
            'channel' => (string) $job['channel'],
            'payload_json' => self::encodePayload($job['payload'] ?? []),
            'final_error' => $error,
            'total_attempts' => $attempts,
            'first_attempt_at' => $job['created_at']->format('Y-m-d H:i:s'),
            'moved_to_dlq_at' => Dates::now('UTC')->format('Y-m-d H:i:s'),
            'metadata_json' => wp_json_encode([
                'idempotency_key' => $job['idempotency_key'],
                'last_run_at' => $job['run_at']->format(DateTimeInterface::ATOM)
            ])
        ]);
        
        // Emettere evento per notifica/alert
        do_action('fp_publisher_job_moved_to_dlq', $job, $error);
    }
}
```

---

### 🟡 **PRIORITÀ MEDIA**

#### 4.4 Graceful Degradation

**Suggerimento:**
```php
final class Scheduler {
    public static function getRunnableJobs(
        DateTimeImmutable $now, 
        ?int $limit = null
    ): array {
        try {
            return self::getRunnableJobsWithChecks($now, $limit);
        } catch (Throwable $e) {
            Logger::get()->error('Scheduler failed with checks, falling back to simple query', [
                'error' => $e->getMessage()
            ]);
            
            // Fallback: ritornare solo job in pending senza blackout check
            return Queue::dueJobs($now, $limit ?? 5);
        }
    }
    
    private static function getRunnableJobsWithChecks(
        DateTimeImmutable $now, 
        ?int $limit
    ): array {
        // ... logica esistente completa con tutti i check
    }
}
```

---

## 5. Database e Data Management

### 🔴 **PRIORITÀ ALTA**

#### 5.1 Transazioni per Operazioni Critiche

**Problema attuale:**
Operazioni multi-step senza transazioni (es. `Approvals::transition`).

**Suggerimento:**
```php
final class Approvals {
    public static function transition(int $planId, string $targetStatus): array {
        global $wpdb;
        
        // Iniziare transazione
        $wpdb->query('START TRANSACTION');
        
        try {
            // ... logica esistente ...
            
            $updated = $wpdb->update(/* ... */);
            
            if ($updated === false || $updated <= 0) {
                throw new RuntimeException(__('Unable to update the plan status.', 'fp-publisher'));
            }
            
            // Log dell'approvazione in tabella separata
            $wpdb->insert($wpdb->prefix . 'fp_pub_approval_log', [
                'plan_id' => $planId,
                'user_id' => get_current_user_id(),
                'from_status' => $currentStatus,
                'to_status' => $targetStatus,
                'timestamp' => $timestamp->format('Y-m-d H:i:s')
            ]);
            
            // Commit
            $wpdb->query('COMMIT');
            
            return [/* ... */];
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            Logger::get()->error('Approval transition failed', [
                'plan_id' => $planId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
```

---

#### 5.2 Partitioning per Tabelle ad Alto Volume

**Problema attuale:**
Tabella `fp_pub_jobs` crescerà molto, query potrebbero rallentare.

**Suggerimento:**
```sql
-- Partitioning per anno/mese su created_at
ALTER TABLE wp_fp_pub_jobs
PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at)) (
    PARTITION p202410 VALUES LESS THAN (202411),
    PARTITION p202411 VALUES LESS THAN (202412),
    PARTITION p202412 VALUES LESS THAN (202501),
    PARTITION p202501 VALUES LESS THAN (202502),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- Script per aggiungere partizioni automaticamente
final class PartitionManager {
    public static function ensurePartitions(int $monthsAhead = 3): void {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_pub_jobs';
        $now = new DateTimeImmutable();
        
        for ($i = 0; $i <= $monthsAhead; $i++) {
            $date = $now->add(new DateInterval("P{$i}M"));
            $partitionName = 'p' . $date->format('Ym');
            $partitionValue = (int) $date->add(new DateInterval('P1M'))->format('Ym');
            
            // Verificare se esiste
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.PARTITIONS 
                 WHERE TABLE_NAME = %s AND PARTITION_NAME = %s",
                $table,
                $partitionName
            ));
            
            if (!$exists) {
                $wpdb->query("
                    ALTER TABLE {$table}
                    REORGANIZE PARTITION p_future INTO (
                        PARTITION {$partitionName} VALUES LESS THAN ({$partitionValue}),
                        PARTITION p_future VALUES LESS THAN MAXVALUE
                    )
                ");
            }
        }
    }
}
```

---

#### 5.3 Archiving Strategy Migliorata

**Suggerimento:**
```php
final class Housekeeping {
    // Aggiungere compressione per payload in archive
    private static function archiveJobs(): void {
        global $wpdb;
        
        // ... query esistente ...
        
        // Comprimere payload_json per risparmiare spazio
        $wpdb->query(
            "INSERT INTO {$archiveTable} (id, status, channel, payload_json, ...)
            SELECT 
                id, 
                status, 
                channel,
                COMPRESS(payload_json) as payload_json, 
                ...
            FROM {$jobsTable} WHERE id IN ({$idList})"
        );
    }
    
    // Aggiungere metodo per ripristino da archive
    public static function restoreFromArchive(int $jobId): ?array {
        global $wpdb;
        
        $archiveTable = $wpdb->prefix . 'fp_pub_jobs_archive';
        $jobsTable = $wpdb->prefix . 'fp_pub_jobs';
        
        $wpdb->query('START TRANSACTION');
        
        try {
            // Decomprimere e spostare
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$jobsTable} (id, status, channel, payload_json, ...)
                SELECT 
                    id, 
                    'pending' as status,
                    channel,
                    UNCOMPRESS(payload_json) as payload_json,
                    ...
                FROM {$archiveTable} WHERE id = %d",
                $jobId
            ));
            
            $wpdb->delete($archiveTable, ['id' => $jobId]);
            
            $wpdb->query('COMMIT');
            
            return Queue::findById($jobId);
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
}
```

---

### 🟡 **PRIORITÀ MEDIA**

#### 5.4 Read Replicas Support

**Suggerimento:**
```php
final class DatabaseRouter {
    public static function getConnection(bool $write = false): wpdb {
        global $wpdb;
        
        if ($write || !defined('DB_REPLICA_HOST')) {
            return $wpdb; // Primary
        }
        
        // Usare replica per read
        static $replica = null;
        if ($replica === null) {
            $replica = new wpdb(
                DB_REPLICA_USER,
                DB_REPLICA_PASSWORD,
                DB_REPLICA_NAME,
                DB_REPLICA_HOST
            );
        }
        
        return $replica;
    }
}

// Modificare repository per usare routing
final class WpdbJobRepository {
    public function findById(int $id): ?Job {
        $db = DatabaseRouter::getConnection(false); // Read replica OK
        // ...
    }
    
    public function save(Job $job): void {
        $db = DatabaseRouter::getConnection(true); // Must use primary
        // ...
    }
}
```

---

## 6. API e Integrazioni

### 🔴 **PRIORITÀ ALTA**

#### 6.1 API Versioning

**Problema attuale:**
Namespace `fp-publisher/v1` ma nessuna strategia di versioning reale.

**Suggerimento:**
```php
final class Routes {
    public const NAMESPACE_V1 = 'fp-publisher/v1';
    public const NAMESPACE_V2 = 'fp-publisher/v2'; // Futuro
    
    public static function registerRoutes(): void {
        // V1 (mantenere per retrocompatibilità)
        register_rest_route(self::NAMESPACE_V1, '/plans', [
            'methods' => 'GET',
            'callback' => [self::class, 'listPlansV1'],
            'deprecated' => true, // Marcare come deprecato
        ]);
        
        // V2 (nuova versione con miglioramenti)
        register_rest_route(self::NAMESPACE_V2, '/plans', [
            'methods' => 'GET',
            'callback' => [self::class, 'listPlansV2'],
            'args' => [
                'pagination' => [
                    'type' => 'object',
                    'properties' => [
                        'page' => ['type' => 'integer', 'default' => 1],
                        'per_page' => ['type' => 'integer', 'default' => 20],
                        'cursor' => ['type' => 'string'] // Cursor-based pagination
                    ]
                ]
            ]
        ]);
    }
}
```

---

#### 6.2 Webhook System

**Problema attuale:**
Nessun sistema di webhook per notificare sistemi esterni.

**Suggerimento:**
```php
// Nuova tabella
CREATE TABLE wp_fp_pub_webhooks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    url TEXT NOT NULL,
    events_json TEXT NOT NULL, -- ['job.completed', 'plan.published', ...]
    secret VARCHAR(64) NOT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    last_triggered_at DATETIME NULL,
    KEY active (active)
);

namespace FP\Publisher\Services;

final class WebhookDispatcher {
    public static function register(): void {
        // Ascoltare eventi rilevanti
        add_action('fp_pub_published', [self::class, 'onPublished'], 10, 3);
        add_action('fp_publisher_job_moved_to_dlq', [self::class, 'onJobFailed'], 10, 2);
        add_action('fp_publisher_plan_status_changed', [self::class, 'onPlanStatusChanged'], 10, 2);
    }
    
    public static function onPublished(string $channel, ?string $remoteId, array $job): void {
        self::dispatch('job.completed', [
            'job_id' => $job['id'],
            'channel' => $channel,
            'remote_id' => $remoteId,
            'completed_at' => Dates::now('UTC')->format(DateTimeInterface::ATOM)
        ]);
    }
    
    private static function dispatch(string $event, array $payload): void {
        $webhooks = self::getActiveWebhooks($event);
        
        foreach ($webhooks as $webhook) {
            wp_remote_post($webhook['url'], [
                'timeout' => 10,
                'blocking' => false, // Non bloccare
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-FP-Publisher-Event' => $event,
                    'X-FP-Publisher-Signature' => self::sign($payload, $webhook['secret'])
                ],
                'body' => wp_json_encode([
                    'event' => $event,
                    'payload' => $payload,
                    'timestamp' => Dates::now('UTC')->format(DateTimeInterface::ATOM)
                ])
            ]);
            
            // Aggiornare last_triggered_at
            self::updateLastTriggered($webhook['id']);
        }
    }
    
    private static function sign(array $payload, string $secret): string {
        return hash_hmac('sha256', wp_json_encode($payload), $secret);
    }
}
```

---

#### 6.3 API Response Standardization

**Suggerimento:**
```php
final class ApiResponse {
    public static function success(
        mixed $data, 
        int $status = 200, 
        array $meta = []
    ): WP_REST_Response {
        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
            'meta' => array_merge([
                'timestamp' => Dates::now('UTC')->format(DateTimeInterface::ATOM),
                'version' => FP_PUBLISHER_VERSION
            ], $meta)
        ], $status);
    }
    
    public static function error(
        string $message, 
        int $status = 400, 
        ?string $code = null,
        array $details = []
    ): WP_REST_Response {
        return new WP_REST_Response([
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code ?? 'error',
                'details' => $details
            ],
            'meta' => [
                'timestamp' => Dates::now('UTC')->format(DateTimeInterface::ATOM),
                'version' => FP_PUBLISHER_VERSION
            ]
        ], $status);
    }
    
    public static function paginated(
        array $items, 
        int $total, 
        int $page, 
        int $perPage
    ): WP_REST_Response {
        return self::success($items, 200, [
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
                'has_next' => $page * $perPage < $total,
                'has_prev' => $page > 1
            ]
        ]);
    }
}

// Uso
public static function listPlans(WP_REST_Request $request): WP_REST_Response {
    try {
        $result = Queue::paginate(/*...*/);
        return ApiResponse::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    } catch (ValidationException $e) {
        return ApiResponse::error($e->getMessage(), 400, 'validation_error');
    } catch (Throwable $e) {
        Logger::get()->error('Failed to list plans', ['error' => $e->getMessage()]);
        return ApiResponse::error('Internal server error', 500, 'internal_error');
    }
}
```

---

### 🟡 **PRIORITÀ MEDIA**

#### 6.4 GraphQL Support

**Suggerimento:**
```php
// Aggiungere GraphQL accanto a REST per query complesse
// Usare wp-graphql plugin come base

final class PublisherGraphQL {
    public static function register(): void {
        add_action('graphql_register_types', [self::class, 'registerTypes']);
    }
    
    public static function registerTypes(): void {
        register_graphql_object_type('PublisherJob', [
            'fields' => [
                'id' => ['type' => 'ID'],
                'status' => ['type' => 'String'],
                'channel' => ['type' => 'String'],
                'attempts' => ['type' => 'Int'],
                'runAt' => ['type' => 'String'],
                'payload' => ['type' => 'String'] // JSON
            ]
        ]);
        
        register_graphql_field('RootQuery', 'publisherJobs', [
            'type' => ['list_of' => 'PublisherJob'],
            'args' => [
                'status' => ['type' => 'String'],
                'channel' => ['type' => 'String'],
                'limit' => ['type' => 'Int', 'defaultValue' => 20]
            ],
            'resolve' => function($root, $args) {
                $result = Queue::paginate(1, $args['limit'], [
                    'status' => $args['status'] ?? null,
                    'channel' => $args['channel'] ?? null
                ]);
                return $result['items'];
            }
        ]);
    }
}
```

---

## 7. Monitoraggio e Osservabilità

### 🔴 **PRIORITÀ ALTA**

#### 7.1 Metrics Collection

**Suggerimento:**
```php
namespace FP\Publisher\Monitoring;

final class Metrics {
    private static array $counters = [];
    private static array $gauges = [];
    private static array $histograms = [];
    
    public static function incrementCounter(string $metric, int $value = 1, array $tags = []): void {
        $key = self::buildKey($metric, $tags);
        self::$counters[$key] = (self::$counters[$key] ?? 0) + $value;
    }
    
    public static function recordGauge(string $metric, float $value, array $tags = []): void {
        $key = self::buildKey($metric, $tags);
        self::$gauges[$key] = $value;
    }
    
    public static function recordTiming(string $metric, float $milliseconds, array $tags = []): void {
        $key = self::buildKey($metric, $tags);
        self::$histograms[$key][] = $milliseconds;
    }
    
    public static function flush(): array {
        $snapshot = [
            'counters' => self::$counters,
            'gauges' => self::$gauges,
            'histograms' => array_map(function($values) {
                return [
                    'count' => count($values),
                    'sum' => array_sum($values),
                    'avg' => array_sum($values) / count($values),
                    'min' => min($values),
                    'max' => max($values),
                    'p50' => self::percentile($values, 50),
                    'p95' => self::percentile($values, 95),
                    'p99' => self::percentile($values, 99)
                ];
            }, self::$histograms),
            'timestamp' => time()
        ];
        
        // Reset
        self::$counters = [];
        self::$gauges = [];
        self::$histograms = [];
        
        return $snapshot;
    }
    
    public static function exportPrometheus(): string {
        $output = '';
        
        foreach (self::$counters as $key => $value) {
            $output .= "fp_publisher_{$key} {$value}\n";
        }
        
        foreach (self::$gauges as $key => $value) {
            $output .= "fp_publisher_{$key} {$value}\n";
        }
        
        return $output;
    }
    
    private static function buildKey(string $metric, array $tags): string {
        if (empty($tags)) {
            return $metric;
        }
        
        $tagStr = implode(',', array_map(
            fn($k, $v) => "{$k}={$v}",
            array_keys($tags),
            array_values($tags)
        ));
        
        return "{$metric}{{$tagStr}}";
    }
    
    private static function percentile(array $values, int $percentile): float {
        sort($values);
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;
        return $values[max(0, $index)];
    }
}

// Uso nei dispatcher
final class MetaDispatcher {
    public static function handle(array $job): void {
        $start = microtime(true);
        $channel = $job['channel'];
        
        try {
            // ... logica esistente ...
            
            Metrics::incrementCounter('jobs_processed_total', 1, [
                'channel' => $channel,
                'status' => 'success'
            ]);
        } catch (Throwable $e) {
            Metrics::incrementCounter('jobs_processed_total', 1, [
                'channel' => $channel,
                'status' => 'error'
            ]);
            throw $e;
        } finally {
            $duration = (microtime(true) - $start) * 1000;
            Metrics::recordTiming('job_processing_duration_ms', $duration, [
                'channel' => $channel
            ]);
        }
    }
}

// Endpoint Prometheus
register_rest_route('fp-publisher/v1', '/metrics', [
    'methods' => 'GET',
    'callback' => function() {
        return new WP_REST_Response(
            Metrics::exportPrometheus(),
            200,
            ['Content-Type' => 'text/plain; version=0.0.4']
        );
    },
    'permission_callback' => function() {
        // Autenticare con token specifico
        return isset($_SERVER['HTTP_AUTHORIZATION']) 
            && $_SERVER['HTTP_AUTHORIZATION'] === 'Bearer ' . get_option('fp_pub_metrics_token');
    }
]);
```

---

#### 7.2 Health Check Endpoint

**Suggerimento:**
```php
final class HealthCheck {
    public static function register(): void {
        register_rest_route('fp-publisher/v1', '/health', [
            'methods' => 'GET',
            'callback' => [self::class, 'check'],
            'permission_callback' => '__return_true' // Pubblico per load balancer
        ]);
    }
    
    public static function check(): WP_REST_Response {
        $checks = [
            'database' => self::checkDatabase(),
            'queue' => self::checkQueue(),
            'cron' => self::checkCron(),
            'storage' => self::checkStorage(),
            'external_apis' => self::checkExternalAPIs()
        ];
        
        $healthy = !in_array(false, array_column($checks, 'healthy'), true);
        $status = $healthy ? 200 : 503;
        
        return new WP_REST_Response([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => Dates::now('UTC')->format(DateTimeInterface::ATOM),
            'version' => FP_PUBLISHER_VERSION
        ], $status);
    }
    
    private static function checkDatabase(): array {
        global $wpdb;
        
        try {
            $wpdb->get_var("SELECT 1");
            return ['healthy' => true, 'message' => 'Database connection OK'];
        } catch (Throwable $e) {
            return ['healthy' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }
    
    private static function checkQueue(): array {
        try {
            $pending = count(Queue::dueJobs(Dates::now('UTC'), 1));
            $running = array_sum(Queue::runningChannels());
            
            // Alert se troppi job in pending
            $healthy = $pending < 1000 && $running < 100;
            
            return [
                'healthy' => $healthy,
                'pending_jobs' => $pending,
                'running_jobs' => $running,
                'message' => $healthy ? 'Queue healthy' : 'Queue backlog detected'
            ];
        } catch (Throwable $e) {
            return ['healthy' => false, 'message' => 'Queue check failed: ' . $e->getMessage()];
        }
    }
    
    private static function checkCron(): array {
        $nextTick = wp_next_scheduled(Worker::EVENT);
        $healthy = $nextTick !== false && $nextTick > time() - 300; // Non più di 5min fa
        
        return [
            'healthy' => $healthy,
            'next_scheduled' => $nextTick ? date('c', $nextTick) : null,
            'message' => $healthy ? 'Cron scheduled' : 'Cron may be stuck'
        ];
    }
    
    private static function checkStorage(): array {
        $uploads = wp_upload_dir();
        $writable = is_writable($uploads['basedir']);
        $freeSpace = disk_free_space($uploads['basedir']);
        
        return [
            'healthy' => $writable && $freeSpace > 1073741824, // > 1GB
            'writable' => $writable,
            'free_space_gb' => round($freeSpace / 1073741824, 2),
            'message' => $writable ? 'Storage OK' : 'Storage not writable'
        ];
    }
    
    private static function checkExternalAPIs(): array {
        // Verificare che i circuit breaker non siano tutti aperti
        $services = ['meta', 'tiktok', 'youtube', 'google_business'];
        $openCircuits = 0;
        
        foreach ($services as $service) {
            $state = get_transient("fp_pub_cb_state_{$service}");
            if ($state === 'open') {
                $openCircuits++;
            }
        }
        
        return [
            'healthy' => $openCircuits < count($services) / 2, // < 50% falliti
            'open_circuits' => $openCircuits,
            'total_services' => count($services),
            'message' => "{ $openCircuits}/{count($services)} services unavailable"
        ];
    }
}
```

---

#### 7.3 Distributed Tracing

**Suggerimento:**
```php
namespace FP\Publisher\Monitoring;

final class Tracer {
    private static ?string $traceId = null;
    private static array $spans = [];
    
    public static function startTrace(string $name): string {
        self::$traceId = wp_generate_uuid4();
        return self::startSpan($name, null);
    }
    
    public static function startSpan(string $name, ?string $parentId = null): string {
        $spanId = bin2hex(random_bytes(8));
        
        self::$spans[$spanId] = [
            'trace_id' => self::$traceId,
            'span_id' => $spanId,
            'parent_id' => $parentId,
            'name' => $name,
            'start_time' => microtime(true),
            'tags' => []
        ];
        
        return $spanId;
    }
    
    public static function endSpan(string $spanId, array $tags = []): void {
        if (!isset(self::$spans[$spanId])) {
            return;
        }
        
        self::$spans[$spanId]['end_time'] = microtime(true);
        self::$spans[$spanId]['duration'] = 
            self::$spans[$spanId]['end_time'] - self::$spans[$spanId]['start_time'];
        self::$spans[$spanId]['tags'] = array_merge(
            self::$spans[$spanId]['tags'],
            $tags
        );
    }
    
    public static function addTag(string $spanId, string $key, mixed $value): void {
        if (isset(self::$spans[$spanId])) {
            self::$spans[$spanId]['tags'][$key] = $value;
        }
    }
    
    public static function exportJaeger(): array {
        return [
            'trace_id' => self::$traceId,
            'spans' => array_values(self::$spans)
        ];
    }
}

// Uso
final class Worker {
    public static function process(): void {
        $traceId = Tracer::startTrace('worker.process');
        
        try {
            $jobs = Scheduler::getRunnableJobs(Dates::now('UTC'), 5);
            Tracer::addTag($traceId, 'jobs.count', count($jobs));
            
            foreach ($jobs as $job) {
                $jobSpan = Tracer::startSpan('worker.process_job', $traceId);
                Tracer::addTag($jobSpan, 'job.id', $job['id']);
                Tracer::addTag($jobSpan, 'job.channel', $job['channel']);
                
                try {
                    do_action('fp_publisher_process_job', $job);
                    Tracer::addTag($jobSpan, 'job.status', 'success');
                } catch (Throwable $e) {
                    Tracer::addTag($jobSpan, 'job.status', 'error');
                    Tracer::addTag($jobSpan, 'error.message', $e->getMessage());
                } finally {
                    Tracer::endSpan($jobSpan);
                }
            }
        } finally {
            Tracer::endSpan($traceId);
            
            // Esportare trace (es. via webhook o file)
            if (defined('FP_PUBLISHER_JAEGER_ENDPOINT')) {
                wp_remote_post(FP_PUBLISHER_JAEGER_ENDPOINT, [
                    'blocking' => false,
                    'body' => wp_json_encode(Tracer::exportJaeger())
                ]);
            }
        }
    }
}
```

---

### 🟡 **PRIORITÀ MEDIA**

#### 7.4 APM Integration (Application Performance Monitoring)

**Suggerimento:**
Integrare con servizi come New Relic, DataDog, o Elastic APM:

```php
// Esempio con New Relic
if (extension_loaded('newrelic')) {
    newrelic_name_transaction('FP Publisher - Process Job');
    newrelic_add_custom_parameter('job_id', $job['id']);
    newrelic_add_custom_parameter('channel', $job['channel']);
}
```

---

## 8. User Experience

### 🔴 **PRIORITÀ ALTA**

#### 8.1 Real-time Updates con WebSockets/SSE

**Problema attuale:**
UI polling per aggiornamenti, inefficiente.

**Suggerimento:**
```php
// Server-Sent Events endpoint
final class StreamingUpdates {
    public static function register(): void {
        add_action('rest_api_init', function() {
            register_rest_route('fp-publisher/v1', '/stream/updates', [
                'methods' => 'GET',
                'callback' => [self::class, 'stream'],
                'permission_callback' => function() {
                    return Capabilities::userCan('fp_publisher_manage_plans');
                }
            ]);
        });
    }
    
    public static function stream(): void {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no'); // Nginx
        
        // Flush output buffering
        if (ob_get_level()) {
            ob_end_flush();
        }
        
        $lastCheck = time();
        
        while (true) {
            $updates = self::getUpdatesSince($lastCheck);
            
            if (!empty($updates)) {
                echo "data: " . wp_json_encode($updates) . "\n\n";
                flush();
            } else {
                // Keep-alive
                echo ": heartbeat\n\n";
                flush();
            }
            
            $lastCheck = time();
            sleep(2); // Poll ogni 2 secondi
            
            // Check se client disconnesso
            if (connection_aborted()) {
                break;
            }
        }
    }
    
    private static function getUpdatesSince(int $timestamp): array {
        global $wpdb;
        
        $updates = [];
        
        // Check job status changes
        $jobs = $wpdb->get_results($wpdb->prepare(
            "SELECT id, status, channel FROM {$wpdb->prefix}fp_pub_jobs 
             WHERE UNIX_TIMESTAMP(updated_at) > %d 
             LIMIT 50",
            $timestamp
        ), ARRAY_A);
        
        if ($jobs) {
            $updates['jobs'] = $jobs;
        }
        
        // Check plan status changes
        $plans = $wpdb->get_results($wpdb->prepare(
            "SELECT id, status, brand FROM {$wpdb->prefix}fp_pub_plans 
             WHERE UNIX_TIMESTAMP(updated_at) > %d 
             LIMIT 50",
            $timestamp
        ), ARRAY_A);
        
        if ($plans) {
            $updates['plans'] = $plans;
        }
        
        return $updates;
    }
}

// Frontend: connettere all'SSE
const eventSource = new EventSource('/wp-json/fp-publisher/v1/stream/updates');

eventSource.onmessage = (event) => {
    const updates = JSON.parse(event.data);
    
    if (updates.jobs) {
        updateJobsUI(updates.jobs);
    }
    
    if (updates.plans) {
        updatePlansUI(updates.plans);
    }
};

eventSource.onerror = () => {
    console.error('SSE connection error');
    // Reconnect con exponential backoff
};
```

---

#### 8.2 Bulk Operations

**Problema attuale:**
Nessuna operazione bulk sui job o plan.

**Suggerimento:**
```php
register_rest_route(self::NAMESPACE, '/jobs/bulk', [
    'methods' => 'POST',
    'callback' => [self::class, 'bulkJobAction'],
    'permission_callback' => static fn($request) => 
        self::authorize($request, 'fp_publisher_manage_plans'),
    'args' => [
        'action' => [
            'required' => true,
            'enum' => ['replay', 'cancel', 'delete']
        ],
        'job_ids' => [
            'required' => true,
            'type' => 'array',
            'items' => ['type' => 'integer']
        ]
    ]
]);

public static function bulkJobAction(WP_REST_Request $request): WP_REST_Response {
    $action = $request->get_param('action');
    $jobIds = array_map('intval', $request->get_param('job_ids'));
    
    if (count($jobIds) > 100) {
        return ApiResponse::error('Too many jobs. Maximum 100 per request.', 400);
    }
    
    $results = [
        'success' => [],
        'failed' => []
    ];
    
    foreach ($jobIds as $jobId) {
        try {
            match($action) {
                'replay' => Queue::replay($jobId),
                'cancel' => self::cancelJob($jobId),
                'delete' => self::deleteJob($jobId),
            };
            $results['success'][] = $jobId;
        } catch (Throwable $e) {
            $results['failed'][] = [
                'id' => $jobId,
                'error' => $e->getMessage()
            ];
        }
    }
    
    return ApiResponse::success($results);
}
```

---

#### 8.3 Advanced Filtering e Search

**Suggerimento:**
```php
// Full-text search su jobs
ALTER TABLE wp_fp_pub_jobs 
ADD FULLTEXT INDEX ft_search (idempotency_key, error);

final class Queue {
    public static function search(string $query, array $filters = []): array {
        global $wpdb;
        
        $conditions = ['1=1'];
        $params = [];
        
        // Full-text search
        if ($query !== '') {
            $conditions[] = 'MATCH(idempotency_key, error) AGAINST(%s IN BOOLEAN MODE)';
            $params[] = $wpdb->esc_like($query) . '*';
        }
        
        // Date range filter
        if (isset($filters['date_from'])) {
            $conditions[] = 'created_at >= %s';
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $conditions[] = 'created_at <= %s';
            $params[] = $filters['date_to'];
        }
        
        // Status filter (multi-select)
        if (!empty($filters['statuses'])) {
            $placeholders = implode(',', array_fill(0, count($filters['statuses']), '%s'));
            $conditions[] = "status IN ({$placeholders})";
            $params = array_merge($params, $filters['statuses']);
        }
        
        // Channels filter (multi-select)
        if (!empty($filters['channels'])) {
            $placeholders = implode(',', array_fill(0, count($filters['channels']), '%s'));
            $conditions[] = "channel IN ({$placeholders})";
            $params = array_merge($params, $filters['channels']);
        }
        
        // Attempts range
        if (isset($filters['min_attempts'])) {
            $conditions[] = 'attempts >= %d';
            $params[] = (int) $filters['min_attempts'];
        }
        
        // Build query
        $where = implode(' AND ', $conditions);
        $sql = "SELECT * FROM " . self::table($wpdb) . " WHERE {$where} ORDER BY created_at DESC LIMIT 100";
        
        $prepared = $wpdb->prepare($sql, ...$params);
        $results = $wpdb->get_results($prepared, ARRAY_A);
        
        return array_map(self::hydrate(...), $results ?? []);
    }
}
```

---

### 🟡 **PRIORITÀ MEDIA**

#### 8.4 Export/Import di Configurazioni

**Suggerimento:**
```php
final class ConfigExporter {
    public static function export(): array {
        return [
            'version' => FP_PUBLISHER_VERSION,
            'exported_at' => Dates::now('UTC')->format(DateTimeInterface::ATOM),
            'options' => Options::all(),
            'templates' => self::exportTemplates(),
            'blackout_windows' => Options::get('queue.blackout_windows'),
            'channels' => Options::get('channels'),
            'brands' => Options::get('brands')
        ];
    }
    
    public static function import(array $config): void {
        // Validare versione
        if (version_compare($config['version'], FP_PUBLISHER_VERSION, '>')) {
            throw new RuntimeException('Config from newer version, cannot import');
        }
        
        // Import options (esclusi i token sensibili)
        foreach ($config['options'] as $key => $value) {
            if ($key !== 'tokens') {
                Options::set($key, $value);
            }
        }
        
        // Import templates
        if (isset($config['templates'])) {
            self::importTemplates($config['templates']);
        }
    }
}
```

---

## 9. Developer Experience

### 🔴 **PRIORITÀ ALTA**

#### 9.1 CLI Commands Estesi

**Problema attuale:**
Solo un comando `wp fp-publisher queue`.

**Suggerimento:**
```php
// Aggiungere più comandi WP-CLI

namespace FP\Publisher\Support\Cli;

final class DiagnosticsCommand {
    public function __invoke($args, $assoc_args): void {
        WP_CLI::line('FP Publisher Diagnostics');
        WP_CLI::line('=======================');
        
        // Database
        $this->checkDatabase();
        
        // Queue status
        $this->checkQueue();
        
        // Cron status
        $this->checkCron();
        
        // External APIs
        $this->checkAPIs();
        
        // Disk space
        $this->checkStorage();
    }
    
    private function checkDatabase(): void {
        global $wpdb;
        
        WP_CLI::line("\n📊 Database:");
        
        foreach ($this->getTables() as $table) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $size = $wpdb->get_var("
                SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) 
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE() AND table_name = '{$table}'
            ");
            
            WP_CLI::line("  ✓ {$table}: {$count} rows, {$size} MB");
        }
    }
}

// Registrare comando
WP_CLI::add_command('fp-publisher diagnostics', DiagnosticsCommand::class);
WP_CLI::add_command('fp-publisher worker start', WorkerCommand::class);
WP_CLI::add_command('fp-publisher queue stats', QueueStatsCommand::class);
WP_CLI::add_command('fp-publisher cache clear', CacheClearCommand::class);
```

---

#### 9.2 Development Mode & Debug Tools

**Suggerimento:**
```php
// Abilitare con define in wp-config.php
// define('FP_PUBLISHER_DEBUG', true);

final class Debug {
    public static function enabled(): bool {
        return defined('FP_PUBLISHER_DEBUG') && FP_PUBLISHER_DEBUG === true;
    }
    
    public static function log(string $message, array $context = []): void {
        if (!self::enabled()) {
            return;
        }
        
        $logFile = WP_CONTENT_DIR . '/fp-publisher-debug.log';
        $timestamp = Dates::now()->format('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . wp_json_encode($context) : '';
        
        file_put_contents(
            $logFile,
            "[{$timestamp}] {$message}{$contextStr}\n",
            FILE_APPEND
        );
    }
    
    public static function toolbar(): void {
        if (!self::enabled() || !is_admin()) {
            return;
        }
        
        add_action('admin_bar_menu', function($wp_admin_bar) {
            $stats = [
                'pending' => count(Queue::dueJobs(Dates::now('UTC'), 1000)),
                'running' => array_sum(Queue::runningChannels()),
                'memory' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB'
            ];
            
            $wp_admin_bar->add_node([
                'id' => 'fp-publisher-debug',
                'title' => '🔧 FP Publisher: ' . implode(' | ', array_map(
                    fn($k, $v) => "{$k}: {$v}",
                    array_keys($stats),
                    $stats
                )),
                'href' => admin_url('admin.php?page=fp-publisher-logs')
            ]);
        }, 100);
    }
}

// Registrare
if (Debug::enabled()) {
    Debug::toolbar();
}
```

---

#### 9.3 API Documentation (OpenAPI/Swagger)

**Suggerimento:**
```php
final class ApiDocumentation {
    public static function register(): void {
        register_rest_route('fp-publisher/v1', '/docs', [
            'methods' => 'GET',
            'callback' => [self::class, 'getOpenAPISpec'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    public static function getOpenAPISpec(): WP_REST_Response {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'FP Digital Publisher API',
                'version' => FP_PUBLISHER_VERSION,
                'description' => 'REST API for managing social media publishing queue'
            ],
            'servers' => [
                ['url' => rest_url('fp-publisher/v1')]
            ],
            'paths' => [
                '/jobs' => [
                    'get' => [
                        'summary' => 'List queue jobs',
                        'parameters' => [
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/JobList']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                // ... altri endpoint
            ],
            'components' => [
                'schemas' => [
                    'Job' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'status' => ['type' => 'string', 'enum' => ['pending', 'running', 'completed', 'failed']],
                            'channel' => ['type' => 'string'],
                            'run_at' => ['type' => 'string', 'format' => 'date-time'],
                            // ...
                        ]
                    ]
                ]
            ]
        ];
        
        return new WP_REST_Response($spec, 200);
    }
}

// Servire Swagger UI
add_action('admin_menu', function() {
    add_submenu_page(
        'fp-publisher',
        'API Documentation',
        'API Docs',
        'manage_options',
        'fp-publisher-api-docs',
        function() {
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
            </head>
            <body>
                <div id="swagger-ui"></div>
                <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
                <script>
                    SwaggerUIBundle({
                        url: '<?php echo rest_url('fp-publisher/v1/docs'); ?>',
                        dom_id: '#swagger-ui'
                    });
                </script>
            </body>
            </html>
            <?php
        }
    );
});
```

---

### 🟡 **PRIORITÀ MEDIA**

#### 9.4 Plugin Hooks Documentation

**Suggerimento:**
Creare un file `docs/HOOKS.md` con documentazione completa di tutti gli hook:

```markdown
# Hooks Reference

## Actions

### `fp_publisher_process_job`
Fired when a job is being processed by the worker.

**Parameters:**
- `$job` (array) - The job array with keys: id, channel, payload, etc.

**Example:**
```php
add_action('fp_publisher_process_job', function($job) {
    if ($job['channel'] === 'custom_channel') {
        // Handle custom channel
    }
}, 10, 1);
```

### `fp_pub_published`
Fired after a job completes successfully.

**Parameters:**
- `$channel` (string) - The channel name
- `$remoteId` (string|null) - The remote platform ID
- `$job` (array) - The job data

**Example:**
```php
add_action('fp_pub_published', function($channel, $remoteId, $job) {
    // Track analytics
}, 10, 3);
```

## Filters

### `fp_pub_payload_pre_send`
Modify payload before sending to external API.

**Parameters:**
- `$payload` (array) - The payload data
- `$job` (array) - The job data

**Returns:** array

**Example:**
```php
add_filter('fp_pub_payload_pre_send', function($payload, $job) {
    if ($job['channel'] === 'meta_facebook') {
        $payload['link'] = add_query_arg('utm_source', 'fb', $payload['link']);
    }
    return $payload;
}, 10, 2);
```
```

---

## 10. Testing e Quality Assurance

### 🔴 **PRIORITÀ ALTA**

#### 10.1 Integration Tests Mancanti

**Problema attuale:**
Buona copertura unit test (149 test), ma pochi integration test.

**Suggerimento:**
```php
namespace FP\Publisher\Tests\Integration;

final class QueueIntegrationTest extends WP_UnitTestCase {
    public function test_full_job_lifecycle(): void {
        // Enqueue
        $job = Queue::enqueue(
            'test_channel',
            ['content' => 'test'],
            Dates::now('UTC'),
            'test-key-' . time()
        );
        
        $this->assertIsArray($job);
        $this->assertEquals('pending', $job['status']);
        
        // Claim
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertEquals('running', $claimed['status']);
        
        // Complete
        Queue::markCompleted($job['id'], 'remote-123');
        $completed = Queue::findById($job['id']);
        $this->assertEquals('completed', $completed['status']);
        $this->assertEquals('remote-123', $completed['remote_id']);
    }
    
    public function test_idempotency_across_requests(): void {
        $key = 'idempotent-' . time();
        
        $job1 = Queue::enqueue('test', [], Dates::now('UTC'), $key);
        $job2 = Queue::enqueue('test', [], Dates::now('UTC'), $key);
        
        $this->assertEquals($job1['id'], $job2['id']);
        
        // Verificare che solo 1 record in DB
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}fp_pub_jobs WHERE idempotency_key = %s",
            $key
        ));
        $this->assertEquals(1, $count);
    }
}
```

---

#### 10.2 Load Testing

**Suggerimento:**
```php
// Script per load testing
namespace FP\Publisher\Tests\Load;

final class QueueLoadTest {
    public static function run(int $jobCount = 10000): void {
        $start = microtime(true);
        $channels = ['meta_facebook', 'instagram', 'tiktok', 'youtube'];
        
        WP_CLI::line("Enqueueing {$jobCount} jobs...");
        
        for ($i = 0; $i < $jobCount; $i++) {
            $channel = $channels[array_rand($channels)];
            
            try {
                Queue::enqueue(
                    $channel,
                    ['content' => "Load test job {$i}"],
                    Dates::now('UTC')->add(new DateInterval('PT' . rand(1, 3600) . 'S')),
                    "load-test-{$i}-" . time()
                );
                
                if ($i % 100 === 0) {
                    WP_CLI::line("  Progress: {$i}/{$jobCount}");
                }
            } catch (Throwable $e) {
                WP_CLI::error("Failed at job {$i}: " . $e->getMessage());
            }
        }
        
        $duration = microtime(true) - $start;
        $ratePerSec = round($jobCount / $duration, 2);
        
        WP_CLI::success("Enqueued {$jobCount} jobs in {$duration}s ({$ratePerSec} jobs/sec)");
        
        // Test claiming
        WP_CLI::line("\nTesting claiming...");
        $claimStart = microtime(true);
        $claimed = 0;
        
        for ($i = 0; $i < 100; $i++) {
            $jobs = Scheduler::getRunnableJobs(Dates::now('UTC'), 10);
            $claimed += count($jobs);
        }
        
        $claimDuration = microtime(true) - $claimStart;
        WP_CLI::success("Claimed {$claimed} jobs in {$claimDuration}s");
    }
}

// Registrare comando
WP_CLI::add_command('fp-publisher load-test', [QueueLoadTest::class, 'run']);
```

---

#### 10.3 End-to-End Tests

**Suggerimento:**
Usare Playwright o Cypress per E2E:

```javascript
// tests/e2e/plan-workflow.spec.ts
import { test, expect } from '@playwright/test';

test('create and publish plan', async ({ page }) => {
    // Login
    await page.goto('/wp-admin');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    
    // Navigare a FP Publisher
    await page.goto('/wp-admin/admin.php?page=fp-publisher');
    
    // Creare nuovo plan
    await page.click('text=New Plan');
    await page.fill('[name="title"]', 'E2E Test Plan');
    await page.fill('[name="caption"]', 'This is a test caption for E2E');
    await page.selectOption('[name="channel"]', 'meta_facebook');
    
    // Schedulare
    await page.fill('[name="scheduled_at"]', '2025-12-31 12:00:00');
    await page.click('text=Schedule');
    
    // Verificare success message
    await expect(page.locator('.notice-success')).toContainText('Plan scheduled');
    
    // Verificare che appare nel calendario
    await page.goto('/wp-admin/admin.php?page=fp-publisher&view=calendar');
    await expect(page.locator('.fp-calendar__item')).toContainText('E2E Test Plan');
});
```

---

### 🟡 **PRIORITÀ MEDIA**

#### 10.4 Mutation Testing

**Suggerimento:**
Usare Infection PHP per verificare la qualità dei test:

```bash
composer require --dev infection/infection

# Creare infection.json.dist
{
    "source": {
        "directories": [
            "src"
        ]
    },
    "logs": {
        "text": "infection.log"
    },
    "mutators": {
        "@default": true
    }
}

# Eseguire
vendor/bin/infection --min-msi=80 --min-covered-msi=90
```

---

## 📊 Riepilogo Priorità

### 🔴 **PRIORITÀ ALTA** (Implementare entro 3 mesi)

1. Circuit Breaker per API esterne
2. Caching multi-layer
3. Query optimization e indexing
4. Rate limiting API REST
5. Transazioni database
6. Event sourcing per queue
7. Health check endpoint
8. Metrics collection
9. Real-time updates (SSE)
10. Integration tests

### 🟡 **PRIORITÀ MEDIA** (Implementare entro 6-12 mesi)

1. Dependency Injection Container
2. Repository Pattern
3. Dead Letter Queue
4. Webhook system
5. GraphQL support
6. Read replicas support
7. Distributed tracing
8. Bulk operations UI
9. Export/Import configs
10. CLI commands estesi

### 🟢 **PRIORITÀ BASSA** (Nice to have)

1. Plugin hooks completi
2. Mutation testing
3. APM integration completa
4. A/B testing nativo
5. Libreria asset avanzata

---

## 🎯 Metriche di Successo

### Performance
- **Latency P95** < 200ms per API calls
- **Queue throughput** > 100 job/min
- **Database query time** < 50ms per query
- **Memory usage** < 256MB per request

### Reliability
- **Uptime** > 99.9%
- **Error rate** < 0.1%
- **Circuit breaker trip rate** < 5%
- **Job success rate** > 98%

### Security
- **Zero** SQL injection vulnerabilities
- **Zero** XSS vulnerabilities
- **CSRF** protection su tutti gli endpoint
- **Rate limit** effectiveness > 95%

### Code Quality
- **Test coverage** > 85%
- **Mutation score** > 80%
- **PHPStan level** 8 (max)
- **PHPCS** violations = 0

---

## 🚀 Roadmap di Implementazione Suggerita

### Q1 2025
- [ ] Circuit Breaker
- [ ] Caching Layer
- [ ] Rate Limiting
- [ ] Health Checks
- [ ] Metrics Collection

### Q2 2025
- [ ] Event Sourcing
- [ ] Transazioni DB
- [ ] Query Optimization
- [ ] Integration Tests
- [ ] Dead Letter Queue

### Q3 2025
- [ ] Repository Pattern
- [ ] Dependency Injection
- [ ] Webhook System
- [ ] Real-time Updates
- [ ] Distributed Tracing

### Q4 2025
- [ ] GraphQL Support
- [ ] CLI Commands
- [ ] Bulk Operations
- [ ] API Documentation
- [ ] Load Testing Suite

---

## 📝 Note Finali

Questo documento rappresenta una **roadmap ambiziosa ma realizzabile** per portare FP Digital Publisher da un plugin già solido a una **piattaforma enterprise-grade**. 

### Punti di Forza Attuali
✅ Architettura pulita con separazione delle responsabilità  
✅ Buona copertura test unitari (149 test)  
✅ Sistema di logging strutturato  
✅ Gestione errori transient con retry intelligente  
✅ Encryption dei token con Sodium  
✅ Queue con idempotency e blackout windows  

### Aree di Miglioramento Principale
⚠️ Performance e caching  
⚠️ Monitoraggio e osservabilità  
⚠️ Resilienza (circuit breaker, DLQ)  
⚠️ Testing (integration, load, E2E)  
⚠️ Developer experience  

---

**Documento preparato da:** Analisi automatica AI  
**Data:** 2025-10-05  
**Versione:** 1.0
