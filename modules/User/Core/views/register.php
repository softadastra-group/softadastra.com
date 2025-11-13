<link rel="stylesheet" href="<?= asset('assets/register') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/popup.css') ?>">

<div class="sa-auth">
    <section class="sa-card">
        <header class="sa-card__head">
            <a href="/login"
                class="sa-back"
                data-sa-event="auth_login_back"
                data-label="from_register">
                <i class="fa fa-arrow-left" aria-hidden="true"></i>
                <span>Login</span>
            </a>
            <div class="sa-logo">
                <img src="/public/images/icons/softadastra.png"
                    alt="Softadastra Logo"
                    data-sa-event="auth_logo_click"
                    data-label="register_screen">
            </div>
        </header>

        <div class="sa-card__body">
            <h2 class="sa-title">Create an account</h2>

            <form id="registerForm" method="post" class="sa-form">
                <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token']) : ''; ?>">

                <!-- Fullname -->
                <div class="sa-field">
                    <label class="sa-label" for="fullname">Full Name</label>
                    <input type="text" class="sa-input" id="fullname" name="fullname" spellcheck="false" required>
                </div>

                <!-- Phone -->
                <div class="sa-field" id="phone-wrapper">
                    <label class="sa-label" for="phone_number">WhatsApp Number</label>
                    <div class="softadastra-text-field phone-input-wrapper">
                        <span id="flag-icon" class="flag-icon"></span>
                        <input type="tel"
                            id="phone_number"
                            name="phone_number"
                            inputmode="tel"
                            autocomplete="tel"
                            maxlength="16"
                            placeholder="+256 7XXXXXXXX or +243 8XXXXXXXX"
                            required>
                        <div id="country-dropdown" class="country-dropdown">
                            <div class="country-option" data-code="+256" data-flag="🇺🇬">🇺🇬 Uganda (+256)</div>
                            <div class="country-option" data-code="+243" data-flag="🇨🇩">🇨🇩 DRC (+243)</div>
                        </div>
                        <div id="phone_number_error" class="error-messages"></div>
                    </div>
                </div>

                <!-- Email -->
                <div class="sa-field" id="email-group">
                    <label class="sa-label" for="email">Email</label>
                    <input type="email" class="sa-input" id="email" name="email" spellcheck="false" required>
                </div>

                <!-- Password -->
                <div class="sa-field">
                    <label class="sa-label" for="password">Password</label>
                    <div class="sa-password">
                        <input type="password" class="sa-input" id="password" name="password" required>
                        <!-- ⬇️ toggle password tracé -->
                        <button type="button"
                            class="sa-password__toggle"
                            id="togglePassword"
                            aria-label="Show password"
                            data-sa-event="auth_password_toggle"
                            data-label="register">
                            <i class="fa fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <small class="sa-help">8–20 chars, upper & lower case, a digit & a symbol.</small>
                </div>

                <!-- Help -->
                <p class="sa-inline-help">
                    <i class="fa fa-info-circle" aria-hidden="true"></i>
                    <!-- ⬇️ ouverture help -->
                    <a href="/help" data-sa-event="help_open" data-label="register_help">Do you need help?</a>
                </p>

                <!-- Submit -->
                <div class="sa-actions">
                    <!-- ⬇️ clic sur “Continue” -->
                    <button type="submit"
                        id="custom-login-login"
                        class="sa-btn sa-btn--primary"
                        data-sa-event="auth_register_click"
                        data-label="email+password+phone">
                        <span class="btn-text"><i class="fas fa-check" aria-hidden="true"></i> Continue</span>
                        <span class="btn-spinner spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>

            <!-- Popup -->
            <div id="popupMessage" class="sa-popup" role="alert" aria-live="polite">
                <button id="closePopup" class="sa-popup__close" aria-label="Close">&times;</button>
                <p id="popupText"></p>
            </div>
        </div>
    </section>
</div>


<!-- Bottom-sheet popup pour showMessage -->
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
    // Config locale si besoin (sinon déjà dans ton layout global)
    window.SA_API_BASE = window.SA_ACSS_PATHPI_BASE || "http://localhost:3000";
    window.SA_USER_ID = null; // visiteur non connecté

    // 1) écran Register vu
    window.addEventListener("DOMContentLoaded", () => {
        if (window.SA && typeof SA.event === "function") {
            SA.event("auth_register_view", {
                method: "email+password+phone"
            });
        }
    });

    // 2) Sélection pays (Uganda/DRC) dans la dropdown
    document.getElementById("country-dropdown")?.addEventListener("click", (e) => {
        const opt = e.target.closest(".country-option");
        if (!opt) return;
        const code = opt.getAttribute("data-code");
        const flag = opt.getAttribute("data-flag");
        if (window.SA && typeof SA.event === "function") {
            SA.event("country_select", {
                code,
                flag,
                context: "register"
            });
        }
    });
</script>


<script src="<?= asset('assets/js/modal') ?>" defer></script>
<script src="<?= asset('assets/js/register') ?>" defer></script>