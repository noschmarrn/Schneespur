import { test, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { formatDate, formatTime, guardedDate } from '../../resources/js/format.js';

// The layout sets this global; in a test we set it ourselves. Reset before
// each test so one test's region does not leak into the next — and so does
// the warn-once flag, which lives on the same object for exactly that reason.
beforeEach(() => {
    globalThis.window = {};
});

/** Swap console.warn for a recorder; returns the collected calls. */
function captureWarnings() {
    const calls = [];
    const original = console.warn;

    console.warn = (...args) => calls.push(args);
    captureWarnings.restore = () => { console.warn = original; };

    return calls;
}

afterEach(() => {
    captureWarnings.restore?.();
    captureWarnings.restore = undefined;
});

const at = (h, m, s = 0) => new Date(2026, 7, 6, h, m, s); // 6 August 2026, local

test('24h time pads both parts', () => {
    globalThis.window.schneespurFormat = { timeFormat: '24h' };

    assert.equal(formatTime(at(14, 30)), '14:30');
    assert.equal(formatTime(at(9, 5)), '09:05');
});

test('12h time uses AM and PM', () => {
    globalThis.window.schneespurFormat = { timeFormat: '12h' };

    assert.equal(formatTime(at(14, 30)), '2:30 PM');
    assert.equal(formatTime(at(9, 5)), '9:05 AM');
});

// The two cases where `getHours() % 12 || 12` goes wrong if written carelessly:
// midnight must be 12 AM (not 0 AM) and noon must be 12 PM (not 0 PM).
test('midnight and noon survive the twelve hour clock', () => {
    globalThis.window.schneespurFormat = { timeFormat: '12h' };

    assert.equal(formatTime(at(0, 0)), '12:00 AM');
    assert.equal(formatTime(at(12, 0)), '12:00 PM');
    assert.equal(formatTime(at(0, 30)), '12:30 AM');
    assert.equal(formatTime(at(12, 30)), '12:30 PM');
});

test('an unset global falls back to the 24h default', () => {
    assert.equal(formatTime(at(14, 30)), '14:30');
    assert.equal(formatDate(at(14, 30)), '06.08.2026');
});

test('date follows every configured format', () => {
    const cases = {
        dmy_dot: '06.08.2026',
        dmy_slash: '06/08/2026',
        mdy_slash: '08/06/2026',
        iso: '2026-08-06',
    };

    for (const [dateFormat, expected] of Object.entries(cases)) {
        globalThis.window.schneespurFormat = { dateFormat };

        assert.equal(formatDate(at(9, 0)), expected, `format ${dateFormat}`);
    }
});

// An offline-queued job can come back with started_at = null, and the update
// server does not guarantee signed_at. Neither may put "NaN:NaN" in front of a
// driver — but neither may vanish silently either, hence the warning.
test('an unusable date yields the placeholder instead of NaN', () => {
    captureWarnings();

    assert.equal(formatTime(new Date('not a date')), '—');
    assert.equal(formatDate(new Date(undefined)), '—');
    assert.equal(formatTime(null), '—');
    assert.equal(formatDate(undefined), '—');
});

test('a value that merely looks like a date is refused, not coerced', () => {
    captureWarnings();

    // new Date('2026-08-06') would work, but accepting the string here would
    // mean the two call sites disagree about who builds the Date.
    assert.equal(formatDate('2026-08-06'), '—');
    assert.equal(formatTime(1754481600000), '—');
});

test('the warning fires once per page, not once per tick', () => {
    const warnings = captureWarnings();

    // The driver dashboard calls this every second; a warning per tick would
    // bury the console it is meant to help.
    for (let i = 0; i < 5; i++) {
        formatTime(new Date('nope'));
    }
    formatDate(null);

    assert.equal(warnings.length, 1);
    assert.match(warnings[0][0], /unusable date/);
});

test('a valid date never warns', () => {
    const warnings = captureWarnings();
    globalThis.window.schneespurFormat = { timeFormat: '24h' };

    assert.equal(formatTime(at(14, 30)), '14:30');
    assert.equal(warnings.length, 0);
});

/**
 * The trap the call sites have to dodge: `new Date(null)` is not an invalid
 * date, it is the 1st of January 1970. The guard cannot catch it, so both call
 * sites pass null through instead of wrapping it — this test is the reminder
 * of why that `isoStr ? new Date(isoStr) : null` is there.
 */
test('a null timestamp turned into a Date is NOT caught by the guard', () => {
    captureWarnings();
    globalThis.window.schneespurFormat = { dateFormat: 'iso' };

    assert.equal(formatDate(new Date(null)), '1970-01-01');
    assert.equal(formatDate(null), '—');
});

test('guardedDate hands a usable date straight back', () => {
    const moment = at(14, 30);

    assert.equal(guardedDate(moment), moment);
    assert.equal(guardedDate(new Date('nope')), null);
});

test('single digit days and months are padded', () => {
    globalThis.window.schneespurFormat = { dateFormat: 'mdy_slash' };

    assert.equal(formatDate(new Date(2026, 0, 2, 9, 0)), '01/02/2026');
});

// --- The operation's zone, not the device's (1.2.1) ---------------------------
//
// formatTime used date.getHours(), which reads whatever zone the phone is set
// to. That is right by accident in Passau and wrong on a phone that travelled,
// and wrong for every operation whose drivers are not in its own zone. The
// server hands the zone down through window.schneespurFormat.timeZone.

// 15 January 2026, 03:00 UTC. In Chicago that is the 14th at 21:00; in Berlin
// the 15th at 04:00 — a different hour AND a different day.
const utcMoment = () => new Date(Date.UTC(2026, 0, 15, 3, 0, 0));

test('time is rendered in the configured zone, not the device zone', () => {
    globalThis.window = { schneespurFormat: { timeFormat: '24h', timeZone: 'America/Chicago' } };
    assert.equal(formatTime(utcMoment()), '21:00');

    globalThis.window = { schneespurFormat: { timeFormat: '24h', timeZone: 'Europe/Berlin' } };
    assert.equal(formatTime(utcMoment()), '04:00');
});

test('the date follows the zone across the day boundary', () => {
    globalThis.window = { schneespurFormat: { dateFormat: 'iso', timeZone: 'America/Chicago' } };
    assert.equal(formatDate(utcMoment()), '2026-01-14');

    globalThis.window = { schneespurFormat: { dateFormat: 'iso', timeZone: 'Europe/Berlin' } };
    assert.equal(formatDate(utcMoment()), '2026-01-15');
});

test('12h time uses the configured zone for AM/PM too', () => {
    globalThis.window = { schneespurFormat: { timeFormat: '12h', timeZone: 'America/Chicago' } };
    assert.equal(formatTime(utcMoment()), '9:00 PM');
});

test('midnight in the configured zone renders as 00, never 24', () => {
    // 06:00 UTC is midnight in Chicago (UTC-6) on 15 January.
    globalThis.window = { schneespurFormat: { timeFormat: '24h', timeZone: 'America/Chicago' } };
    assert.equal(formatTime(new Date(Date.UTC(2026, 0, 15, 6, 0, 0))), '00:00');
});
