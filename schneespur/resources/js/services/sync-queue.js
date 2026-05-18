import { openDB } from 'idb';

const DB_NAME = 'schneespur_sync';
const DB_VERSION = 1;
const STORE_NAME = 'pending_requests';

export class SyncQueue {
    constructor() {
        this.db = null;
    }

    async init() {
        this.db = await openDB(DB_NAME, DB_VERSION, {
            upgrade(db) {
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    const store = db.createObjectStore(STORE_NAME, {
                        keyPath: 'id',
                        autoIncrement: true,
                    });
                    store.createIndex('by_synced', 'synced');
                    store.createIndex('by_timestamp', 'timestamp');
                }
            },
        });
        return this;
    }

    async addRequest({ url, method, data, headers }) {
        if (!this.db) throw new Error('SyncQueue not initialized — call init() first');
        return this.db.add(STORE_NAME, {
            url,
            method,
            data,
            headers: headers || {},
            timestamp: Date.now(),
            synced: false,
        });
    }

    async getPending() {
        if (!this.db) throw new Error('SyncQueue not initialized — call init() first');
        const all = await this.db.getAllFromIndex(STORE_NAME, 'by_synced', false);
        return all.sort((a, b) => a.timestamp - b.timestamp);
    }

    async markSynced(id) {
        if (!this.db) throw new Error('SyncQueue not initialized — call init() first');
        const entry = await this.db.get(STORE_NAME, id);
        if (!entry) return;
        entry.synced = true;
        entry.syncedAt = Date.now();
        await this.db.put(STORE_NAME, entry);
    }

    async removeSynced() {
        if (!this.db) throw new Error('SyncQueue not initialized — call init() first');
        const oneHourAgo = Date.now() - 60 * 60 * 1000;
        const synced = await this.db.getAllFromIndex(STORE_NAME, 'by_synced', true);
        const tx = this.db.transaction(STORE_NAME, 'readwrite');
        for (const entry of synced) {
            if (entry.syncedAt && entry.syncedAt < oneHourAgo) {
                await tx.store.delete(entry.id);
            }
        }
        await tx.done;
    }

    async getCount() {
        if (!this.db) throw new Error('SyncQueue not initialized — call init() first');
        return this.db.countFromIndex(STORE_NAME, 'by_synced', false);
    }
}
