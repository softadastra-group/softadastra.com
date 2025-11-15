<header class="nav" data-header>
    <div class="container nav-row">
        <a class="nav-brand" href="/">
            <img src="<?= asset('assets/logo/ivi.png') ?>" alt="ivi.php logo" width="26" height="26">
            <span>ivi.php</span>
        </a>

        <?= menu([
            '/'         => 'Home',
            '/docs'     => 'Docs',
            '/users'    => 'Users',
            '/auth' => 'Auth',
        ], ['class' => 'nav-links']) ?>

        <span class="nav-pill"><?= htmlspecialchars($_ENV['IVI_VERSION'] ?? 'v0.1.0 • DEV') ?></span>
    </div>
</header>