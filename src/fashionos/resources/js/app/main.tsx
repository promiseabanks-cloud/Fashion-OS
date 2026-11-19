import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import '../../css/app.css';
import { App } from './App';

const container = document.getElementById('fashionos-root');

if (container) {
  const config = window.FashionOS;

  if (!config) {
    container.innerHTML =
      '<div class="fashionos-boot"><p class="fashionos-boot__wordmark">FashionOS</p><p class="fashionos-boot__text">The application could not start because its configuration was not provided. Reload the page or contact your administrator.</p></div>';
  } else {
    container.classList.add('fashionos-shell--hydrated');
    createRoot(container).render(
      <StrictMode>
        <App config={config} />
      </StrictMode>
    );
  }
}
