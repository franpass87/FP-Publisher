import React from 'react';

export const MediaLibrary: React.FC = () => {
  return (
    <div className="fp-media-library">
      <h1>🖼️ Libreria Media</h1>
      
      <div className="library-placeholder">
        <div className="placeholder-icon">📁</div>
        <h2>Libreria Media in arrivo!</h2>
        <p>Qui potrai gestire tutti i tuoi asset multimediali:</p>
        <ul>
          <li>📷 Immagini</li>
          <li>🎬 Video</li>
          <li>🎵 Audio</li>
          <li>📄 Documenti</li>
          <li>🏷️ Tag e organizzazione</li>
          <li>🔍 Ricerca avanzata</li>
        </ul>
        <button className="button button-primary">
          📤 Carica i tuoi primi media
        </button>
      </div>
    </div>
  );
};
