<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ ($title ?? null) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
<script>
    (function () {
        try {
            if (localStorage.getItem('theme') !== 'light') {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {}
    })();
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
