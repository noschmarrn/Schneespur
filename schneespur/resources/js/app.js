import './bootstrap';

import Alpine from 'alpinejs';
import L from 'leaflet';
import { foregroundSync } from './services/foreground-sync.js';
import { generateOwntracksQr } from './qrcode.js';
import { formatDate, formatTime } from './format.js';

window.Alpine = Alpine;
window.L = L;
window.generateOwntracksQr = generateOwntracksQr;

// Prefixed because module assets load into the same page (@moduleAssets) and
// a bare `formatTime` global would be a collision waiting to happen.
window.schneespurFormatTime = formatTime;
window.schneespurFormatDate = formatDate;

if (window.location.pathname.startsWith('/driver')) {
    window.foregroundSync = foregroundSync;
    foregroundSync.init();
}

Alpine.start();
