import './bootstrap';

import Alpine from 'alpinejs';
import L from 'leaflet';
import { foregroundSync } from './services/foreground-sync.js';
import { SyncQueue } from './services/sync-queue.js';
import { generateOwntracksQr } from './qrcode.js';
import { formatDate, formatTime } from './format.js';

window.Alpine = Alpine;
window.L = L;
window.generateOwntracksQr = generateOwntracksQr;

// The class, not just the ready-made instance: a module with different retry
// needs can run its own queue without shipping idb a second time.
window.SyncQueue = SyncQueue;

// Prefixed because module assets load into the same page (@moduleAssets) and
// a bare `formatTime` global would be a collision waiting to happen.
window.schneespurFormatTime = formatTime;
window.schneespurFormatDate = formatDate;

if (window.location.pathname.startsWith('/driver')) {
    window.foregroundSync = foregroundSync;
    // The connectivity indicator awaits this before touching `queue.db` —
    // without it, a page that loads faster than IndexedDB opens could read
    // the queue before init() has actually finished.
    window.foregroundSync.ready = foregroundSync.init();
}

Alpine.start();
