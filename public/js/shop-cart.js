document.addEventListener('DOMContentLoaded', () => {
    const toast = document.querySelector('[data-shop-toast]');
    const panelElement = document.getElementById('instantCart');
    let csrfPromise = null;
    const notify = (message, error = false) => {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.toggle('error', error);
        toast.classList.add('show');
        window.setTimeout(() => toast.classList.remove('show'), 2600);
    };
    const updateCounters = (count) => {
        document.querySelectorAll('[data-cart-count]').forEach((badge) => {
            badge.textContent = count;
            badge.classList.toggle('d-none', count === 0);
        });
        document.querySelectorAll('[data-cart-summary]').forEach((summary) => {
            summary.textContent = `${count} article${count > 1 ? 's' : ''} dans le panier`;
        });
    };
    const ensureSessionCsrf = async () => {
        if (document.body.dataset.sessionReady === '1') return;
        csrfPromise ??= fetch('/session/csrf', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(async (response) => {
            const data = await response.json();
            if (!response.ok || !data.token) throw new Error('La session sécurisée n’a pas pu être créée.');
            document.querySelectorAll('input[name="_token"]').forEach((input) => { input.value = data.token; });
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.content = data.token;
            document.body.dataset.sessionReady = '1';
        }).catch((error) => {
            csrfPromise = null;
            throw error;
        });

        await csrfPromise;
    };

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('.cookie-banner form');
        if (!form || document.body.dataset.sessionReady === '1') return;
        event.preventDefault();
        if (form.dataset.loading === 'true') return;
        form.dataset.loading = 'true';
        const submitter = event.submitter;
        try {
            await ensureSessionCsrf();
            form.requestSubmit(submitter || undefined);
        } catch (error) {
            form.dataset.loading = 'false';
            notify(error.message || 'La préférence n’a pas pu être enregistrée.', true);
        }
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('.js-add-to-cart');
        if (!form) return;
        event.preventDefault();
        if (form.dataset.loading === 'true') return;
        const button = form.querySelector('button[type="submit"], button:not([type])');
        const label = button?.querySelector('span');
        const originalLabel = label?.textContent;
        form.dataset.loading = 'true';
        if (button) button.disabled = true;
        if (label) label.textContent = 'Ajout en cours…';

        try {
            await ensureSessionCsrf();
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Impossible d’ajouter ce produit.');
            updateCounters(data.cart_count);
            if (label) label.textContent = 'Ajouté !';
            if (panelElement) {
                panelElement.querySelector('[data-instant-cart-image]').src = data.product.image;
                const name = panelElement.querySelector('[data-instant-cart-name]');
                name.textContent = data.product.name;
                name.href = data.product.url;
                panelElement.querySelector('[data-instant-cart-quantity]').textContent = data.item_quantity;
                panelElement.querySelector('[data-instant-cart-price]').textContent = data.product.price;
                panelElement.querySelector('[data-instant-cart-subtotal]').textContent = data.subtotal;
                bootstrap.Offcanvas.getOrCreateInstance(panelElement).show();
            }
            notify(data.message);
        } catch (error) {
            notify(error.message || 'Une erreur est survenue.', true);
        } finally {
            window.setTimeout(() => {
                if (button) button.disabled = false;
                if (label) label.textContent = originalLabel;
                form.dataset.loading = 'false';
            }, 900);
        }
    });
});
