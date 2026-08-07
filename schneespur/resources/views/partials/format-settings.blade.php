{{--
    Hands the browser the three format values it cannot get server-side.

    Included by every layout before the script bundle. It reads the Formatter
    singleton rather than Setting::get() so a page load does not pay three
    extra queries for it, and so the defaults live in exactly one place.
--}}
<script>
    window.schneespurFormat = @json(app(\App\Services\Format\Formatter::class)->browserSettings());
</script>
