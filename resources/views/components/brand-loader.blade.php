<div class="ah-page-loader" id="ah-page-loader" role="status" aria-live="polite" aria-label="Chargement de la page">
    <img src="{{ asset('assets/achathub-logo.webp') }}?v=20260803b" width="82" height="72" alt="">
    <span class="ah-loader-label">Chargement…</span>
</div>
<noscript><style>#ah-page-loader{display:none!important}</style></noscript>
<script>
    (() => {
        const loader = document.getElementById('ah-page-loader');
        if (!loader) return;

        let hidden = false;
        const hideLoader = () => {
            if (hidden) return;
            hidden = true;
            loader.classList.add('is-hidden');
            window.setTimeout(() => loader.remove(), 220);
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', hideLoader, { once: true });
        } else {
            hideLoader();
        }

        window.addEventListener('pageshow', hideLoader, { once: true });
        window.setTimeout(hideLoader, 2500);
    })();
</script>
