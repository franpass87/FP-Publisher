import { createElement, useState, useEffect, useCallback } from '@wordpress/element';

interface Client {
  id: number;
  name: string;
  slug: string;
  logo_url?: string;
  website?: string;
  industry?: string;
  timezone: string;
  color: string;
  status: string;
  billing_plan: string;
  limits: {
    max_channels: number;
    max_posts_monthly: number;
    max_team_members: number;
    storage_bytes: number;
  };
  created_at: string;
}

export const ClientsManagement = () => {
  const [clients, setClients] = useState<Client[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [editingClient, setEditingClient] = useState<Client | null>(null);
  const [searchQuery, setSearchQuery] = useState('');

  const fetchClients = useCallback(async () => {
    setLoading(true);
    try {
      const response = await fetch('/wp-json/fp-publisher/v1/clients');
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      setClients(data.clients || []);
    } catch (error) {
      console.error('Failed to fetch clients:', error);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchClients();
  }, [fetchClients]);

  const handleAddClient = () => {
    setEditingClient(null);
    setShowModal(true);
  };

  const handleEditClient = (client: Client) => {
    setEditingClient(client);
    setShowModal(true);
  };

  const handleDeleteClient = async (client: Client) => {
    if (!confirm(`Sei sicuro di voler eliminare il cliente "${client.name}"? Questa azione non può essere annullata.`)) {
      return;
    }

    try {
      const response = await fetch(`/wp-json/fp-publisher/v1/clients/${client.id}`, {
        method: 'DELETE',
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      fetchClients();
    } catch (error) {
      console.error('Failed to delete client:', error);
      alert('Errore durante l\'eliminazione del cliente');
    }
  };

  const filteredClients = clients.filter(client =>
    client.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    client.slug.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const getStatusBadge = (status: string) => {
    const badges = {
      active: { label: 'Attivo', class: 'status-active', icon: '✅' },
      paused: { label: 'Pausato', class: 'status-paused', icon: '⏸️' },
      archived: { label: 'Archiviato', class: 'status-archived', icon: '📦' },
    };
    return badges[status as keyof typeof badges] || badges.active;
  };

  const getPlanBadge = (plan: string) => {
    const badges = {
      free: { label: 'Free', class: 'plan-free' },
      basic: { label: 'Basic', class: 'plan-basic' },
      pro: { label: 'Pro', class: 'plan-pro' },
      agency: { label: 'Agency', class: 'plan-agency' },
      enterprise: { label: 'Enterprise', class: 'plan-enterprise' },
    };
    return badges[plan as keyof typeof badges] || badges.free;
  };

  return (
    <div className="fp-clients-management">
      <div className="page-header">
        <h1>👥 Gestione Clienti</h1>
        <button className="button button-primary" onClick={handleAddClient}>
          + Nuovo Cliente
        </button>
      </div>

      <div className="clients-toolbar">
        <div className="search-box">
          <input
            type="search"
            placeholder="🔍 Cerca clienti..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
          />
        </div>
        <div className="filters">
          {/* TODO: Add status and plan filters */}
        </div>
      </div>

      {loading ? (
        <div className="loading-state">Caricamento clienti...</div>
      ) : filteredClients.length === 0 ? (
        <div className="empty-state">
          <p>Nessun cliente trovato.</p>
          <button className="button button-primary" onClick={handleAddClient}>
            Crea il tuo primo cliente
          </button>
        </div>
      ) : (
        <div className="clients-grid">
          {filteredClients.map(client => {
            const statusBadge = getStatusBadge(client.status);
            const planBadge = getPlanBadge(client.billing_plan);

            return (
              <div key={client.id} className="client-card">
                <div className="client-card-header">
                  <div className="client-info">
                    {client.logo_url ? (
                      <img 
                        src={client.logo_url} 
                        alt={client.name}
                        className="client-card-logo"
                      />
                    ) : (
                      <span 
                        className="client-card-badge"
                        style={{ backgroundColor: client.color }}
                      >
                        {client.name.substring(0, 2).toUpperCase()}
                      </span>
                    )}
                    <div>
                      <h3>{client.name}</h3>
                      <span className={`status-badge ${statusBadge.class}`}>
                        {statusBadge.icon} {statusBadge.label}
                      </span>
                    </div>
                  </div>
                </div>

                <div className="client-card-body">
                  <div className="client-stat">
                    <span className="stat-label">📱 Canali:</span>
                    <span className="stat-value">{client.limits.max_channels === Number.MAX_SAFE_INTEGER ? 'Illimitati' : client.limits.max_channels}</span>
                  </div>
                  <div className="client-stat">
                    <span className="stat-label">📊 Post/mese:</span>
                    <span className="stat-value">{client.limits.max_posts_monthly === Number.MAX_SAFE_INTEGER ? 'Illimitati' : client.limits.max_posts_monthly}</span>
                  </div>
                  <div className="client-stat">
                    <span className="stat-label">👥 Team:</span>
                    <span className="stat-value">Max {client.limits.max_team_members === Number.MAX_SAFE_INTEGER ? 'Illimitati' : client.limits.max_team_members}</span>
                  </div>
                  <div className="client-stat">
                    <span className="stat-label">💰 Piano:</span>
                    <span className={`plan-badge ${planBadge.class}`}>{planBadge.label}</span>
                  </div>
                </div>

                <div className="client-card-footer">
                  <button
                    className="button button-small"
                    onClick={() => window.location.href = `/wp-admin/admin.php?page=fp-publisher&client_id=${client.id}`}
                  >
                    Dashboard
                  </button>
                  <button
                    className="button button-small"
                    onClick={() => handleEditClient(client)}
                  >
                    Modifica
                  </button>
                  <button
                    className="button button-small"
                    onClick={() => window.location.href = `/wp-admin/admin.php?page=fp-publisher-accounts&client_id=${client.id}`}
                  >
                    Account Social
                  </button>
                  <button
                    className="button button-small button-link-delete"
                    onClick={() => handleDeleteClient(client)}
                  >
                    Elimina
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {showModal && (
        <ClientModal
          client={editingClient}
          onClose={() => {
            setShowModal(false);
            setEditingClient(null);
          }}
          onSave={() => {
            setShowModal(false);
            setEditingClient(null);
            fetchClients();
          }}
        />
      )}
    </div>
  );
};

interface ClientModalProps {
  client: Client | null;
  onClose: () => void;
  onSave: () => void;
}

const ClientModal = ({ client, onClose, onSave }: ClientModalProps) => {
  const [formData, setFormData] = useState({
    name: client?.name || '',
    slug: client?.slug || '',
    logo_url: client?.logo_url || '',
    website: client?.website || '',
    industry: client?.industry || '',
    timezone: client?.timezone || 'Europe/Rome',
    color: client?.color || '#666666',
    status: client?.status || 'active',
  });
  const [saving, setSaving] = useState(false);

  // Update form data when client prop changes
  useEffect(() => {
    if (client) {
      setFormData({
        name: client.name || '',
        slug: client.slug || '',
        logo_url: client.logo_url || '',
        website: client.website || '',
        industry: client.industry || '',
        timezone: client.timezone || 'Europe/Rome',
        color: client.color || '#666666',
        status: client.status || 'active',
      });
    }
  }, [client]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);

    try {
      const url = client
        ? `/wp-json/fp-publisher/v1/clients/${client.id}`
        : '/wp-json/fp-publisher/v1/clients';

      const method = client ? 'PUT' : 'POST';

      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      onSave();
      onClose();
    } catch (error) {
      console.error('Failed to save client:', error);
      alert('Errore durante il salvataggio del cliente');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fp-modal-overlay" onClick={onClose}>
      <div className="fp-modal" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h2>{client ? '✏️ Modifica Cliente' : '➕ Nuovo Cliente'}</h2>
          <button className="close-button" onClick={onClose}>✕</button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="modal-body">
            <div className="form-group">
              <label htmlFor="name">Nome Cliente *</label>
              <input
                type="text"
                id="name"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                required
              />
            </div>

            <div className="form-group">
              <label htmlFor="slug">Slug (URL-friendly)</label>
              <input
                type="text"
                id="slug"
                value={formData.slug}
                onChange={(e) => setFormData({ ...formData, slug: e.target.value })}
                placeholder="Auto-generato dal nome"
              />
              <small>Lascia vuoto per generazione automatica</small>
            </div>

            <div className="form-group">
              <label htmlFor="logo_url">URL Logo</label>
              <input
                type="url"
                id="logo_url"
                value={formData.logo_url}
                onChange={(e) => setFormData({ ...formData, logo_url: e.target.value })}
                placeholder="https://..."
              />
            </div>

            <div className="form-group">
              <label htmlFor="website">Sito Web</label>
              <input
                type="url"
                id="website"
                value={formData.website}
                onChange={(e) => setFormData({ ...formData, website: e.target.value })}
                placeholder="https://..."
              />
            </div>

            <div className="form-row">
              <div className="form-group">
                <label htmlFor="industry">Settore</label>
                <select
                  id="industry"
                  value={formData.industry}
                  onChange={(e) => setFormData({ ...formData, industry: e.target.value })}
                >
                  <option value="">Seleziona settore</option>
                  <option value="technology">Technology</option>
                  <option value="food">Food & Beverage</option>
                  <option value="retail">Retail</option>
                  <option value="healthcare">Healthcare</option>
                  <option value="education">Education</option>
                  <option value="entertainment">Entertainment</option>
                  <option value="other">Altro</option>
                </select>
              </div>

              <div className="form-group">
                <label htmlFor="timezone">Timezone</label>
                <select
                  id="timezone"
                  value={formData.timezone}
                  onChange={(e) => setFormData({ ...formData, timezone: e.target.value })}
                >
                  <option value="UTC">UTC</option>
                  <option value="Europe/Rome">Europe/Rome</option>
                  <option value="Europe/London">Europe/London</option>
                  <option value="America/New_York">America/New_York</option>
                  <option value="America/Los_Angeles">America/Los_Angeles</option>
                </select>
              </div>
            </div>

            <div className="form-row">
              <div className="form-group">
                <label htmlFor="color">Colore Brand</label>
                <div className="color-picker">
                  <input
                    type="color"
                    id="color"
                    value={formData.color}
                    onChange={(e) => setFormData({ ...formData, color: e.target.value })}
                  />
                  <input
                    type="text"
                    value={formData.color}
                    onChange={(e) => setFormData({ ...formData, color: e.target.value })}
                    placeholder="#666666"
                    pattern="^#[0-9A-Fa-f]{6}$"
                  />
                </div>
              </div>

              <div className="form-group">
                <label htmlFor="billing_plan">Piano di Fatturazione</label>
                <select
                  id="billing_plan"
                  value={formData.billing_plan}
                  onChange={(e) => setFormData({ ...formData, billing_plan: e.target.value })}
                >
                  <option value="free">Free</option>
                  <option value="basic">Basic (€15/mese)</option>
                  <option value="pro">Pro (€29/mese)</option>
                  <option value="agency">Agency (€99/mese)</option>
                  <option value="enterprise">Enterprise (Custom)</option>
                </select>
              </div>
            </div>

            <div className="form-group">
              <label>Status</label>
              <div className="radio-group">
                <label>
                  <input
                    type="radio"
                    name="status"
                    value="active"
                    checked={formData.status === 'active'}
                    onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                  />
                  Attivo
                </label>
                <label>
                  <input
                    type="radio"
                    name="status"
                    value="paused"
                    checked={formData.status === 'paused'}
                    onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                  />
                  Pausato
                </label>
                <label>
                  <input
                    type="radio"
                    name="status"
                    value="archived"
                    checked={formData.status === 'archived'}
                    onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                  />
                  Archiviato
                </label>
              </div>
            </div>
          </div>

          <div className="modal-footer">
            <button
              type="button"
              className="button"
              onClick={onClose}
              disabled={saving}
            >
              Annulla
            </button>
            {client && (
              <button
                type="button"
                className="button button-link-delete"
                onClick={() => {
                  handleDeleteClient(client);
                  onClose();
                }}
                disabled={saving}
              >
                Elimina Cliente
              </button>
            )}
            <button
              type="submit"
              className="button button-primary"
              disabled={saving}
            >
              {saving ? 'Salvataggio...' : '💾 Salva Modifiche'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
