import { createElement, useState, useEffect } from '@wordpress/element';
import { useClient } from '../hooks/useClient';

interface Channel {
  id: string;
  name: string;
  icon: string;
  connected: boolean;
  accountName?: string;
}

interface MediaFile {
  url: string;
  type: 'image' | 'video';
  thumbnail?: string;
  duration?: number;
}

export const Composer = () => {
  const { selectedClientId, currentClient } = useClient();
  const [message, setMessage] = useState('');
  const [selectedChannels, setSelectedChannels] = useState<string[]>([]);
  const [availableChannels, setAvailableChannels] = useState<Channel[]>([]);
  const [media, setMedia] = useState<MediaFile[]>([]);
  const [scheduledDate, setScheduledDate] = useState('');
  const [scheduledTime, setScheduledTime] = useState('');
  const [publishing, setPublishing] = useState(false);

  useEffect(() => {
    if (selectedClientId) {
      fetchConnectedAccounts();
    }
  }, [selectedClientId]);

  // Cleanup object URLs to prevent memory leaks
  useEffect(() => {
    return () => {
      media.forEach(item => {
        if (item.url.startsWith('blob:')) {
          URL.revokeObjectURL(item.url);
        }
      });
    };
  }, [media]);

  const fetchConnectedAccounts = async () => {
    if (!selectedClientId) return;

    try {
      const response = await fetch(`/wp-json/fp-publisher/v1/clients/${selectedClientId}/accounts`);
      const data = await response.json();

      const channels: Channel[] = [
        { id: 'meta_facebook', name: 'Facebook', icon: '📘', connected: false },
        { id: 'meta_instagram', name: 'Instagram', icon: '📷', connected: false },
        { id: 'youtube', name: 'YouTube', icon: '📹', connected: false },
        { id: 'tiktok', name: 'TikTok', icon: '🎵', connected: false },
        { id: 'google_business', name: 'Google Business', icon: '🗺️', connected: false },
        { id: 'wordpress_blog', name: 'WordPress', icon: '📝', connected: true },
      ];

      // Mark connected channels
      data.accounts?.forEach((account: any) => {
        const channel = channels.find(c => c.id === account.channel);
        if (channel) {
          channel.connected = true;
          channel.accountName = account.account_name;
        }
      });

      setAvailableChannels(channels);
    } catch (error) {
      console.error('Failed to fetch accounts:', error);
    }
  };

  const toggleChannel = (channelId: string) => {
    setSelectedChannels(prev =>
      prev.includes(channelId)
        ? prev.filter(id => id !== channelId)
        : [...prev, channelId]
    );
  };

  const handleMediaUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (!files) return;

    Array.from(files).forEach(file => {
      const url = URL.createObjectURL(file);
      const type = file.type.startsWith('image/') ? 'image' : 'video';

      setMedia(prev => [...prev, { url, type }]);
    });
  };

  const removeMedia = (index: number) => {
    setMedia(prev => {
      const itemToRemove = prev[index];
      if (itemToRemove && itemToRemove.url.startsWith('blob:')) {
        URL.revokeObjectURL(itemToRemove.url);
      }
      return prev.filter((_, i) => i !== index);
    });
  };

  const handlePublish = async (isDraft: boolean = false) => {
    if (!selectedClientId) {
      alert('Seleziona un cliente prima di pubblicare');
      return;
    }

    if (selectedChannels.length === 0) {
      alert('Seleziona almeno un canale');
      return;
    }

    if (!message.trim()) {
      alert('Inserisci un messaggio');
      return;
    }

    setPublishing(true);

    try {
      let publishAt = new Date().toISOString();
      
      if (scheduledDate && scheduledTime) {
        const scheduledDateTime = new Date(`${scheduledDate}T${scheduledTime}`);
        if (isNaN(scheduledDateTime.getTime())) {
          alert('❌ Data o ora non valida');
          setPublishing(false);
          return;
        }
        if (scheduledDateTime < new Date()) {
          alert('❌ La data di pubblicazione deve essere futura');
          setPublishing(false);
          return;
        }
        publishAt = scheduledDateTime.toISOString();
      }

      const response = await fetch('/wp-json/fp-publisher/v1/publish/multi-channel', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          client_id: selectedClientId,
          channels: selectedChannels,
          plan: {
            brand: currentClient?.name || 'Brand',
            channels: selectedChannels,
            slots: selectedChannels.map(channel => ({
              channel,
              scheduled_at: publishAt,
            })),
            template: {
              title: message.substring(0, 100),
              content: message,
            },
            status: isDraft ? 'draft' : 'ready',
          },
          payload: {
            message,
            media: media.map(m => ({
              source: m.url,
              type: m.type,
            })),
          },
          publish_at: publishAt,
        }),
      });

      const result = await response.json();

      if (result.success) {
        alert(`✅ ${isDraft ? 'Bozza salvata' : 'Pubblicato'} con successo su ${result.published} canali!`);
        
        // Reset form
        setMessage('');
        setSelectedChannels([]);
        setMedia([]);
        setScheduledDate('');
        setScheduledTime('');
        
        // Redirect to dashboard
        window.location.href = '/wp-admin/admin.php?page=fp-publisher';
      } else {
        alert('❌ Errore durante la pubblicazione');
      }
    } catch (error) {
      console.error('Publish failed:', error);
      alert('❌ Errore durante la pubblicazione');
    } finally {
      setPublishing(false);
    }
  };

  const characterCount = message.length;
  const maxChars = 2200; // TikTok limit

  if (!selectedClientId) {
    return (
      <div className="fp-composer">
        <div className="composer-empty">
          <h2>⚠️ Nessun Cliente Selezionato</h2>
          <p>Seleziona un cliente dal menu in alto per iniziare a comporre</p>
        </div>
      </div>
    );
  }

  return (
    <div className="fp-composer">
      <div className="composer-header">
        <h1>✏️ Componi Post</h1>
        <div className="header-actions">
          <button className="button" onClick={() => handlePublish(true)} disabled={publishing}>
            💾 Salva Bozza
          </button>
          <button 
            className="button button-primary" 
            onClick={() => handlePublish(false)}
            disabled={publishing || selectedChannels.length === 0}
          >
            {publishing ? '⏳ Pubblicando...' : '🚀 Pubblica'}
          </button>
        </div>
      </div>

      <div className="composer-layout">
        {/* Main Editor */}
        <div className="composer-main">
          {/* Message Input */}
          <div className="message-editor">
            <textarea
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              placeholder="Cosa vuoi condividere?"
              className="message-textarea"
              rows={8}
            />
            <div className="editor-footer">
              <div className="char-counter">
                <span className={characterCount > maxChars ? 'over-limit' : ''}>
                  {characterCount}
                </span>
                <span className="separator">/</span>
                <span>{maxChars}</span>
              </div>
              <div className="editor-tools">
                <button 
                  className="tool-button"
                  onClick={() => {
                    const emoji = prompt('Inserisci emoji:');
                    if (emoji) setMessage(prev => prev + emoji);
                  }}
                  title="Aggiungi emoji"
                >
                  😀
                </button>
                <button 
                  className="tool-button"
                  onClick={() => {
                    const hashtag = prompt('Inserisci hashtag (senza #):');
                    if (hashtag) setMessage(prev => prev + ' #' + hashtag);
                  }}
                  title="Aggiungi hashtag"
                >
                  #️⃣
                </button>
              </div>
            </div>
          </div>

          {/* Media Upload */}
          <div className="media-section">
            <div className="media-header">
              <h3>🖼️ Media</h3>
              <label className="button button-small">
                📤 Carica Media
                <input
                  type="file"
                  accept="image/*,video/*"
                  multiple
                  onChange={handleMediaUpload}
                  style={{ display: 'none' }}
                />
              </label>
            </div>

            {media.length > 0 && (
              <div className="media-grid">
                {media.map((file, index) => (
                  <div key={index} className="media-item">
                    {file.type === 'image' ? (
                      <img src={file.url} alt="Preview" />
                    ) : (
                      <video src={file.url} controls />
                    )}
                    <button
                      className="remove-media"
                      onClick={() => removeMedia(index)}
                    >
                      ✕
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Scheduling */}
          <div className="scheduling-section">
            <h3>⏰ Programmazione</h3>
            <div className="scheduling-inputs">
              <div className="input-group">
                <label>Data</label>
                <input
                  type="date"
                  value={scheduledDate}
                  onChange={(e) => setScheduledDate(e.target.value)}
                  min={new Date().toISOString().split('T')[0]}
                />
              </div>
              <div className="input-group">
                <label>Ora</label>
                <input
                  type="time"
                  value={scheduledTime}
                  onChange={(e) => setScheduledTime(e.target.value)}
                />
              </div>
              {scheduledDate && scheduledTime && (
                <button
                  className="button button-small"
                  onClick={() => {
                    setScheduledDate('');
                    setScheduledTime('');
                  }}
                >
                  ✕ Rimuovi
                </button>
              )}
            </div>
            {!scheduledDate && !scheduledTime && (
              <p className="scheduling-hint">
                💡 Lascia vuoto per pubblicare immediatamente
              </p>
            )}
          </div>
        </div>

        {/* Sidebar - Channel Selection */}
        <div className="composer-sidebar">
          <div className="channels-section">
            <h3>📱 Canali di Pubblicazione</h3>
            <p className="channels-hint">
              Seleziona i canali dove pubblicare questo post
            </p>

            <div className="channels-list">
              {availableChannels.map(channel => (
                <div
                  key={channel.id}
                  className={`channel-item ${!channel.connected ? 'disabled' : ''} ${selectedChannels.includes(channel.id) ? 'selected' : ''}`}
                  onClick={() => channel.connected && toggleChannel(channel.id)}
                >
                  <div className="channel-info">
                    <span className="channel-icon">{channel.icon}</span>
                    <div className="channel-details">
                      <div className="channel-name">{channel.name}</div>
                      {channel.accountName && (
                        <div className="channel-account">{channel.accountName}</div>
                      )}
                      {!channel.connected && (
                        <div className="channel-status">Non connesso</div>
                      )}
                    </div>
                  </div>
                  {channel.connected && (
                    <div className="channel-checkbox">
                      <input
                        type="checkbox"
                        checked={selectedChannels.includes(channel.id)}
                        onChange={() => {}}
                      />
                    </div>
                  )}
                </div>
              ))}
            </div>

            {selectedChannels.length > 0 && (
              <div className="selected-count">
                ✅ {selectedChannels.length} canale{selectedChannels.length > 1 ? 'i' : ''} selezionat{selectedChannels.length > 1 ? 'i' : 'o'}
              </div>
            )}

            <button
              className="button button-full"
              onClick={() => window.location.href = `/wp-admin/admin.php?page=fp-publisher-accounts&client_id=${selectedClientId}`}
            >
              ⚙️ Gestisci Account
            </button>
          </div>

          {/* Preview */}
          {message && (
            <div className="preview-section">
              <h3>👁️ Anteprima</h3>
              <div className="preview-card">
                <div className="preview-header">
                  <span className="preview-avatar">
                    {currentClient?.logo_url ? (
                      <img src={currentClient.logo_url} alt={currentClient.name} />
                    ) : (
                      <span style={{ backgroundColor: currentClient?.color || '#666' }}>
                        {currentClient?.name?.substring(0, 2).toUpperCase()}
                      </span>
                    )}
                  </span>
                  <div className="preview-author">
                    <div className="preview-name">{currentClient?.name}</div>
                    <div className="preview-time">Adesso</div>
                  </div>
                </div>
                <div className="preview-content">
                  {message}
                </div>
                {media.length > 0 && (
                  <div className="preview-media">
                    {media[0].type === 'image' ? (
                      <img src={media[0].url} alt="Preview" />
                    ) : (
                      <video src={media[0].url} />
                    )}
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
