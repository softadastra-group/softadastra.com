<?php

$client   = $params['client'] ?? null;
$loginUrl = $client ? $client->createAuthUrl() : '#';
$next     = isset($_GET['next']) ? trim($_GET['next']) : '';
?>
<style>
    /* ====== LOGIN PAGE GOOD BOOK ====== */

    /* Container */
    .xa-shell {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--gb-bg);
        color: var(--gb-text);
        transition: background 0.3s, color 0.3s;
        padding: 2rem;
        font-family: var(--font-sans);
    }

    .xa-container.is-single {
        max-width: 450px;
        width: 100%;
    }

    /* CARD */
    .xa-card {
        background: var(--gb-header-bg);
        color: var(--gb-header-text);
        border-radius: 12px;
        padding: 2rem;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2);
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        transition: background 0.3s, color 0.3s;
    }

    .xa-card-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .xa-link {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        color: var(--gb-gold);
        font-weight: 500;
        text-decoration: none;
        transition: color 0.3s;
    }

    .xa-link:hover {
        color: var(--gb-gold-variant2);
    }

    .xa-card-body h2.xa-title {
        font-family: var(--font-serif);
        font-size: 1.8rem;
        margin-bottom: 0.5rem;
    }

    .xa-card-body p.xa-desc {
        font-size: 0.95rem;
        margin-bottom: 1.5rem;
        color: var(--gb-white);
    }

    /* BUTTONS */
    .xa-actions {
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
    }

    .xa-btn {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.6rem 1rem;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.95rem;
        transition: background 0.3s, transform 0.2s;
        justify-content: center;
    }

    .xa-btn svg {
        flex-shrink: 0;
    }

    .xa-btn--google {
        background: #ffffff;
        color: #000;
        border: 1px solid var(--gb-gold);
    }

    .xa-btn--google:hover {
        background: var(--gb-gold);
        color: var(--gb-black);
    }

    .xa-btn--email {
        background: var(--gb-gold);
        color: var(--gb-black);
    }

    .xa-btn--email:hover {
        background: var(--gb-gold-variant2);
    }

    .xa-btn--primary {
        background: var(--gb-black);
        color: var(--gb-gold);
    }

    .xa-btn--primary:hover {
        background: var(--gb-gold);
        color: var(--gb-black);
    }

    /* Divider */
    .xa-divider {
        text-align: center;
        font-size: 0.9rem;
        color: #ccc;
        margin: 0.8rem 0;
        position: relative;
    }

    .xa-divider::before,
    .xa-divider::after {
        content: '';
        position: absolute;
        top: 50%;
        width: 40%;
        height: 1px;
        background: #ccc;
    }

    .xa-divider::before {
        left: 0;
    }

    .xa-divider::after {
        right: 0;
    }

    /* LEGAL */
    .xa-legal {
        font-size: 0.75rem;
        text-align: center;
        margin-top: 1rem;
        color: #aaa;
    }

    .xa-legal a {
        color: var(--gb-gold);
        text-decoration: none;
    }

    .xa-legal a:hover {
        color: var(--gb-gold-variant2);
    }

    /* RESPONSIVE */
    @media (max-width: 480px) {
        .xa-card {
            padding: 1.5rem;
        }

        .xa-card-body h2.xa-title {
            font-size: 1.5rem;
        }

        .xa-actions {
            gap: 0.6rem;
        }
    }

    /* Header & Footer dynamiques */
    .gb-header,
    .gb-footer,
    .xa-card {
        transition: background 0.3s ease, color 0.3s ease;
    }

    /* Thème sombre */
    body.dark-theme {
        --gb-bg: #0f0f0f;
        --gb-text: #f1f1f1;
        --gb-header-bg: #1a1a1a;
        --gb-header-text: var(--gb-gold);
        --gb-footer-bg: #1a1a1a;
        --gb-footer-text: var(--gb-gold);
    }

    /* Thème clair */
    body:not(.dark-theme) {
        --gb-bg: var(--gb-white);
        --gb-text: var(--gb-black);
        --gb-header-bg: var(--gb-black);
        --gb-header-text: var(--gb-white);
        --gb-footer-bg: var(--gb-black);
        --gb-footer-text: var(--gb-white);
    }

    /* Appliquer les couleurs */
    body {
        background: var(--gb-bg);
        color: var(--gb-text);
    }

    .gb-header {
        background-color: var(--gb-header-bg);
        color: var(--gb-header-text);
    }

    .gb-footer {
        background-color: var(--gb-footer-bg);
        color: var(--gb-footer-text);
    }

    .xa-card {
        background-color: var(--gb-header-bg);
        color: var(--gb-header-text);
    }
</style>
<script>
    window.SA_DISABLE = true;
</script>

