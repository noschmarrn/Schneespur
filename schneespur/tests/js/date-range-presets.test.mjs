// Die Zeitzone vor der ersten Date-Benutzung festnageln: fmt() der Komponente
// geht über toISOString(), das in UTC rechnet. Ohne das hinge das erwartete
// Datum an der Zone der Maschine, auf der die Suite gerade läuft.
process.env.TZ = 'UTC';

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

// Gegen die echte Quelle, nicht gegen eine Kopie: der Code lebt in einem
// <script> im Blade-Template der Komponente. Eine nachgebaute Fassung hier
// würde nur beweisen, daß die Kopie stimmt.
const bladePath = fileURLToPath(
    new URL('../../resources/views/components/date-range-presets.blade.php', import.meta.url)
);
const blade = readFileSync(bladePath, 'utf8');
const script = blade.match(/<script>([\s\S]*?)<\/script>/);

assert.ok(script, 'Die Komponente hat keinen <script>-Block mehr');
assert.ok(
    ! script[1].includes('{{'),
    'Der Skriptblock enthält Blade-Ausgaben und ist so nicht mehr als reines JavaScript prüfbar'
);

// Indirektes eval, damit die Funktionsdeklaration im globalen Bereich landet
// und `Date` erst beim Aufruf aufgelöst wird — sonst griffe der Tausch unten
// nicht.
(0, eval)(script[1]);

/** Führt fn() so aus, als wäre `iso` der jetzige Moment. */
function at(iso, fn) {
    const Real = globalThis.Date;

    class Fixed extends Real {
        constructor(...args) {
            super(...(args.length === 0 ? [iso] : args));
        }

        static now() {
            return new Real(iso).getTime();
        }
    }

    globalThis.Date = Fixed;

    try {
        return fn();
    } finally {
        globalThis.Date = Real;
    }
}

/** Ruft einen Knopf der Komponente auf und gibt zurück, was er in die Felder schreibt. */
function press(weekStartsOn, button, iso) {
    const written = {};

    globalThis.document = {
        getElementById: (id) => ({
            set value(v) {
                written[id] = v;
            },
            closest: () => null,
        }),
    };

    const component = globalThis.datePresets('from', 'to', false, weekStartsOn);

    at(iso, () => component[button]());

    return written;
}

// Mittwoch, 2. September 2026. Die Vorwoche ist damit
// Mo 24.08.–So 30.08. (Montagswoche) bzw. So 23.08.–Sa 29.08. (Sonntagswoche).
const wednesday = '2026-09-02T12:00:00Z';

test('die Montagswoche läuft von Montag bis Sonntag', () => {
    assert.deepEqual(press(1, 'lastWeek', wednesday), { from: '2026-08-24', to: '2026-08-30' });
});

test('die Sonntagswoche läuft von Sonntag bis Samstag', () => {
    assert.deepEqual(press(7, 'lastWeek', wednesday), { from: '2026-08-23', to: '2026-08-29' });
});

// Der Sonntag selbst ist der Tag, an dem sich die beiden Zählungen am
// stärksten unterscheiden: in der Montagswoche ist er der letzte Tag der
// gerade endenden Woche, in der Sonntagswoche der erste Tag der neuen.
test('am Sonntag zeigen die beiden Wochenanfänge verschiedene Vorwochen', () => {
    const sunday = '2026-08-30T12:00:00Z';

    assert.deepEqual(press(1, 'lastWeek', sunday), { from: '2026-08-17', to: '2026-08-23' });
    assert.deepEqual(press(7, 'lastWeek', sunday), { from: '2026-08-23', to: '2026-08-29' });
});

// Ohne viertes Argument — eine ältere, zwischengespeicherte Seite — muß die
// Komponente sich weiter genau so verhalten wie vor der Umstellung.
test('ohne Angabe bleibt es bei der Montagswoche', () => {
    const written = {};

    globalThis.document = {
        getElementById: (id) => ({
            set value(v) {
                written[id] = v;
            },
            closest: () => null,
        }),
    };

    const component = globalThis.datePresets('from', 'to', false);

    at(wednesday, () => component.lastWeek());

    assert.deepEqual(written, { from: '2026-08-24', to: '2026-08-30' });
});
