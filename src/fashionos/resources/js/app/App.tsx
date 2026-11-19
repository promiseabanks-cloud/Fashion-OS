import { useCallback, useEffect, useMemo, useState } from 'react';
import { createApiClient } from '../services/api';
import type { FashionOSConfig, MeResponse } from '../types';
import { Studio } from './Studio';
import { FullPageMessage } from './ui';
import { WorkspaceSetup } from './WorkspaceSetup';

const STORAGE_KEY = 'fashionos.workspace';

function readStoredWorkspace(): string | null {
  try {
    return window.localStorage.getItem(STORAGE_KEY);
  } catch {
    return null;
  }
}

function storeWorkspace(id: number | null): void {
  try {
    if (id === null) {
      window.localStorage.removeItem(STORAGE_KEY);
    } else {
      window.localStorage.setItem(STORAGE_KEY, String(id));
    }
  } catch {
    // Storage can be unavailable; the workspace header is a convenience, not security.
  }
}

type SessionState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; session: MeResponse };

export function App({ config }: { config: FashionOSConfig }) {
  const client = useMemo(() => createApiClient(config), [config]);
  const [workspaceId, setWorkspaceId] = useState<string | null>(readStoredWorkspace);
  const [session, setSession] = useState<SessionState>({ status: 'loading' });
  const [reloadKey, setReloadKey] = useState(0);

  useEffect(() => {
    let cancelled = false;

    client.setWorkspace(workspaceId);
    setSession({ status: 'loading' });

    client
      .me()
      .then((me) => {
        if (cancelled) {
          return;
        }

        if (me.activeTenant) {
          storeWorkspace(me.activeTenant.tenant.id);
        } else if (me.workspaceError === 'workspace_not_found' && workspaceId !== null) {
          storeWorkspace(null);
          setWorkspaceId(null);
          return;
        }

        setSession({ status: 'ready', session: me });
      })
      .catch((error: unknown) => {
        if (!cancelled) {
          setSession({
            status: 'error',
            message: error instanceof Error ? error.message : 'The API could not be reached.',
          });
        }
      });

    return () => {
      cancelled = true;
    };
  }, [client, workspaceId, reloadKey]);

  const refresh = useCallback(() => setReloadKey((key) => key + 1), []);

  const selectWorkspace = useCallback((id: number) => {
    storeWorkspace(id);
    setWorkspaceId(String(id));
  }, []);

  if (session.status === 'loading') {
    return (
      <FullPageMessage
        eyebrow="FashionOS"
        title="Opening your studio"
        detail="Checking your workspaces and permissions."
      />
    );
  }

  if (session.status === 'error') {
    return (
      <FullPageMessage
        eyebrow="FashionOS"
        title="We could not open your studio"
        detail={session.message}
        action={
          <button className="fashionos-btn fashionos-btn--primary" type="button" onClick={refresh}>
            Try again
          </button>
        }
      />
    );
  }

  if (!session.session.activeTenant) {
    return <WorkspaceSetup client={client} session={session.session} onReady={selectWorkspace} />;
  }

  return (
    <Studio
      client={client}
      session={session.session}
      logoutUrl={config.logoutUrl}
      onSelectWorkspace={selectWorkspace}
      onRefresh={refresh}
    />
  );
}
