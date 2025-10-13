import React from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './App';
import './styles/app.css';

const container = document.getElementById('fp-publisher-app');
if (container) {
  const root = createRoot(container);
  root.render(<App />);
}
