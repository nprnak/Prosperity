<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @php
            // Pages live either in resources/js/Pages or in a module's Vue/Pages dir.
            $pageFile = "resources/js/Pages/{$page['component']}.vue";
            if (! file_exists(base_path($pageFile))) {
                $moduleMatch = glob(base_path("Modules/*/Vue/Pages/{$page['component']}.vue"));
                $pageFile = $moduleMatch ? ltrim(str_replace(base_path(), '', $moduleMatch[0]), '/') : null;
            }
        @endphp
        @vite(array_filter(['resources/js/app.js', $pageFile]))
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