<div class="xa-shell">
    <div class="xa-container is-single">
        <!-- RIGHT: Auth options -->
        <main class="xa-right" aria-label="Sign in options">
            <section class="xa-card">
                <header class="xa-card-head">
                    <!-- Bouton Back avec SVG -->
                    <a href="/" class="xa-link xa-back">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                            <path fill-rule="evenodd" d="M15 8a.75.75 0 0 1-.75.75H3.56l4.72 4.72a.75.75 0 0 1-1.06 1.06l-6-6a.75.75 0 0 1 0-1.06l6-6a.75.75 0 0 1 1.06 1.06L3.56 7.25h10.69A.75.75 0 0 1 15 8z" />
                        </svg>
                        <span>Home</span>
                    </a>
                    <!-- Bouton Help avec SVG -->
                    <a href="/help" class="xa-link xa-help">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                            <path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zM0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8z" />
                            <path d="M5.255 5.786a.237.237 0 0 1 .241-.247h.825c.138 0 .248.113.266.25.09.648.54 1.094 1.247 1.094.708 0 1.167-.45 1.167-1.07 0-.618-.42-1.07-1.07-1.07-.364 0-.678.143-.852.401a.25.25 0 0 1-.33.083l-.764-.43a.25.25 0 0 1-.092-.334C6.072 3.846 6.905 3.5 8 3.5c1.42 0 2.375.912 2.375 2.15 0 .818-.48 1.513-1.181 1.801v.036c.662.22 1.056.776 1.056 1.479 0 1.097-.893 1.885-2.25 1.885-1.178 0-2.034-.593-2.174-1.524a.247.247 0 0 1 .241-.287h.825c.12 0 .224.082.246.2.083.417.401.684.862.684.545 0 .905-.365.905-.899 0-.528-.37-.9-.95-.9h-.287a.25.25 0 0 1-.25-.25v-.637z" />
                            <circle cx="8" cy="11.75" r="1" />
                        </svg>
                        <span>Help</span>
                    </a>
                </header>
                <div class="xa-card-body">
                    <h2 class="xa-title">Sign in</h2>
                    <p class="xa-desc">Choose a method to access your account.</p>
                    <div class="xa-actions">
                        <!-- Google -->
                        <a class="xa-btn xa-btn--google" id="xaBtnGoogle" href="<?= htmlspecialchars($loginUrl) ?>">
                            <!-- Logo Google Officiel -->
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 533.5 544.3" width="20" height="20" aria-hidden="true">
                                <path fill="#4285F4" d="M533.5 278.4c0-17.4-1.6-34.1-4.6-50.2H272v95.0h146.9c-6.4 34.5-25.8 63.7-55.0 83.4v68h88.8c52.0-47.9 81-118.5 81-196.2z" />
                                <path fill="#34A853" d="M272 544.3c74.8 0 137.5-24.9 183.3-67.7l-88.8-68c-24.7 16.6-56.4 26.4-94.5 26.4-72.7 0-134.4-49.1-156.5-115.1H23.7v72.4C69.9 482.3 164.1 544.3 272 544.3z" />
                                <path fill="#FBBC05" d="M115.5 319.9c-10.9-32.7-10.9-68.1 0-100.8V146.7H23.7c-42.1 83.8-42.1 183.9 0 267.7l91.8-94.5z" />
                                <path fill="#EA4335" d="M272 107.7c39.7 0 75.5 13.6 103.6 40.5l77.6-77.6C409.5 24.9 346.8 0 272 0 164.1 0 69.9 62.0 23.7 146.7l91.8 72.4c22.1-66 83.8-115.1 156.5-115.1z" />
                            </svg>
                            <span>Continue with Google</span>
                        </a>
                        <!-- Email (redirige vers la page dédiée) -->
                        <a class="xa-btn xa-btn--email" id="xaBtnEmail" href="/login/email<?= $next ? ('?next=' . urlencode($next)) : '' ?>">
                            <!-- Icône enveloppe simple -->
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                                <path fill="#3c4043" d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" />
                            </svg>
                            <span>Continue with Email</span>
                        </a>
                        <div class="xa-divider">or</div>
                        <!-- Create account -->
                        <a class="xa-btn xa-btn--primary" id="xaBtnCreate" href="/register<?= $next ? ('?next=' . urlencode($next)) : '' ?>">
                            <!-- Icône "user-circle-plus" plus grand -->
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="26" height="26" aria-hidden="true">
                                <path fill="#1b1100" d="M12 2a10 10 0 100 20 10 10 0 000-20zm0 4a3 3 0 110 6 3 3 0 010-6zm0 12c-2.33 0-4.32-1.17-5.5-3 .03-1.66 3.34-2.58 5.5-2.58s5.47.92 5.5 2.58c-1.18 1.83-3.17 3-5.5 3zm7-7v2h-2v2h-2v-2h-2v-2h2V9h2v2h2z" />
                            </svg>
                            <span>Create my account</span>
                        </a>

                    </div>
                </div>
                <div class="xa-legal">
                    <small>By continuing you agree to our <a href="/terms">Terms</a> and <a href="/privacy">Privacy</a>.</small>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
    /* Propage ?next= vers Google si présent */
    (function() {
        const next = new URLSearchParams(location.search).get('next');
        if (!next) return;
        const g = document.getElementById('xaBtnGoogle');
        if (!g) return;
        try {
            const u = new URL(g.href, location.origin);
            u.searchParams.set('next', next);
            g.href = u.toString();
        } catch {}
    })();
</script>
<script>
    (function() {
        function setHeaderH() {
            var header =
                document.querySelector('.softadastra-navbar .softadastra-header') ||
                document.querySelector('.softadastra-navbar');

            var h = header ? header.getBoundingClientRect().height : 64;
            document.documentElement.style.setProperty('--header-h', Math.round(h) + 'px');
        }

        window.addEventListener('DOMContentLoaded', setHeaderH);
        window.addEventListener('load', setHeaderH);
        window.addEventListener('resize', setHeaderH);
        window.addEventListener('orientationchange', setHeaderH);
    })();
</script>