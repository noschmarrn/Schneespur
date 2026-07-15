import 'fake-indexeddb/auto';
import { IDBFactory } from 'fake-indexeddb';
import { openDB } from 'idb';
import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { SyncQueue } from '../../resources/js/services/sync-queue.js';

// Each test gets a fresh IndexedDB — fake-indexeddb otherwise persists the
// store process-wide, leaking one test's entries into the next.
beforeEach(() => {
    globalThis.indexedDB = new IDBFactory();
});

test('getPending returns queued request without throwing', async () => {
    const q = await new SyncQueue().init();
    await q.addRequest({ url: '/x', method: 'POST', data: { a: 1 } });

    const pending = await q.getPending();

    assert.equal(pending.length, 1);
    assert.equal(pending[0].url, '/x');
});

test('markSynced removes entry from pending and count', async () => {
    const q = await new SyncQueue().init();
    const id = await q.addRequest({ url: '/y', method: 'POST', data: {} });

    await q.markSynced(id);

    assert.equal((await q.getPending()).length, 0);
    assert.equal(await q.getCount(), 0);
});

test('v1→v2 upgrade migrates legacy boolean synced values to numbers', async () => {
    // Seed a v1 database with legacy boolean `synced` values.
    const v1 = await openDB('schneespur_sync', 1, {
        upgrade(db) {
            const store = db.createObjectStore('pending_requests', { keyPath: 'id', autoIncrement: true });
            store.createIndex('by_synced', 'synced');
            store.createIndex('by_timestamp', 'timestamp');
        },
    });
    await v1.add('pending_requests', { url: '/pending', method: 'POST', data: {}, timestamp: 1, synced: false });
    await v1.add('pending_requests', { url: '/done', method: 'POST', data: {}, timestamp: 2, synced: true });
    v1.close();

    // Opening through SyncQueue triggers the v1→v2 migration.
    const q = await new SyncQueue().init();
    const pending = await q.getPending();

    assert.equal(pending.length, 1);
    assert.equal(pending[0].url, '/pending');
    assert.equal(await q.getCount(), 1);
});
