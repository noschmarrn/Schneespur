import { openDB } from 'idb';

const DB_NAME = 'schneespur_sync';
const DB_VERSION = 2;
const STORE_NAME = 'pending_requests';

// The `synced` index carries the state. A third value needs no schema change:
// getPending() reads 0 and removeSynced() reads 1, so 2 falls out of both.
const SYNC_PENDING = 0;
const SYNC_DONE = 1;
const SYNC_FAILED = 2;

export class SyncQueue {
    constructor() {
        this.db = null;
    }

    async init() {
        this.db = await openDB(DB_NAME, DB_VERSION, {
            async upgrade(db, oldVersion, newVersion, tx) {
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    const store = db.createObjectStore(STORE_NAME, {
                        keyPath: 'id',
                        autoIncrement: true,
                    });
                    store.createIndex('by_synced', 'synced');
                    store.createIndex('by_timestamp', 'timestamp');
                }

                if (oldVersion > 0 && oldVersion < 2) {
                    // Migrate legacy boolean `synced` values to numbers (0/1) —
                    // boolean index keys are invalid and broke getAllFromIndex.
                    const store = tx.objectStore(STORE_NAME);
                    let cursor = await store.openCursor();
                    while (cursor) {
                        const value = cursor.value;
                        value.synced = value.synced ? 1 : 0;
                        await cursor.update(value);
                        cursor = await cursor.continue();
                    }
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
            synced: SYNC_PENDING,
        });
    }

    async getPending() {
        if (!this.db) throw new Error('SyncQueue not initialized — call init() first');
        const all = await this.db.getAllFromIndex(STORE_NAME, 'by_synced', SYNC_PENDING);
        return all.sort((a, b) => a.timestamp - b.timestamp);
    }

    async markSynced(id) {
        if (!this.db) throw new Error('SyncQueue not initialized — call init() first');
        const entry = await this.db.get(STORE_NAME, id);
        if (!entry) return;
        entry.synced = SYNC_DONE;
        entry.syncedAt = Date.now();
        await this.db.put(STORE_NAME, entry);
    }

    async removeSynced() {
        if (!this.db) throw new Error('SyncQueue not initialized — call init() first');
        const oneHourAgo = Date.now() - 60 * 60 * 1000;
        const synced = await this.db.getAllFromIndex(STORE_NAME, 'by_synced', SYNC_DONE);
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
        return this.db.countFromIndex(STORE_NAME, 'by_synced', SYNC_PENDING);
    }

    async markFailed(id, reason) {
        if (!this.db) throw new Error('SyncQueue not initialized — call init() first');
        const entry = await this.db.get(STORE_NAME, id);
        if (!entry) return;
        entry.synced = SYNC_FAILED;
        entry.failedAt = Date.now();
        entry.failedReason = reason;
        await this.db.put(STORE_NAME, entry);
    }

    async getFailed() {
        if (!this.db) throw new Error('SyncQueue not initialized — call init() first');
        const all = await this.db.getAllFromIndex(STORE_NAME, 'by_synced', SYNC_FAILED);
        return all.sort((a, b) => a.timestamp - b.timestamp);
    }

    async failedCount() {
        if (!this.db) throw new Error('SyncQueue not initialized — call init() first');
        return this.db.countFromIndex(STORE_NAME, 'by_synced', SYNC_FAILED);
    }

    /** @returns {Promise<number>} how many entries went back into the queue */
    async retryFailed() {
        if (!this.db) throw new Error('SyncQueue not initialized — call init() first');
        const failed = await this.getFailed();
        const tx = this.db.transaction(STORE_NAME, 'readwrite');

        for (const entry of failed) {
            entry.synced = SYNC_PENDING;
            entry.retryCount = 0;
            delete entry.failedAt;
            delete entry.failedReason;
            await tx.store.put(entry);
        }

        await tx.done;

        return failed.length;
    }
}
