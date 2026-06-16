<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $form->getSeoTitle())</title>
    <meta name="description" content="@yield('seo_description', $form->getSeoDescription())">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS Stack for Bespoke Styling -->
    @yield('styles')
</head>
<body>
    @yield('content')

    <!-- Auto-resizing script for seamless parent-iframe integration -->
    <script>
        (function() {
            const allowedOrigins = @json($form->getAllowedEmbedOrigins());

            function sendHeight() {
                const height = document.documentElement.offsetHeight || document.body.offsetHeight;
                allowedOrigins.forEach(function(origin) {
                    window.parent.postMessage({
                        type: 'polydock-iframe-resize',
                        height: height
                    }, origin);
                });
            }

            window.addEventListener('load', sendHeight);
            window.addEventListener('resize', sendHeight);

            // Observe dynamic changes in the DOM (e.g., toggling fields, error showing, loader running)
            if (window.MutationObserver) {
                const observer = new MutationObserver(function() {
                    sendHeight();
                });
                observer.observe(document.body, {
                    attributes: true,
                    childList: true,
                    subtree: true
                });
            }

            // Periodically check just in case — fire a few times then stop
            let heartbeatCount = 0;
            const heartbeat = setInterval(function() {
                sendHeight();
                if (++heartbeatCount >= 10) clearInterval(heartbeat);
            }, 1000);
        })();
    </script>
    @yield('scripts')
</body>
</html>
