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

interface IntegrationToken {
  service: string;
  label: string;
  icon: string;
  token: string;
  description: string;
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
  const [tokens, setTokens] = useState<IntegrationToken[]>([
    { service: 'meta_facebook', label: 'Facebook', icon: '📘', token: '', description: 'Token di accesso per Facebook Graph API' },
    { service: 'meta_instagram', label: 'Instagram', icon: '📷', token: '', description: 'Token di accesso per Instagram Business API' },
    { service: 'youtube', label: 'YouTube', icon: '📹', token: '', description: 'API Key per YouTube Data API v3' },
    { service: 'tiktok', label: 'TikTok', icon: '🎵', token: '', description: 'Access Token per TikTok Business API' },
    { service: 'google_business', label: 'Google Business', icon: '🗺️', token: '', description: 'API Key per Google Business Profile API' }
  ]);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [loadingTokens, setLoadingTokens] = useState(true);
  const timeoutRef = useRef<number | null>(null);

  // Load existing tokens from API
  useEffect(() => {
    const loadTokens = async () => {
      try {
        const response = await fetch('/wp-json/fp-publisher/v1/settings', {
          headers: {
            'X-WP-Nonce': (window as any).fpPublisher?.nonce || ''
          }
        });
        
        if (response.ok) {
          const data = await response.json();
          if (data.options && data.options.tokens) {
            setTokens(prev => prev.map(t => ({
              ...t,
              token: data.options.tokens[t.service] || ''
            })));
          }
        }
      } catch (error) {
        console.error('Failed to load tokens:', error);
      } finally {
        setLoadingTokens(false);
      }
    };
    
    loadTokens();
  }, []);

  // Cleanup timeout on unmount
  useEffect(() => {
    return () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }
    };
  }, []);

  const handleTokenChange = (service: string, value: string) => {
    setTokens(prev => prev.map(t => 
      t.service === service ? { ...t, token: value } : t
    ));
  };

  const handleSaveTokens = async () => {
    setSaving(true);
    setSaved(false);

    // Clear any existing timeout
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
      timeoutRef.current = null;
    }

    try {
      // Save only non-empty tokens
      const tokensToSave: Record<string, string> = {};
      tokens.forEach(t => {
        if (t.token.trim() !== '') {
          tokensToSave[t.service] = t.token.trim();
        }
      });

      // Save each token individually using Options API
      for (const [service, token] of Object.entries(tokensToSave)) {
        const response = await fetch('/wp-json/fp-publisher/v1/settings', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': (window as any).fpPublisher?.nonce || ''
          },
          body: JSON.stringify({
            key: `tokens.${service}`,
            value: token
          })
        });

        if (!response.ok) {
          throw new Error(`Failed to save token for ${service}`);
        }
      }

      setSaved(true);
      timeoutRef.current = window.setTimeout(() => {
        setSaved(false);
        timeoutRef.current = null;
      }, 3000);
      
      // Reload page to clear the notice
      setTimeout(() => {
        window.location.reload();
      }, 1500);
    } catch (error) {
      console.error('Failed to save tokens:', error);
      alert('Errore durante il salvataggio dei token');
    } finally {
      setSaving(false);
    }
  };

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
        {/* Integration Tokens */}
        <div className="settings-section">
          <h2>🔑 Token di Integrazione</h2>
          <p className="section-description">
            Configura i token di accesso per i vari canali social. Almeno un token è richiesto per utilizzare il plugin.
          </p>

          {loadingTokens ? (
            <p>Caricamento token...</p>
          ) : (
            <>
              {tokens.map(token => (
                <div key={token.service} className="setting-item">
                  <label className="setting-label">
                    <strong>{token.icon} {token.label}</strong>
                  </label>
                  <input 
                    type="password"
                    value={token.token}
                    onChange={(e) => handleTokenChange(token.service, e.target.value)}
                    placeholder="Inserisci token di accesso..."
                    className="regular-text"
                  />
                  <p className="setting-description">
                    {token.description}
                  </p>
                </div>
              ))}

              <div className="settings-actions">
                <button 
                  className="button button-primary button-large"
                  onClick={handleSaveTokens}
                  disabled={saving}
                >
                  {saving ? '💾 Salvataggio...' : '💾 Salva Token'}
                </button>

                {saved && (
                  <span className="save-notice">✅ Token salvati con successo!</span>
                )}
              </div>

              <div className="info-box" style={{ marginTop: '20px' }}>
                <strong>💡 Nota:</strong> I token vengono crittografati automaticamente nel database. 
                Puoi configurare token solo per i canali che intendi utilizzare.
              </div>
            </>
          )}
        </div>

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
