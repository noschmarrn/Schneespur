@props(['fromId' => 'from', 'toId' => 'to', 'submit' => false])

{{--
    Der Wochenanfang kommt als Argument, nicht über window.schneespurFormat.
    Der dortige Vertrag trägt bewußt nur die vier Werte, die die zwei
    server-unerreichbaren Stellen brauchen (laufende Uhr, signed_at), und ist
    als Modulvertrag dokumentiert — ein Wert, den genau diese eine
    Admin-Komponente liest, gehört nicht in jede Seite und nicht in einen
    Vertrag, an den sich Modulautoren dann für immer halten müßten.
--}}
<div x-data="datePresets('{{ $fromId }}', '{{ $toId }}', {{ $submit ? 'true' : 'false' }}, {{ app(\App\Services\Format\Formatter::class)->weekStartsOn() }})" class="flex flex-wrap gap-2 mt-2">
    <button type="button" x-on:click="today()" class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded hover:bg-gray-200 transition">{{ __('ui.date_preset_today') }}</button>
    <button type="button" x-on:click="yesterday()" class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded hover:bg-gray-200 transition">{{ __('ui.date_preset_yesterday') }}</button>
    <button type="button" x-on:click="lastWeek()" class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded hover:bg-gray-200 transition">{{ __('ui.date_preset_last_week') }}</button>
    <button type="button" x-on:click="lastMonth()" class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded hover:bg-gray-200 transition">{{ __('ui.date_preset_last_month') }}</button>
    <button type="button" x-on:click="currentSeason()" class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded hover:bg-gray-200 transition">{{ __('ui.date_preset_season') }}</button>
</div>

<script>
    // weekStartsOn in ISO-Zählung: 1 = Montag, 7 = Sonntag — dieselbe Zahl,
    // die Formatter::weekStartsOn() liefert und der Monatskalender benutzt.
    // Der Vorgabewert hält eine ältere, zwischengespeicherte Seite am Leben,
    // die das vierte Argument noch nicht mitschickt.
    function datePresets(fromId, toId, autoSubmit, weekStartsOn = 1) {
        return {
            set(from, to) {
                const fromEl = document.getElementById(fromId);
                const toEl = document.getElementById(toId);
                if (fromEl) fromEl.value = from;
                if (toEl) toEl.value = to;
                if (autoSubmit && fromEl) fromEl.closest('form')?.requestSubmit();
            },
            fmt(d) { return d.toISOString().slice(0, 10); },
            today() { const d = this.fmt(new Date()); this.set(d, d); },
            yesterday() {
                const d = new Date(); d.setDate(d.getDate() - 1);
                const s = this.fmt(d); this.set(s, s);
            },
            lastWeek() {
                const now = new Date();
                // Abstand bis zum letzten Tag der Vorwoche: getDay() zählt
                // Sonntag als 0. Für die Montagswoche ist das der alte
                // `getDay() || 7`, für die Sonntagswoche genau ein Tag
                // weniger — der Knopf muß denselben Wochenanfang treffen wie
                // der Monatskalender daneben.
                const day = weekStartsOn === 7 ? now.getDay() + 1 : ((now.getDay() + 6) % 7) + 1;
                const end = new Date(now); end.setDate(now.getDate() - day);
                const start = new Date(end); start.setDate(end.getDate() - 6);
                this.set(this.fmt(start), this.fmt(end));
            },
            lastMonth() {
                const now = new Date();
                const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                const end = new Date(now.getFullYear(), now.getMonth(), 0);
                this.set(this.fmt(start), this.fmt(end));
            },
            currentSeason() {
                const now = new Date();
                const y = now.getFullYear();
                const m = now.getMonth();
                const seasonStart = m >= 9 ? new Date(y, 9, 1) : new Date(y - 1, 9, 1);
                const seasonEnd = m >= 9 ? new Date(y + 1, 3, 30) : new Date(y, 3, 30);
                const today = now < seasonEnd ? now : seasonEnd;
                this.set(this.fmt(seasonStart), this.fmt(today));
            },
        };
    }
</script>
