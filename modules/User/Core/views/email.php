<?php

$client = $params['client'] ?? null;
$login_url = $client->createAuthUrl();
?>
<link rel="stylesheet" href="<?= asset('assets/css/auth.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/with-email.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/email.css') ?>auth/email.css">
<link rel="stylesheet" href="<?= asset('assets/css/popup.css') ?>">

<div class="sa-auth">
    <section class="sa-card">
        <div class="sa-card__head">
            <a href="/login" class="sa-back">
                <i class="fa fa-arrow-left" aria-hidden="true"></i>
                <span>Back</span>
            </a>
            <div class="sa-logo">
                <img src="/public/images/icons/softadastra.png" alt="Softadastra Logo">
            </div>
        </div>
        <div class="sa-card__body">
            <h2 class="sa-title">Sign in</h2>
            <form id="loginForm" method="post" class="sa-form">
                <input type="hidden" name="next" id="nextParam">
                <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token']) : ''; ?>">
                <div class="sa-field">
                    <label class="sa-label" for="email">Email</label>
                    <input type="email" class="sa-input" id="email" name="email"
                        value="<?= isset($_SESSION['existing_email']) ? htmlspecialchars($_SESSION['existing_email']) : ''; ?>"
                        required>
                </div>
                <div class="sa-field">
                    <label class="sa-label" for="password">Password</label>
                    <div class="sa-password">
                        <input type="password" class="sa-input" id="password" name="password" required>
                        <button type="button"
                            class="sa-password__toggle"
                            id="togglePassword"
                            aria-label="Show password"
                            data-sa-event="auth_password_toggle"
                            data-label="toggle_password">
                            <i class="fa fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="sa-actions">
                    <button type="submit"
                        id="custom-login-login"
                        class="sa-btn sa-btn--primary"
                        data-sa-event="auth_login_submit"
                        data-label="email_password"
                        aria-live="polite">
                        <span class="btn-text">Continue</span>
                        <span class="btn-spinner" role="status" aria-hidden="true"></span>
                    </button>

                </div>
            </form>
            <a href="/auth/forgot-password"
                class="sa-link sa-forgot"
                data-sa-event="auth_forgot_click"
                data-label="forgot_password">Forgot your password?</a>

            <div class="sa-footer">
                <p>New to Softadastra?</p>
                <a href="/register"
                    class="sa-btn sa-btn--secondary"
                    data-sa-event="auth_register_click"
                    data-label="register">Create my account</a>
            </div>
        </div>
    </section>
</div>

<div id="popupMessage" class="sa-popup" style="display:none;">
    <button class="sa-popup__close">&times;</button>
    <p id="popupText"></p>
</div>

<div id="success-popup" class="sa-flash sa-flash--success">
    <button class="sa-popup__close">&times;</button>
    <i class="fas fa-check-circle icon"></i>
    <div class="message-content">
        <p id="success-message"></p>
    </div>
</div>

<div id="error-popup" class="sa-flash sa-flash--error">
    <button class="sa-popup__close">&times;</button>
    <div class="message-content">
        <ul id="error-message" class="error-list"></ul>
    </div>
</div>

<div id="shop-popup" class="shop-popup" style="display:none;">
    <div class="shop-popup-backdrop"></div>
    <div class="shop-popup-sheet" role="dialog" aria-live="polite" aria-modal="true">
        <div class="shop-popup-grabber" aria-hidden="true"></div>
        <div class="shop-popup-header">
            <span id="popup-icon" class="popup-icon" aria-hidden="true"></span>
            <h3 id="popup-title" class="popup-title">Message</h3>
        </div>
        <div class="shop-popup-body">
            <p id="popup-text" class="popup-text"></p>
            <a id="popup-link" class="popup-link" href="#" style="display:none;">Open</a>
        </div>
        <button type="button" class="shop-popup-close" aria-label="Close">&times;</button>
    </div>
</div>
<script>
    // LOCAL
    window.SA_USER_ID = null;

    window.addEventListener("DOMContentLoaded", () => {
        if (window.SA && typeof SA.event === "function") {
            SA.event("auth_login_view", {
                method: "email_password"
            });
        }
    });
</script>
<script src="<?= asset('assets/js/modal.js') ?>" defer></script>
<script src="<?= asset('assets/js/login.js') ?>" defer></script>