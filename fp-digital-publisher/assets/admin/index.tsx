import { render } from '@wordpress/element';
import { App } from './App';
import './styles/app.css';

const container = document.getElementById('fp-publisher-app');
if (container) {
  render(<App />, container);
}
