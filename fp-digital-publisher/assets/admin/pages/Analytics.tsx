import React from 'react';
import { useClient } from '../hooks/useClient';

export const Analytics: React.FC = () => {
  const { currentClient } = useClient();

  return (
    <div className="fp-analytics">
      <h1>📈 Analytics</h1>
      
      <div className="analytics-placeholder">
        <div className="placeholder-icon">📊</div>
        <h2>Analytics in arrivo!</h2>
        <p>
          Questa sezione mostrerà statistiche dettagliate per{' '}
          {currentClient ? currentClient.name : 'i tuoi clienti'}:
        </p>
        <ul>
          <li>📊 Performance post per canale</li>
          <li>📈 Crescita follower nel tempo</li>
          <li>💬 Engagement rate</li>
          <li>🎯 Migliori orari di pubblicazione</li>
          <li>📱 Confronto canali</li>
          <li>📅 Report mensili</li>
        </ul>
        <button className="button button-primary">
          🚀 Richiedi Demo Analytics
        </button>
      </div>
    </div>
  );
};
