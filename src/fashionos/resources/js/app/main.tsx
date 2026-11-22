import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import '../../css/app.css';
import { PortalApp } from '../portal/PortalApp';
import { App } from './App';

/**
 * Query parameter the portal link arrives with. It is read once and removed
 * from the address bar so the token does not linger in history or get copied
 * into a shared link.
 */
const PORTAL_PARAM = 'fos_portal';

function stripPortalToken(): void {
  try {
    const url = new URL(window.location.href);

    if (!url.searchParams.has(PORTAL_PARAM)) {
      return;
    }

    url.searchParams.delete(PORTAL_PARAM);
    window.history.replaceState(null, '', `${url.pathname}${url.search}${url.hash}`);
  } catch {
    // History access can be restricted; the token is only ever sent as a header.
  }
}

const container = document.getElementById('fashionos-root');

if (container) {
  const config = window.FashionOS;
  const queryToken = new URLSearchParams(window.location.search).get(PORTAL_PARAM) ?? '';
  const portalToken = config?.portalToken || queryToken;

  if (!config) {
    container.innerHTML =
      '<div class="fashionos-boot"><p class="fashionos-boot__wordmark">FashionOS</p><p class="fashionos-boot__text">The application could not start because its configuration was not provided. Reload the page or contact your administrator.</p></div>';
  } else if (portalToken) {
    if (queryToken) {
      stripPortalToken();
    }

    container.classList.add('fashionos-shell--hydrated');
    createRoot(container).render(
      <StrictMode>
        <PortalApp restUrl={config.restUrl} token={portalToken} />
      </StrictMode>
    );
  } else {
    container.classList.add('fashionos-shell--hydrated');
    createRoot(container).render(
      <StrictMode>
        <App config={config} />
      </StrictMode>
    );
  }
}
