document.addEventListener('DOMContentLoaded', () => {
    const toast = document.querySelector('[data-shop-toast]');
    const panelElement = document.getElementById('instantCart');
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
