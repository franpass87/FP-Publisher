import { createElement, useState, useEffect } from '@wordpress/element';
import { useClient } from '../hooks/useClient';

interface Account {
  id: number;
  channel: string;
  account_name: string;
  status: string;
  connected_at: string;
}

export const SocialAccounts = () => {
  const { selectedClientId, currentClient } = useClient();
  const [accounts, setAccounts] = useState<Account[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (selectedClientId) {
      fetchAccounts();
    }
  }, [selectedClientId]);

  const fetchAccounts = async () => {
    if (!selectedClientId) return;
    
    setLoading(true);
    try {
      const response = await fetch(`/wp-json/fp-publisher/v1/clients/${selectedClientId}/accounts`);
      const data = await response.json();
      setAccounts(data.accounts || []);
    } catch (error) {
      console.error('Failed to fetch accounts:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleConnect = (channel: string) => {
    alert(`Connessione OAuth per ${channel} - Implementare flow OAuth 2.0`);
    // TODO: Implementare OAuth flow
  };

  const handleDisconnect = async (accountId: number) => {
    if (!selectedClientId) return;
    if (!confirm('Vuoi davvero disconnettere questo account?')) return;

    try {
      await fetch(`/wp-json/fp-publisher/v1/clients/${selectedClientId}/accounts/${accountId}`, {
        method: 'DELETE'
      });
      fetchAccounts();
    } catch (error) {
      console.error('Failed to disconnect account:', error);
      alert('Errore durante la disconnessione');
    }
  };

  const getChannelIcon = (channel: string) => {
    const icons: Record<string, string> = {
      meta_facebook: '📘',
      meta_instagram: '📷',
      youtube: '📹',
      tiktok: '🎵',
      google_business: '🗺️',
      wordpress_blog: '📝'
    };
    return icons[channel] || '📱';
  };

  const getChannelName = (channel: string) => {
    const names: Record<string, string> = {
      meta_facebook: 'Facebook',
      meta_instagram: 'Instagram',
      youtube: 'YouTube',
      tiktok: 'TikTok',
      google_business: 'Google Business',
      wordpress_blog: 'WordPress Blog'
    };
    return names[channel] || channel;
  };

  const availableChannels = [
    { id: 'meta_facebook', name: 'Facebook', icon: '📘', oauth: true },
    { id: 'meta_instagram', name: 'Instagram', icon: '📷', oauth: true },
    { id: 'youtube', name: 'YouTube', icon: '📹', oauth: true },
    { id: 'tiktok', name: 'TikTok', icon: '🎵', oauth: true },
    { id: 'google_business', name: 'Google Business', icon: '🗺️', oauth: true },
    { id: 'wordpress_blog', name: 'WordPress Blog', icon: '📝', oauth: false }
  ];

  const isConnected = (channelId: string) => 
    accounts.some(acc => acc.channel === channelId && acc.status === 'active');

  if (!selectedClientId) {
    return (
      <div className="fp-social-accounts">
        <div className="empty-state">
          <h2>🔌 Account Social</h2>
          <p>Seleziona un cliente dal dropdown in alto per gestire gli account social.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="fp-social-accounts">
      <div className="page-header">
        <div>
          <h1>🔌 Account Social</h1>
          {currentClient && (
            <p className="subtitle">Cliente: <strong>{currentClient.name}</strong></p>
          )}
        </div>
        <button className="button" onClick={fetchAccounts}>
          🔄 Aggiorna
        </button>
      </div>

      {loading ? (
        <div className="loading-spinner">Caricamento account...</div>
      ) : (
        <>
          {/* Connected Accounts */}
          {accounts.length > 0 && (
            <div className="section">
              <h2>✅ Account Connessi ({accounts.length})</h2>
              <div className="accounts-grid">
                {accounts.map(account => (
                  <div key={account.id} className="account-card connected">
                    <div className="account-icon">{getChannelIcon(account.channel)}</div>
                    <div className="account-info">
                      <h3>{getChannelName(account.channel)}</h3>
                      <p className="account-name">{account.account_name}</p>
                      <p className="account-meta">
                        Connesso il {new Date(account.connected_at).toLocaleDateString('it-IT')}
                      </p>
                    </div>
                    <div className="account-actions">
                      <span className="status-badge active">✓ Attivo</span>
                      <button 
                        className="button button-link-delete"
                        onClick={() => handleDisconnect(account.id)}
                      >
                        Disconnetti
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Available Channels */}
          <div className="section">
            <h2>➕ Connetti Nuovo Account</h2>
            <div className="channels-grid">
              {availableChannels.map(channel => {
                const connected = isConnected(channel.id);
                return (
                  <div key={channel.id} className={`channel-card ${connected ? 'disabled' : ''}`}>
                    <div className="channel-icon">{channel.icon}</div>
                    <h3>{channel.name}</h3>
                    {channel.oauth && (
                      <p className="channel-meta">OAuth 2.0</p>
                    )}
                    {connected ? (
                      <button className="button" disabled>
                        ✓ Connesso
                      </button>
                    ) : (
                      <button 
                        className="button button-primary"
                        onClick={() => handleConnect(channel.id)}
                      >
                        + Connetti
                      </button>
                    )}
                  </div>
                );
              })}
            </div>
          </div>

          {/* Help Section */}
          <div className="section help-section">
            <h3>ℹ️ Come Connettere Account</h3>
            <ol>
              <li>Click su "<strong>+ Connetti</strong>" per il canale desiderato</li>
              <li>Segui il flusso OAuth 2.0 (login + autorizzazione)</li>
              <li>Una volta connesso, l'account apparirà in "Account Connessi"</li>
              <li>Potrai usarlo nel Composer per pubblicare contenuti</li>
            </ol>
            
            <div className="info-box">
              <strong>💡 Nota:</strong> WordPress Blog non richiede OAuth perché pubblichi direttamente 
              sul tuo sito WordPress locale.
            </div>
          </div>
        </>
      )}
    </div>
  );
};
