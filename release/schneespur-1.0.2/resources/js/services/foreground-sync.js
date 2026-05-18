import { SyncQueue } from './sync-queue.js';

const MAX_RETRIES = 3;

class ForegroundSync {
    constructor() {
        this.queue = new SyncQueue();
        this.syncing = false;
        this._bound = {
            onOnline: () => this.flush(),
            onVisibilityChange: () => {
                if (document.visibilityState === 'visible') this.flush();
            },
        };
    }

    async init() {
        await this.queue.init();

        window.addEventListener('online', this._bound.onOnline);
        document.addEventListener('visibilitychange', this._bound.onVisibilityChange);

        if (navigator.onLine) {
            this.flush();
        }

        return this;
    }

    destroy() {
        window.removeEventListener('online', this._bound.onOnline);
        document.removeEventListener('visibilitychange', this._bound.onVisibilityChange);
    }

    async flush() {
        if (this.syncing || !navigator.onLine) return;
        this.syncing = true;

        const pending = await this.queue.getPending();
        if (pending.length === 0) {
            this.syncing = false;
            return;
        }

        window.dispatchEvent(new CustomEvent('sync:start', { detail: { count: pending.length } }));

        let csrfToken;
        try {
            csrfToken = await this._refreshCsrfToken();
        } catch {
            window.dispatchEvent(new CustomEvent('sync:error', { detail: { reason: 'csrf_refresh_failed' } }));
            this.syncing = false;
            return;
        }

        let syncedCount = 0;
        let failedCount = 0;

        for (const entry of pending) {
            try {
                const response = await window.axios({
                    url: entry.url,
                    method: entry.method,
                    data: entry.data,
                    headers: {
                        ...entry.headers,
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });

                if (response.status >= 200 && response.status < 300) {
                    await this.queue.markSynced(entry.id);
                    syncedCount++;
                }
            } catch {
                failedCount++;
                const retryCount = (entry.retryCount || 0) + 1;

                if (retryCount >= MAX_RETRIES) {
                    await this._removeEntry(entry.id);
                    window.dispatchEvent(new CustomEvent('sync:error', {
                        detail: { entryId: entry.id, reason: 'max_retries', url: entry.url },
                    }));
                } else {
                    await this._updateRetryCount(entry.id, retryCount);
                }
            }
        }

        await this.queue.removeSynced();

        window.dispatchEvent(new CustomEvent('sync:complete', {
            detail: { synced: syncedCount, failed: failedCount },
        }));

        this.syncing = false;
    }

    async _refreshCsrfToken() {
        const response = await window.axios.get('/driver/job/active', {
            headers: { Accept: 'text/html' },
        });
        const parser = new DOMParser();
        const doc = parser.parseFromString(response.data, 'text/html');
        const meta = doc.querySelector('meta[name="csrf-token"]');
        if (meta) {
            return meta.getAttribute('content');
        }
        const localMeta = document.querySelector('meta[name="csrf-token"]');
        if (localMeta) {
            return localMeta.getAttribute('content');
        }
        throw new Error('No CSRF token found');
    }

    async _updateRetryCount(id, retryCount) {
        const db = this.queue.db;
        if (!db) return;
        const entry = await db.get('pending_requests', id);
        if (!entry) return;
        entry.retryCount = retryCount;
        await db.put('pending_requests', entry);
    }

    async _removeEntry(id) {
        const db = this.queue.db;
        if (!db) return;
        await db.delete('pending_requests', id);
    }
}

export const foregroundSync = new ForegroundSync();
