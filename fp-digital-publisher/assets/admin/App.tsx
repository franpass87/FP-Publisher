import { createElement } from '@wordpress/element';
import { Dashboard } from './pages/Dashboard';
import { Composer } from './pages/Composer';
import { Calendar } from './pages/Calendar';
import { Analytics } from './pages/Analytics';
import { MediaLibrary } from './pages/MediaLibrary';
import { ClientsManagement } from './pages/ClientsManagement';
import { ClientSelector } from './components/ClientSelector';

// Import CSS
import './pages/Dashboard.css';
import './pages/Composer.css';
import './pages/Calendar.css';
import './pages/ClientsManagement.css';
import './pages/common.css';
import './components/ClientSelector.css';

export const App: React.FC = () => {
  // Get current page from URL params
  const urlParams = new URLSearchParams(window.location.search);
  const page = urlParams.get('page') || 'fp-publisher';

  const renderPage = () => {
    switch (page) {
      case 'fp-publisher':
      case 'fp-publisher-dashboard':
        return <Dashboard />;
      
      case 'fp-publisher-composer':
        return <Composer />;
      
      case 'fp-publisher-calendar':
        return <Calendar />;
      
      case 'fp-publisher-analytics':
        return <Analytics />;
      
      case 'fp-publisher-library':
        return <MediaLibrary />;
      
      case 'fp-publisher-clients':
        return <ClientsManagement />;
      
      default:
        return <Dashboard />;
    }
  };

  return (
    <div className="fp-publisher-app">
      <div className="fp-app-header">
        <div className="app-logo">
          <span className="logo-icon">🚀</span>
          <span className="logo-text">FP Publisher</span>
        </div>
        <ClientSelector />
      </div>
      
      <div className="fp-app-content">
        {renderPage()}
      </div>
    </div>
  );
};
