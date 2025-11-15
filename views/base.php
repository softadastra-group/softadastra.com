<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= isset($title) ? htmlspecialchars($title) : 'ivi.php' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <?= $meta ?? '' ?>

    <!-- Favicon -->
    <link rel="icon" href="<?= $favicon ?? asset('assets/favicon/favicon.png') ?>">
    <link rel="stylesheet" href="<?= $css ?? asset('assets/css/app.css') ?>">
    <meta name="theme-color" content="#008037">

    <!-- Global CSS -->
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
    <!-- Page-level CSS (optional) -->
    <?= $styles ?? '' ?>
</head>

<body>

    <?php include base_path('views/partials/header.php'); ?>

    <main id="app">
        <?= $content ?? '' ?>
    </main>

    <?php include base_path('views/partials/footer.php'); ?>

    <!-- Global JS -->
    <script src="<?= asset('assets/js/app.js') ?>" defer></script>

    <!-- SPA Progressive Enhancement -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Active SPA uniquement si JS présent
            window.__SPA__ = true;

            const loadPage = async (url) => {
                const res = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const html = await res.text();
                document.getElementById('app').innerHTML = html;
                history.pushState(null, '', url);
            };

            // Tous les liens avec data-spa deviennent SPA
            document.querySelectorAll('a[data-spa]').forEach(link => {
                link.addEventListener('click', e => {
                    e.preventDefault();
                    loadPage(link.href);
                });
            });

            // Gestion du back/forward
            window.addEventListener('popstate', () => loadPage(location.href));
        });
    </script>

    <!-- Page-level JS (optional) -->
    <?= $scripts ?? '' ?>
</body>

</html>