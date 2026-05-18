@props(['fromId' => 'from', 'toId' => 'to', 'submit' => false])

<div x-data="datePresets('{{ $fromId }}', '{{ $toId }}', {{ $submit ? 'true' : 'false' }})" class="flex flex-wrap gap-2 mt-2">
    <button type="button" x-on:click="today()" class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded hover:bg-gray-200 transition">{{ __('ui.date_preset_today') }}</button>
    <button type="button" x-on:click="yesterday()" class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded hover:bg-gray-200 transition">{{ __('ui.date_preset_yesterday') }}</button>
    <button type="button" x-on:click="lastWeek()" class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded hover:bg-gray-200 transition">{{ __('ui.date_preset_last_week') }}</button>
    <button type="button" x-on:click="lastMonth()" class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded hover:bg-gray-200 transition">{{ __('ui.date_preset_last_month') }}</button>
    <button type="button" x-on:click="currentSeason()" class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded hover:bg-gray-200 transition">{{ __('ui.date_preset_season') }}</button>
</div>

<script>
    function datePresets(fromId, toId, autoSubmit) {
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
                const day = now.getDay() || 7;
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
