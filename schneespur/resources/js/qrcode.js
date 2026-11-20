import QRCode from 'qrcode';

export function buildOwntracksConfig({ serverUrl, username, password }) {
    return {
        _type: 'configuration',
        mode: 3,
        url: serverUrl,
        // Without this, OwnTracks never sends username/password: a fresh
        // install has the authentication switch off, and importing a config
        // only changes the keys it contains. The server then answers 401 to
        // every request, however often the credentials are rotated.
        auth: true,
        username: username,
        password: password,
        deviceId: 'phone',
        tid: username.substring(0, 2),
    };
}

export function buildOwntracksConfigUrl(credentials) {
    const encoded = btoa(JSON.stringify(buildOwntracksConfig(credentials)));

    return `owntracks:///config?inline=${encoded}`;
}

export function generateOwntracksQr(canvasElement, credentials) {
    const url = buildOwntracksConfigUrl(credentials);

    QRCode.toCanvas(canvasElement, url, { width: 256, margin: 2 }, (err) => {
        if (err) {
            console.error('QR code generation failed:', err);
        }
    });

    return url;
}
