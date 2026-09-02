// Mirrors App\Services\Format\Formatter for the two places the server cannot
// render: the driver dashboard's running clock, and the updater's signed_at
// date. Everything else is formatted server-side — do not grow this file.
//
// The values arrive via window.schneespurFormat, set by the layout partial
// resources/views/partials/format-settings.blade.php.
const settings = () => globalThis.window?.schneespurFormat ?? {};

// The same em dash Formatter::PLACEHOLDER prints, so a missing value looks
// identical whether the server or the browser rendered it.
const PLACEHOLDER = '—';

/**
 * Returns the value if it is a usable Date, otherwise null — and says so once.
 *
 * Both call sites can be handed something unusable: a job queued offline comes
 * back with started_at = null, and the update server does not guarantee
 * signed_at. Printing "NaN:NaN" at a driver is not an option, but neither is
 * swallowing it: a clock that silently stopped looks exactly like a clock with
 * no data yet, and that is the kind of bug that survives releases.
 *
 * Refuses anything that is not already a Date. Accepting a string here would
 * mean the two call sites disagree about who builds it.
 */
export function guardedDate(value) {
    if (value instanceof Date && !Number.isNaN(value.getTime())) {
        return value;
    }

    // Page-scoped rather than module-scoped on purpose: the driver dashboard
    // calls this every second, and a warning per tick would bury the console
    // it is meant to help. Living on `window` also means it is reset by
    // whatever resets the settings — a page load, or a test's beforeEach.
    if (globalThis.window && !globalThis.window.schneespurFormatWarned) {
        globalThis.window.schneespurFormatWarned = true;
        console.warn('schneespur: format received an unusable date, printing the placeholder instead', value);
    }

    return null;
}

/**
 * The parts of a date, in the operation's zone rather than the device's.
 *
 * getHours() and its siblings read whatever zone the phone is set to — right
 * by accident in Passau, wrong on a phone that still stands on the zone of
 * the last holiday, and wrong for every operation whose drivers are not in
 * its own zone. The server hands the zone down in settings().timeZone.
 *
 * Left undefined — an older cached layout, or a test that does not care —
 * Intl falls back to the device zone, which is exactly the previous
 * behaviour. No page breaks over a missing setting.
 *
 * en-GB is a deliberate choice and NOT the user's language: the parts are
 * only being taken apart here, and put back together according to
 * dateFormat. A locale with different digits (ar-EG) would return
 * Arabic-Indic numerals and break the reassembly.
 */
function parts(date) {
    const zone = settings().timeZone;

    const found = {};

    for (const part of new Intl.DateTimeFormat('en-GB', {
        timeZone: zone || undefined,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(date)) {
        found[part.type] = part.value;
    }

    // With hour12:false some engines render midnight as '24'.
    if (found.hour === '24') {
        found.hour = '00';
    }

    return found;
}

export function formatTime(value) {
    const date = guardedDate(value);

    if (date === null) {
        return PLACEHOLDER;
    }

    const { hour, minute } = parts(date);

    if (settings().timeFormat === '12h') {
        const asNumber = Number(hour);
        const hours = asNumber % 12 || 12;
        const suffix = asNumber < 12 ? 'AM' : 'PM';

        return `${hours}:${minute} ${suffix}`;
    }

    return `${hour}:${minute}`;
}

export function formatDate(value) {
    const date = guardedDate(value);

    if (date === null) {
        return PLACEHOLDER;
    }

    const { day: d, month: m, year: y } = parts(date);

    switch (settings().dateFormat) {
        case 'mdy_slash': return `${m}/${d}/${y}`;
        case 'dmy_slash': return `${d}/${m}/${y}`;
        case 'iso': return `${y}-${m}-${d}`;
        default: return `${d}.${m}.${y}`;
    }
}
