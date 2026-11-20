import { test } from 'node:test';
import assert from 'node:assert/strict';

import { buildOwntracksConfig, buildOwntracksConfigUrl } from '../../resources/js/qrcode.js';

const input = {
    serverUrl: 'https://example.test/api/owntracks',
    username: 'driver-2-1qh5',
    password: 'geheim',
};

// Ohne `auth: true` schickt OwnTracks Benutzer und Passwort nicht mit: eine
// frisch installierte App hat den Schalter aus, und der Import ändert nur, was
// in der Konfiguration steht. Auf Prod hieß das 401 bei jedem Versuch, egal wie
// oft das Passwort neu erzeugt wurde.
test('die Konfiguration schaltet die Authentifizierung ein', () => {
    assert.equal(buildOwntracksConfig(input).auth, true);
});

test('die Konfiguration trägt Server, Zugangsdaten und HTTP-Modus', () => {
    const config = buildOwntracksConfig(input);

    assert.equal(config._type, 'configuration');
    assert.equal(config.mode, 3);
    assert.equal(config.url, input.serverUrl);
    assert.equal(config.username, input.username);
    assert.equal(config.password, input.password);
});

test('die Import-URL enthält die Konfiguration base64-kodiert', () => {
    const url = buildOwntracksConfigUrl(input);
    const prefix = 'owntracks:///config?inline=';

    assert.ok(url.startsWith(prefix));
    const decoded = JSON.parse(Buffer.from(url.slice(prefix.length), 'base64').toString('utf8'));
    assert.deepEqual(decoded, buildOwntracksConfig(input));
});
