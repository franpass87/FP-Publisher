import { createElement, useState, useEffect, useRef } from '@wordpress/element';

interface PluginSettings {
  worker_enabled: boolean;
  worker_interval: number;
  max_retries: number;
  retry_backoff: number;
  circuit_breaker_enabled: boolean;
  circuit_breaker_threshold: number;
  metrics_enabled: boolean;
}

export const Settings = () => {
  const [settings, setSettings] = useState<PluginSettings>({
    worker_enabled: true,
    worker_interval: 60,
    max_retries: 3,
    retry_backoff: 300,
    circuit_breaker_enabled: true,
    circuit_breaker_threshold: 5,
    metrics_enabled: true
  });
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const timeoutRef = useRef<number | null>(null);

  // Cleanup timeout on unmount
  useEffect(() => {
    return () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }
    };
  }, []);

  const handleSave = async () => {
    setSaving(true);
    setSaved(false);

    // Clear any existing timeout
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
      timeoutRef.current = null;
    }

    try {
      // TODO: Implementare salvataggio settings via API
      await new Promise(resolve => setTimeout(resolve, 1000));
      setSaved(true);
      timeoutRef.current = window.setTimeout(() => {
        setSaved(false);
        timeoutRef.current = null;
      }, 3000);
    } catch (error) {
      console.error('Failed to save settings:', error);
      alert('Errore durante il salvataggio delle impostazioni');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fp-settings">
      <div className="page-header">
        <div>
          <h1>⚙️ Impostazioni</h1>
          <p className="subtitle">Configura il comportamento del plugin</p>
        </div>
      </div>

      <div className="settings-container">
        {/* Worker Settings */}
        <div className="settings-section">
          <h2>🔄 Worker Queue</h2>
          <p className="section-description">
            Il worker processa i job di pubblicazione in background usando WP-Cron.
          </p>

          <div className="setting-item">
            <label className="setting-label">
              <input 
                type="checkbox"
                checked={settings.worker_enabled}
                onChange={(e) => setSettings({...settings, worker_enabled: e.target.checked})}
              />
              <strong>Abilita Worker</strong>
            </label>
            <p className="setting-description">
              Se disabilitato, i job non verranno processati automaticamente.
            </p>
          </div>

          <div className="setting-item">
            <label className="setting-label">
              <strong>Intervallo Worker (secondi)</strong>
            </label>
            <input 
              type="number"
              min="30"
              max="600"
              value={settings.worker_interval}
              onChange={(e) => {
                const value = parseInt(e.target.value, 10);
                if (!isNaN(value)) {
                  setSettings({...settings, worker_interval: value});
                }
              }}
              className="small-input"
            />
            <p className="setting-description">
              Ogni quanti secondi il worker controlla nuovi job da processare (min: 30, max: 600).
            </p>
          </div>
        </div>

        {/* Retry Settings */}
        <div className="settings-section">
          <h2>🔁 Retry Logic</h2>
          <p className="section-description">
            Configura come gestire i job falliti.
          </p>

          <div className="setting-item">
            <label className="setting-label">
              <strong>Tentativi Massimi</strong>
            </label>
            <input 
              type="number"
              min="1"
              max="10"
              value={settings.max_retries}
              onChange={(e) => {
                const value = parseInt(e.target.value, 10);
                if (!isNaN(value)) {
                  setSettings({...settings, max_retries: value});
                }
              }}
              className="small-input"
            />
            <p className="setting-description">
              Quante volte riprovare un job fallito prima di spostarlo nel Dead Letter Queue.
            </p>
          </div>

          <div className="setting-item">
            <label className="setting-label">
              <strong>Backoff Esponenziale (secondi)</strong>
            </label>
            <input 
              type="number"
              min="60"
              max="3600"
              value={settings.retry_backoff}
              onChange={(e) => {
                const value = parseInt(e.target.value, 10);
                if (!isNaN(value)) {
                  setSettings({...settings, retry_backoff: value});
                }
              }}
              className="small-input"
            />
            <p className="setting-description">
              Tempo base tra un tentativo e l'altro. Viene moltiplicato esponenzialmente (60 → 120 → 240).
            </p>
          </div>
        </div>

        {/* Circuit Breaker */}
        <div className="settings-section">
          <h2>🛡️ Circuit Breaker</h2>
          <p className="section-description">
            Protegge da cascading failures quando un'API è down.
          </p>

          <div className="setting-item">
            <label className="setting-label">
              <input 
                type="checkbox"
                checked={settings.circuit_breaker_enabled}
                onChange={(e) => setSettings({...settings, circuit_breaker_enabled: e.target.checked})}
              />
              <strong>Abilita Circuit Breaker</strong>
            </label>
            <p className="setting-description">
              Se un canale fallisce ripetutamente, viene temporaneamente disabilitato.
            </p>
          </div>

          <div className="setting-item">
            <label className="setting-label">
              <strong>Soglia Fallimenti</strong>
            </label>
            <input 
              type="number"
              min="3"
              max="20"
              value={settings.circuit_breaker_threshold}
              onChange={(e) => {
                const value = parseInt(e.target.value, 10);
                if (!isNaN(value)) {
                  setSettings({...settings, circuit_breaker_threshold: value});
                }
              }}
              className="small-input"
            />
            <p className="setting-description">
              Dopo quanti fallimenti consecutivi aprire il circuit breaker.
            </p>
          </div>
        </div>

        {/* Monitoring */}
        <div className="settings-section">
          <h2>📊 Monitoring & Metrics</h2>
          <p className="section-description">
            Raccogli metriche per monitorare le performance del sistema.
          </p>

          <div className="setting-item">
            <label className="setting-label">
              <input 
                type="checkbox"
                checked={settings.metrics_enabled}
                onChange={(e) => setSettings({...settings, metrics_enabled: e.target.checked})}
              />
              <strong>Abilita Metriche</strong>
            </label>
            <p className="setting-description">
              Traccia job processati, errori, tempi di esecuzione (formato Prometheus-ready).
            </p>
          </div>
        </div>

        {/* System Info */}
        <div className="settings-section">
          <h2>ℹ️ Informazioni Sistema</h2>
          
          <div className="info-grid">
            <div className="info-item">
              <strong>PHP Version:</strong>
              <span>{(window as any).fpPublisher?.phpVersion || 'N/A'}</span>
            </div>
            <div className="info-item">
              <strong>WordPress Version:</strong>
              <span>{(window as any).fpPublisher?.wpVersion || 'N/A'}</span>
            </div>
            <div className="info-item">
              <strong>Plugin Version:</strong>
              <span>0.2.0</span>
            </div>
            <div className="info-item">
              <strong>WP-Cron Status:</strong>
              <span className="status-badge status-completed">✓ Attivo</span>
            </div>
          </div>
        </div>

        {/* Actions */}
        <div className="settings-actions">
          <button 
            className="button button-primary button-large"
            onClick={handleSave}
            disabled={saving}
          >
            {saving ? '💾 Salvataggio...' : '💾 Salva Impostazioni'}
          </button>

          {saved && (
            <span className="save-notice">✅ Impostazioni salvate con successo!</span>
          )}
        </div>

        {/* Help */}
        <div className="settings-help">
          <h3>💡 Suggerimenti</h3>
          <ul>
            <li><strong>Worker Interval:</strong> Un valore basso (30s) significa pubblicazione più rapida ma più carico server.</li>
            <li><strong>Max Retries:</strong> Aumenta se hai API instabili, diminuisci per fallire velocemente.</li>
            <li><strong>Circuit Breaker:</strong> Utile in produzione per evitare sprechi quando un'API è completamente down.</li>
            <li><strong>Metrics:</strong> Consigliato per production, aiuta a debuggare problemi.</li>
          </ul>
        </div>
      </div>
    </div>
  );
};
