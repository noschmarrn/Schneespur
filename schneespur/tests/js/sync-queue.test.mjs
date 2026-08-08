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

test('markFailed takes an entry out of pending without deleting it', async () => {
    const q = await new SyncQueue().init();
    const id = await q.addRequest({ url: '/x', method: 'POST', data: { a: 1 } });

    await q.markFailed(id, 'max_retries');

    assert.equal((await q.getPending()).length, 0);
    assert.equal(await q.getCount(), 0);

    const failed = await q.getFailed();
    assert.equal(failed.length, 1);
    assert.equal(failed[0].url, '/x');
    assert.equal(failed[0].failedReason, 'max_retries');
});

test('failedCount counts only failed entries', async () => {
    const q = await new SyncQueue().init();
    const failedId = await q.addRequest({ url: '/a', method: 'POST', data: {} });
    await q.addRequest({ url: '/b', method: 'POST', data: {} });

    await q.markFailed(failedId, 'max_retries');

    assert.equal(await q.failedCount(), 1);
    assert.equal(await q.getCount(), 1);
});

test('retryFailed puts entries back in the queue with a clean retry count', async () => {
    const q = await new SyncQueue().init();
    const id = await q.addRequest({ url: '/x', method: 'POST', data: {} });

    const entry = await q.db.get('pending_requests', id);
    entry.retryCount = 3;
    await q.db.put('pending_requests', entry);

    await q.markFailed(id, 'max_retries');
    const moved = await q.retryFailed();

    assert.equal(moved, 1);
    assert.equal(await q.failedCount(), 0);

    const pending = await q.getPending();
    assert.equal(pending.length, 1);
    assert.equal(pending[0].retryCount, 0);
});

// A failed entry must not be swept by the hourly cleanup — that would be the
// deletion this whole change is removing, just an hour later.
test('removeSynced leaves failed entries alone', async () => {
    const q = await new SyncQueue().init();
    const id = await q.addRequest({ url: '/x', method: 'POST', data: {} });

    // markFailed() never sets syncedAt, so without this the one-hour guard
    // in removeSynced() would skip the entry regardless of whether it reads
    // the SYNC_DONE or SYNC_FAILED index — the test would pass for the wrong
    // reason. Backdating syncedAt past the guard forces the pass to come
    // from the index filter (by_synced === SYNC_FAILED), which is the thing
    // this test actually exists to check.
    const entry = await q.db.get('pending_requests', id);
    entry.syncedAt = Date.now() - 2 * 60 * 60 * 1000;
    await q.db.put('pending_requests', entry);

    await q.markFailed(id, 'max_retries');
    await q.removeSynced();

    assert.equal(await q.failedCount(), 1);
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
