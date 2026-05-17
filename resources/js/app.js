import './bootstrap';

import Alpine from 'alpinejs';
import L from 'leaflet';
import { foregroundSync } from './services/foreground-sync.js';
import { generateOwntracksQr } from './qrcode.js';

window.Alpine = Alpine;
window.L = L;
window.generateOwntracksQr = generateOwntracksQr;

if (window.location.pathname.startsWith('/driver')) {
    window.foregroundSync = foregroundSync;
    foregroundSync.init();
}

Alpine.start();
