import QRCode from 'qrcode';

export function generateOwntracksQr(canvasElement, { serverUrl, username, password }) {
    const config = {
        _type: 'configuration',
        mode: 3,
        url: serverUrl,
        username: username,
        password: password,
        deviceId: 'phone',
        tid: username.substring(0, 2),
    };

    const payload = JSON.stringify(config);
    const encoded = btoa(payload);
    const url = `owntracks:///config?inline=${encoded}`;

    QRCode.toCanvas(canvasElement, url, { width: 256, margin: 2 }, (err) => {
        if (err) {
            console.error('QR code generation failed:', err);
        }
    });

    return url;
}
