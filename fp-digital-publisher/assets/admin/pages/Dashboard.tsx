import { createElement, useState, useEffect, useCallback } from '@wordpress/element';
import { useClient } from '../hooks/useClient';

interface DashboardStats {
  scheduled: number;
  published_today: number;
  published_month: number;
  failed: number;
  total_accounts: number;
}

interface RecentJob {
  id: number;
  channel: string;
  status: string;
  run_at: string;
  payload: any;
}

export const Dashboard = () => {
  const { selectedClientId, currentClient } = useClient();
  const [stats, setStats] = useState<DashboardStats>({
    scheduled: 0,
    published_today: 0,
    published_month: 0,
    failed: 0,
    total_accounts: 0,
  });
  const [recentJobs, setRecentJobs] = useState<RecentJob[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchDashboardData = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (selectedClientId) {
        params.set('client_id', selectedClientId.toString());
      }

      // Fetch stats
      const [scheduled, completed, failed, accounts] = await Promise.all([
        fetch(`/wp-json/fp-publisher/v1/jobs?${params}&status=pending`).then(r => r.json()),
        fetch(`/wp-json/fp-publisher/v1/jobs?${params}&status=completed`).then(r => r.json()),
        fetch(`/wp-json/fp-publisher/v1/jobs?${params}&status=failed`).then(r => r.json()),
        selectedClientId 
          ? fetch(`/wp-json/fp-publisher/v1/clients/${selectedClientId}/accounts`).then(r => r.json())
          : Promise.resolve({ total: 0 }),
      ]);

      setStats({
        scheduled: scheduled.total || 0,
        published_today: 0, // TODO: filter by today
        published_month: completed.total || 0,
        failed: failed.total || 0,
        total_accounts: accounts.total || 0,
      });

      // Recent jobs
      const recent = await fetch(`/wp-json/fp-publisher/v1/jobs?${params}&limit=10`).then(r => r.json());
      setRecentJobs(recent.jobs || []);
    } catch (error) {
      console.error('Failed to fetch dashboard data:', error);
    } finally {
      setLoading(false);
    }
  }, [selectedClientId]);

  useEffect(() => {
    fetchDashboardData();
  }, [fetchDashboardData]);

  const getChannelIcon = (channel: string): string => {
    const icons: Record<string, string> = {
      'meta_facebook': '📘',
      'meta_instagram': '📷',
      'youtube': '📹',
      'tiktok': '🎵',
      'google_business': '🗺️',
      'wordpress_blog': '📝',
    };
    return icons[channel] || '📱';
  };

  const getStatusBadge = (status: string) => {
    const badges: Record<string, { label: string; class: string; icon: string }> = {
      pending: { label: 'Schedulato', class: 'status-pending', icon: '⏱️' },
      running: { label: 'In corso', class: 'status-running', icon: '▶️' },
      completed: { label: 'Pubblicato', class: 'status-completed', icon: '✅' },
      failed: { label: 'Fallito', class: 'status-failed', icon: '❌' },
    };
    return badges[status] || badges.pending;
  };

  const formatDate = (dateStr: string): string => {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return 'Adesso';
    if (diffMins < 60) return `${diffMins} min fa`;
    if (diffHours < 24) return `${diffHours} ore fa`;
    if (diffDays < 7) return `${diffDays} giorni fa`;
    
    return date.toLocaleDateString('it-IT', { 
      day: '2-digit', 
      month: 'short',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  if (loading) {
    return (
      <div className="fp-dashboard loading">
        <div className="loading-spinner">Caricamento dashboard...</div>
      </div>
    );
  }

  return (
    <div className="fp-dashboard">
      {/* Header */}
      <div className="dashboard-header">
        <div>
          <h1>📊 Dashboard</h1>
          {currentClient && (
            <p className="client-subtitle">
              Cliente: <strong>{currentClient.name}</strong>
            </p>
          )}
        </div>
        <div className="header-actions">
          <button 
            className="button button-primary"
            onClick={() => window.location.href = '/wp-admin/admin.php?page=fp-publisher-composer'}
          >
            ✏️ Nuovo Post
          </button>
          <button 
            className="button"
            onClick={fetchDashboardData}
          >
            🔄 Aggiorna
          </button>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="stats-grid">
        <div className="stat-card scheduled">
          <div className="stat-icon">⏱️</div>
          <div className="stat-content">
            <div className="stat-value">{stats.scheduled}</div>
            <div className="stat-label">Schedulati</div>
          </div>
        </div>

        <div className="stat-card published">
          <div className="stat-icon">✅</div>
          <div className="stat-content">
            <div className="stat-value">{stats.published_month}</div>
            <div className="stat-label">Pubblicati (mese)</div>
          </div>
        </div>

        <div className="stat-card failed">
          <div className="stat-icon">❌</div>
          <div className="stat-content">
            <div className="stat-value">{stats.failed}</div>
            <div className="stat-label">Falliti</div>
          </div>
        </div>

        <div className="stat-card accounts">
          <div className="stat-icon">📱</div>
          <div className="stat-content">
            <div className="stat-value">{stats.total_accounts}</div>
            <div className="stat-label">Account Connessi</div>
          </div>
        </div>
      </div>

      {/* Quick Actions */}
      <div className="quick-actions">
        <h2>⚡ Azioni Rapide</h2>
        <div className="actions-grid">
          <button 
            className="action-card"
            onClick={() => window.location.href = '/wp-admin/admin.php?page=fp-publisher-composer'}
          >
            <span className="action-icon">✏️</span>
            <span className="action-label">Componi Post</span>
          </button>
          
          <button 
            className="action-card"
            onClick={() => window.location.href = '/wp-admin/admin.php?page=fp-publisher-calendar'}
          >
            <span className="action-icon">📅</span>
            <span className="action-label">Calendario</span>
          </button>
          
          <button 
            className="action-card"
            onClick={() => window.location.href = '/wp-admin/admin.php?page=fp-publisher-library'}
          >
            <span className="action-icon">🖼️</span>
            <span className="action-label">Libreria Media</span>
          </button>
          
          <button 
            className="action-card"
            onClick={() => window.location.href = '/wp-admin/admin.php?page=fp-publisher-analytics'}
          >
            <span className="action-icon">📈</span>
            <span className="action-label">Analytics</span>
          </button>
        </div>
      </div>

      {/* Recent Activity */}
      <div className="recent-activity">
        <div className="section-header">
          <h2>🕒 Attività Recente</h2>
          <a href="/wp-admin/admin.php?page=fp-publisher-jobs" className="view-all">
            Vedi tutti →
          </a>
        </div>

        {recentJobs.length === 0 ? (
          <div className="empty-state">
            <p>Nessuna attività recente</p>
            <button 
              className="button button-primary"
              onClick={() => window.location.href = '/wp-admin/admin.php?page=fp-publisher-composer'}
            >
              Crea il tuo primo post
            </button>
          </div>
        ) : (
          <div className="activity-list">
            {recentJobs.map(job => {
              const status = getStatusBadge(job.status);
              return (
                <div key={job.id} className="activity-item">
                  <div className="activity-icon">
                    {getChannelIcon(job.channel)}
                  </div>
                  <div className="activity-content">
                    <div className="activity-title">
                      {job.payload?.message?.substring(0, 60) || 'Post senza titolo'}
                      {job.payload?.message?.length > 60 && '...'}
                    </div>
                    <div className="activity-meta">
                      <span className="channel-name">{job.channel}</span>
                      <span className="separator">•</span>
                      <span className="time">{formatDate(job.run_at)}</span>
                    </div>
                  </div>
                  <div className="activity-status">
                    <span className={`status-badge ${status.class}`}>
                      {status.icon} {status.label}
                    </span>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Client Limits (if selected) */}
      {currentClient && (
        <div className="client-limits">
          <h2>📊 Limiti Piano</h2>
          <div className="limits-grid">
            <div className="limit-item">
              <span className="limit-label">Canali massimi:</span>
              <span className="limit-value">
                {stats.total_accounts} / {currentClient.limits.max_channels === Number.MAX_SAFE_INTEGER 
                  ? '∞' 
                  : currentClient.limits.max_channels}
              </span>
            </div>
            <div className="limit-item">
              <span className="limit-label">Post mensili:</span>
              <span className="limit-value">
                {stats.published_month} / {currentClient.limits.max_posts_monthly === Number.MAX_SAFE_INTEGER 
                  ? '∞' 
                  : currentClient.limits.max_posts_monthly}
              </span>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
