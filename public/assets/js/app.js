// ivi.php – global boot
document.addEventListener("DOMContentLoaded", () => {
  // --- Global non-SPA code ---
  const y = document.getElementById("y");
  if (y) y.textContent = new Date().getFullYear();

  const header = document.querySelector("[data-header]");
  if (header) {
    const onScroll = () =>
      header.classList.toggle("is-scrolled", window.scrollY > 4);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  // --- SPA Progressive Enhancement ---
  if (!window.__SPA__) return;

  const appContainer = document.getElementById("app");
  if (!appContainer) return;

  // Create a simple loading overlay
  const loader = document.createElement("div");
  loader.id = "spa-loader";
  loader.style.cssText = `
    position: fixed;
    top:0; left:0; right:0; bottom:0;
    background: rgba(255,255,255,0.6);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #008037;
    transition: opacity 0.3s ease;
    opacity: 0;
    pointer-events: none;
  `;
  loader.textContent = "Loading...";
  document.body.appendChild(loader);

  const showLoader = () => {
    loader.style.opacity = 1;
    loader.style.pointerEvents = "all";
  };
  const hideLoader = () => {
    loader.style.opacity = 0;
    loader.style.pointerEvents = "none";
  };

  // Create a simple error overlay
  const errorOverlay = document.createElement("div");
  errorOverlay.id = "spa-error";
  errorOverlay.style.cssText = `
    position: fixed;
    top:0; left:0; right:0; bottom:0;
    background: rgba(255,0,0,0.1);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #900;
    padding: 1rem;
    text-align: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
  `;
  document.body.appendChild(errorOverlay);

  const showError = (msg) => {
    errorOverlay.textContent = msg;
    errorOverlay.style.opacity = 1;
    errorOverlay.style.pointerEvents = "all";
    setTimeout(() => {
      errorOverlay.style.opacity = 0;
      errorOverlay.style.pointerEvents = "none";
    }, 4000);
  };

  /**
   * Load a page via AJAX and update SPA content
   */
  const loadPage = async (url, push = true) => {
    showLoader();
    try {
      const res = await fetch(url, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      if (!res.ok) throw new Error(`AJAX Error ${res.status}`);
      const html = await res.text();

      // Smooth fade-out/fade-in
      appContainer.style.opacity = 0;
      setTimeout(() => {
        appContainer.innerHTML = html;
        appContainer.style.opacity = 1;
      }, 150);

      // Reinitialize SPA links and scripts
      initSPALinks();
      runPageScripts(appContainer);

      // Update history and active links
      if (push) history.pushState(null, "", url);
      updateActiveLinks();

      // Scroll to top
      window.scrollTo(0, 0);
    } catch (err) {
      console.error(err);
      showError("Failed to load page, redirecting...");
      setTimeout(() => {
        window.location.href = url;
      }, 2000);
    } finally {
      hideLoader();
    }
  };

  /**
   * Initialize SPA links
   */
  const initSPALinks = () => {
    document.querySelectorAll("a[data-spa]").forEach((link) => {
      if (link._spaHandler) link.removeEventListener("click", link._spaHandler);
      link._spaHandler = (e) => {
        e.preventDefault();
        loadPage(link.href);
      };
      link.addEventListener("click", link._spaHandler);
    });
  };

  /**
   * Execute scripts inside container
   */
  const runPageScripts = (container) => {
    container.querySelectorAll("script").forEach((oldScript) => {
      const script = document.createElement("script");
      if (oldScript.src) {
        script.src = oldScript.src;
        script.defer = true;
      } else {
        script.textContent = oldScript.textContent;
      }
      document.body.appendChild(script);
      oldScript.remove();
    });
  };

  /**
   * Update active class for SPA links
   */
  const updateActiveLinks = () => {
    const path = location.pathname;
    document.querySelectorAll("a[data-spa]").forEach((link) => {
      const href = link.getAttribute("href");
      if (!href) return;
      const isActive = path === href || path.startsWith(href + "/");
      link.classList.toggle("active", isActive);
    });
  };

  // --- Initial SPA setup ---
  initSPALinks();
  updateActiveLinks();

  // Handle browser back/forward
  window.addEventListener("popstate", () => loadPage(location.href, false));
});
