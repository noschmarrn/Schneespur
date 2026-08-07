// Mirrors App\Services\Format\Formatter for the two places the server cannot
// render: the driver dashboard's running clock, and the updater's signed_at
// date. Everything else is formatted server-side — do not grow this file.
//
// The values arrive via window.schneespurFormat, set by the layout partial
// resources/views/partials/format-settings.blade.php.
const settings = () => globalThis.window?.schneespurFormat ?? {};

const pad = (n) => String(n).padStart(2, '0');

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

export function formatTime(value) {
    const date = guardedDate(value);

    if (date === null) {
        return PLACEHOLDER;
    }

    if (settings().timeFormat === '12h') {
        const hours = date.getHours() % 12 || 12;
        const suffix = date.getHours() < 12 ? 'AM' : 'PM';

        return `${hours}:${pad(date.getMinutes())} ${suffix}`;
    }

    return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function formatDate(value) {
    const date = guardedDate(value);

    if (date === null) {
        return PLACEHOLDER;
    }

    const d = pad(date.getDate());
    const m = pad(date.getMonth() + 1);
    const y = date.getFullYear();

    switch (settings().dateFormat) {
        case 'mdy_slash': return `${m}/${d}/${y}`;
        case 'dmy_slash': return `${d}/${m}/${y}`;
        case 'iso': return `${y}-${m}-${d}`;
        default: return `${d}.${m}.${y}`;
    }
}
