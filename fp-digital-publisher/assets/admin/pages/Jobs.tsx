import { createElement, useState, useEffect } from '@wordpress/element';
import { useClient } from '../hooks/useClient';

interface Job {
  id: number;
  channel: string;
  status: string;
  run_at: string;
  created_at: string;
  attempts: number;
  last_error?: string;
  payload: {
    message?: string;
  };
}

export const Jobs = () => {
  const { selectedClientId } = useClient();
  const [jobs, setJobs] = useState<Job[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<string>('all');
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const limit = 20;

  useEffect(() => {
    fetchJobs();
  }, [selectedClientId, filter, page]);

  const fetchJobs = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (selectedClientId) {
        params.set('client_id', selectedClientId.toString());
      }
      if (filter !== 'all') {
        params.set('status', filter);
      }
      params.set('limit', limit.toString());
      params.set('offset', ((page - 1) * limit).toString());

      const response = await fetch(`/wp-json/fp-publisher/v1/jobs?${params}`);
      const data = await response.json();
      setJobs(data.jobs || []);
      setTotal(data.total || 0);
    } catch (error) {
      console.error('Failed to fetch jobs:', error);
    } finally {
      setLoading(false);
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

  const getStatusBadge = (status: string) => {
    const badges: Record<string, { label: string; class: string; icon: string }> = {
      pending: { label: 'In attesa', class: 'status-pending', icon: '⏱️' },
      running: { label: 'In esecuzione', class: 'status-running', icon: '▶️' },
      completed: { label: 'Completato', class: 'status-completed', icon: '✅' },
      failed: { label: 'Fallito', class: 'status-failed', icon: '❌' }
    };
    return badges[status] || badges.pending;
  };

  const formatDate = (dateStr: string) => {
    const date = new Date(dateStr);
    return date.toLocaleString('it-IT', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const totalPages = Math.ceil(total / limit);

  return (
    <div className="fp-jobs">
      <div className="page-header">
        <div>
          <h1>📋 Cronologia Job</h1>
          <p className="subtitle">Visualizza lo stato di tutti i job di pubblicazione</p>
        </div>
        <div className="header-actions">
          <button className="button" onClick={fetchJobs}>
            🔄 Aggiorna
          </button>
        </div>
      </div>

      {/* Filters */}
      <div className="filters">
        <div className="filter-group">
          <label>Filtra per stato:</label>
          <select value={filter} onChange={(e) => { setFilter(e.target.value); setPage(1); }}>
            <option value="all">Tutti</option>
            <option value="pending">In attesa</option>
            <option value="running">In esecuzione</option>
            <option value="completed">Completati</option>
            <option value="failed">Falliti</option>
          </select>
        </div>

        <div className="stats-inline">
          <span>Totale job: <strong>{total}</strong></span>
          {totalPages > 1 && (
            <span>Pagina <strong>{page}</strong> di <strong>{totalPages}</strong></span>
          )}
        </div>
      </div>

      {loading ? (
        <div className="loading-spinner">Caricamento job...</div>
      ) : jobs.length === 0 ? (
        <div className="empty-state">
          <h2>📭 Nessun Job Trovato</h2>
          <p>Non ci sono job {filter !== 'all' ? `con stato "${filter}"` : 'in questo momento'}.</p>
          <button 
            className="button button-primary"
            onClick={() => window.location.href = '/wp-admin/admin.php?page=fp-publisher-composer'}
          >
            ✏️ Crea Nuovo Post
          </button>
        </div>
      ) : (
        <>
          {/* Jobs Table */}
          <div className="jobs-table">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Canale</th>
                  <th>Messaggio</th>
                  <th>Stato</th>
                  <th>Programmato per</th>
                  <th>Tentativi</th>
                  <th>Azioni</th>
                </tr>
              </thead>
              <tbody>
                {jobs.map(job => {
                  const badge = getStatusBadge(job.status);
                  return (
                    <tr key={job.id}>
                      <td>#{job.id}</td>
                      <td>
                        <span className="channel-badge">
                          {getChannelIcon(job.channel)} {job.channel}
                        </span>
                      </td>
                      <td className="message-cell">
                        {job.payload?.message?.substring(0, 50) || 'N/A'}
                        {job.payload?.message && job.payload.message.length > 50 && '...'}
                      </td>
                      <td>
                        <span className={`status-badge ${badge.class}`}>
                          {badge.icon} {badge.label}
                        </span>
                      </td>
                      <td>{formatDate(job.run_at)}</td>
                      <td>
                        <span className={job.attempts > 1 ? 'text-warning' : ''}>
                          {job.attempts}
                        </span>
                      </td>
                      <td>
                        <button 
                          className="button button-small"
                          onClick={() => alert(`Job #${job.id}\n\nDettagli:\n${JSON.stringify(job, null, 2)}`)}
                        >
                          👁️ Dettagli
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="pagination">
              <button 
                className="button"
                disabled={page === 1}
                onClick={() => setPage(page - 1)}
              >
                ← Precedente
              </button>
              
              <span className="page-info">
                Pagina {page} di {totalPages}
              </span>

              <button 
                className="button"
                disabled={page === totalPages}
                onClick={() => setPage(page + 1)}
              >
                Successiva →
              </button>
            </div>
          )}

          {/* Legend */}
          <div className="legend">
            <h3>Legenda Stati</h3>
            <ul>
              <li><span className="status-badge status-pending">⏱️ In attesa</span> - Job programmato, in attesa di esecuzione</li>
              <li><span className="status-badge status-running">▶️ In esecuzione</span> - Worker sta processando il job</li>
              <li><span className="status-badge status-completed">✅ Completato</span> - Pubblicato con successo</li>
              <li><span className="status-badge status-failed">❌ Fallito</span> - Errore durante la pubblicazione</li>
            </ul>
          </div>
        </>
      )}
    </div>
  );
};
