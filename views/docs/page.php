<?php

/** views/docs/page.php — rendu dans base.php */
?>
<section class="docs-hero py-5 text-center bg-accent text-white">
    <div class="container">
        <h1 class="display-4">Documentation</h1>
        <p class="lead">Learn how to build fast and expressive apps with <strong>ivi.php</strong>.</p>
    </div>
</section>

<div class="docs-content markdown-body my-5 container">
    <?= $content ?>
</div>


<style>
    .markdown-body {
        max-width: var(--max-width);
        /* limite la largeur du contenu */
        margin: 0 auto;
        /* centre le contenu */
        padding: 0 20px;
        /* padding horizontal */
        box-sizing: border-box;
        width: 100%;
    }

    /* ============================
   Hero
============================ */
    .docs-hero {
        --accent: #008037;
        background-color: var(--accent);
    }

    .docs-hero h1 {
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    .docs-hero .lead {
        font-size: 1.125rem;
        opacity: 0.9;
    }

    /* ============================
   Docs content
============================ */
    .docs-content {
        max-width: 900px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }

    /* ============================
   Code blocks
============================ */
    .docs-content .code-block {
        position: relative;
        margin: 2rem 0;
        border-radius: 0.5rem;
        overflow: hidden;
        border: 1px solid var(--docs-code-border, #ddd);
        background: var(--docs-code-bg, #1e1e1e);
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
    }

    .docs-content .code-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
        background: rgba(0, 0, 0, 0.1);
        color: #fff;
    }

    .docs-content .lang-badge {
        font-family: monospace;
        font-weight: 700;
        text-transform: uppercase;
        color: #fff;
    }

    .docs-content .copy-btn {
        appearance: none;
        border: none;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        cursor: pointer;
        font-size: 0.75rem;
        font-weight: 600;
        background: #22d3ee;
        color: #0d1117;
        transition: 0.15s ease;
    }

    .docs-content .copy-btn:hover {
        filter: brightness(1.1);
    }

    .docs-content .copy-btn.copied {
        background: #10b981;
        color: #fff;
    }

    .docs-content pre {
        margin: 0;
        padding: 1rem;
        overflow-x: auto;
        font-family: monospace;
        font-size: 0.875rem;
        line-height: 1.6;
        background: transparent;
        color: var(--docs-code-fg, #eee);
    }

    /* ============================
   Light/Dark support
============================ */
    @media (prefers-color-scheme: light) {
        .docs-content {
            --docs-code-bg: #f9fafb;
            --docs-code-fg: #111;
            --docs-code-border: #ddd;
        }

        .docs-hero {
            --accent: #008037;
            color: #fff;
        }
    }

    /* ============================
   Responsive & typography
============================ */
    .docs-content h1,
    .docs-content h2,
    .docs-content h3 {
        font-weight: 700;
    }

    .docs-content p {
        line-height: 1.65;
        margin-bottom: 1rem;
    }

    .docs-content img {
        max-width: 100%;
        height: auto;
    }

    /* ============================
   Containers de code
============================ */
    .code-block {
        position: relative;
        margin: 1.5rem 0;
        border-radius: 0.5rem;
        overflow: hidden;
        background: #0d1117;
        /* Dark code bg */
        border: 1px solid #30363d;
        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.15);
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }

    /* Header (badge + copy) */
    .code-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.4rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.05);
        color: #c9d1d9;
    }

    .lang-badge {
        text-transform: uppercase;
        font-family: monospace;
        letter-spacing: 0.5px;
    }

    /* Bouton copy */
    .copy-btn {
        appearance: none;
        border: none;
        background: #22d3ee;
        color: #0d1117;
        font-weight: 600;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        cursor: pointer;
        transition: 0.15s ease;
    }

    .copy-btn:hover {
        filter: brightness(1.1);
    }

    .copy-btn.copied {
        background: #10b981;
        color: #fff;
    }

    .copy-btn.copied::after {
        content: " ✓ Copied!";
        margin-left: 0.3rem;
    }

    /* Code area */
    .code-block pre {
        margin: 0;
        padding: 1rem;
        overflow-x: auto;
        font-size: 0.875rem;
        line-height: 1.6;
        background: transparent;
        color: #c9d1d9;
    }

    .code-block pre code {
        display: block;
    }

    /* Optional : zebra stripes par ligne si JS injecte .line */
    .code-block pre code .line:nth-child(odd) {
        background: rgba(255, 255, 255, 0.02);
        display: block;
        padding-left: 0.5rem;
    }

    /* Selection */
    .code-block ::selection {
        background: rgba(34, 211, 238, 0.3);
    }

    /* Light mode */
    @media (prefers-color-scheme: light) {
        .code-block {
            background: #f9fafb;
            border: 1px solid #d1d5db;
        }

        .code-head {
            color: #111827;
            background: rgba(0, 0, 0, 0.03);
        }

        .copy-btn {
            background: #22d3ee;
            color: #111827;
        }

        .copy-btn.copied {
            background: #10b981;
            color: #fff;
        }

        .code-block pre {
            color: #1f2937;
        }

        .code-block pre code .line:nth-child(odd) {
            background: rgba(0, 0, 0, 0.03);
        }
    }
</style>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.querySelectorAll(".copy-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                const code = btn.closest(".code-block").querySelector("pre code").innerText;
                navigator.clipboard.writeText(code).then(() => {
                    btn.classList.add("copied");
                    setTimeout(() => btn.classList.remove("copied"), 2000);
                });
            });
        });
    });
</script>