import 'fake-indexeddb/auto';
import { IDBFactory } from 'fake-indexeddb';
import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { ForegroundSync } from '../../resources/js/services/foreground-sync.js';
import { SyncQueue } from '../../resources/js/services/sync-queue.js';

// foreground-sync.js reaches for window/document/navigator directly, not
// just IndexedDB. Plain Node has none of them (and no DOMParser), so we
// stub the minimum surface it touches. The CSRF fetch's parsed-document
// branch is made to always miss, which sends the code down the local
// <meta> fallback we do control.
function installDomStubs() {
    // Node 21+ ships its own read-only `navigator` global (there is no
    // setter on globalThis for it), so the online flag is set on the
    // existing object rather than replacing it wholesale.
    globalThis.navigator.onLine = true;
    globalThis.DOMParser = class {
        parseFromString() {
            return { querySelector: () => null };
        }
    };
    globalThis.document = {
        addEventListener() {},
        removeEventListener() {},
        querySelector: (sel) =>
            sel === 'meta[name="csrf-token"]' ? { getAttribute: () => 'test-csrf-token' } : null,
    };
    globalThis.window = {
        addEventListener() {},
        removeEventListener() {},
        dispatchEvent() {},
        axios: null,
    };
}

function axiosStub(requestHandler) {
    const fn = (config) => requestHandler(config);
    fn.get = async () => ({ data: '<html></html>' });
    return fn;
}

// Each test gets a fresh IndexedDB and fresh window/document/navigator
// stubs — otherwise one test's queued entries or dispatched-event spies
// leak into the next.
beforeEach(() => {
    globalThis.indexedDB = new IDBFactory();
    installDomStubs();
});

test('an entry that fails MAX_RETRIES times ends up in getFailed(), not deleted', async () => {
    const fs = new ForegroundSync();
    await fs.queue.init();
    await fs.queue.addRequest({ url: '/x', method: 'POST', data: {} });

    globalThis.window.axios = axiosStub(() => Promise.reject(new Error('network down')));

    // MAX_RETRIES is 3 inside foreground-sync.js; three failed runs push the
    // entry over the edge on the third.
    await fs.flush();
    await fs.flush();
    await fs.flush();

    const failed = await fs.queue.getFailed();
    assert.equal(failed.length, 1);
    assert.equal(failed[0].failedReason, 'max_retries');
    assert.equal((await fs.queue.getPending()).length, 0);
});

test('sync:complete carries the stored failed count', async () => {
    const fs = new ForegroundSync();
    await fs.queue.init();

    // An entry already failed in an earlier run...
    const failedId = await fs.queue.addRequest({ url: '/old', method: 'POST', data: {} });
    await fs.queue.markFailed(failedId, 'max_retries');

    // ...and a fresh entry that will succeed in this run.
    await fs.queue.addRequest({ url: '/new', method: 'POST', data: {} });

    globalThis.window.axios = axiosStub(() => Promise.resolve({ status: 200 }));

    let captured = null;
    globalThis.window.dispatchEvent = (event) => {
        if (event.type === 'sync:complete') captured = event.detail;
    };

    await fs.flush();

    assert.ok(captured, 'sync:complete was not dispatched');
    assert.equal(captured.synced, 1);
    assert.equal(captured.failedStored, 1);
});

// This is the fix for the display bug: a page reload creates a brand-new
// ForegroundSync/SyncQueue instance with no flush() having run yet. The
// connectivity badge must still be able to show a previously stored failed
// entry, which means reading it must not depend on a sync ever happening.
test('a fresh page load reflects a stored failed entry without running a sync', async () => {
    const first = new ForegroundSync();
    await first.queue.init();
    await first.queue.addRequest({ url: '/x', method: 'POST', data: {} });

    globalThis.window.axios = axiosStub(() => Promise.reject(new Error('network down')));
    await first.flush();
    await first.flush();
    await first.flush();

    assert.equal(await first.queue.failedCount(), 1);

    // Second "page load": an independent instance opens the same
    // IndexedDB. No flush() runs on it — this is exactly what the
    // connectivity indicator does on init(): read the persisted count
    // straight from the queue.
    const second = new SyncQueue();
    await second.init();

    assert.equal(await second.failedCount(), 1);
});
