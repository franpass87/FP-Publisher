import { createElement, useState, useEffect, useRef } from '@wordpress/element';

interface Client {
  id: number;
  name: string;
  slug: string;
  logo_url?: string;
  color: string;
  status: string;
}

interface ClientSelectorProps {
  onClientChange?: (clientId: number | null) => void;
}

export const ClientSelector: React.FC<ClientSelectorProps> = ({ onClientChange }) => {
  const [clients, setClients] = useState<Client[]>([]);
  const [selectedClientId, setSelectedClientId] = useState<number | null>(() => {
    const saved = localStorage.getItem('fp_selected_client');
    return saved ? parseInt(saved) : null;
  });
  const [isOpen, setIsOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    fetchClients();
  }, []);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const fetchClients = async () => {
    try {
      const response = await fetch('/wp-json/fp-publisher/v1/clients');
      const data = await response.json();
      setClients(data.clients || []);
    } catch (error) {
      console.error('Failed to fetch clients:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleClientSelect = (clientId: number | null) => {
    setSelectedClientId(clientId);
    setIsOpen(false);
    
    if (clientId) {
      localStorage.setItem('fp_selected_client', clientId.toString());
    } else {
      localStorage.removeItem('fp_selected_client');
    }

    if (onClientChange) {
      onClientChange(clientId);
    }

    // Trigger page reload to update all data
    window.location.reload();
  };

  const selectedClient = clients.find(c => c.id === selectedClientId);

  if (loading) {
    return <div className="fp-client-selector loading">Caricamento...</div>;
  }

  return (
    <div className="fp-client-selector" ref={dropdownRef}>
      <button
        className="client-selector-button"
        onClick={() => setIsOpen(!isOpen)}
        aria-expanded={isOpen}
      >
        <div className="selected-client">
          {selectedClient ? (
            <>
              {selectedClient.logo_url ? (
                <img 
                  src={selectedClient.logo_url} 
                  alt={selectedClient.name}
                  className="client-logo"
                />
              ) : (
                <span 
                  className="client-badge"
                  style={{ backgroundColor: selectedClient.color }}
                >
                  {selectedClient.name.substring(0, 2).toUpperCase()}
                </span>
              )}
              <span className="client-name">{selectedClient.name}</span>
            </>
          ) : (
            <>
              <span className="client-badge" style={{ backgroundColor: '#666' }}>
                ALL
              </span>
              <span className="client-name">Tutti i clienti</span>
            </>
          )}
          <span className="dropdown-icon">{isOpen ? '▲' : '▼'}</span>
        </div>
      </button>

      {isOpen && (
        <div className="client-dropdown">
          <div
            className={`dropdown-item ${!selectedClientId ? 'active' : ''}`}
            onClick={() => handleClientSelect(null)}
          >
            <span className="client-badge" style={{ backgroundColor: '#666' }}>
              ALL
            </span>
            <span>Tutti i clienti</span>
            {!selectedClientId && <span className="check-icon">✓</span>}
          </div>

          <div className="dropdown-divider" />

          {clients
            .filter(c => c.status === 'active')
            .map(client => (
              <div
                key={client.id}
                className={`dropdown-item ${selectedClientId === client.id ? 'active' : ''}`}
                onClick={() => handleClientSelect(client.id)}
              >
                {client.logo_url ? (
                  <img 
                    src={client.logo_url} 
                    alt={client.name}
                    className="client-avatar"
                  />
                ) : (
                  <span 
                    className="client-badge"
                    style={{ backgroundColor: client.color }}
                  >
                    {client.name.substring(0, 2).toUpperCase()}
                  </span>
                )}
                <span>{client.name}</span>
                {selectedClientId === client.id && <span className="check-icon">✓</span>}
              </div>
            ))}

          <div className="dropdown-divider" />

          <div
            className="dropdown-item action-item"
            onClick={() => {
              setIsOpen(false);
              window.location.href = '/wp-admin/admin.php?page=fp-publisher-clients';
            }}
          >
            <span className="icon">⚙️</span>
            <span>Gestisci Clienti</span>
          </div>
        </div>
      )}
    </div>
  );
};
