document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-load-more]');
        if (!button) return;
        event.preventDefault();
        if (button.dataset.loading === 'true') return;
        button.dataset.loading = 'true';
        const label = button.querySelector('span');
        label.textContent = 'Chargement…';
        button.classList.add('disabled');

        try {
            const response = await fetch(button.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('Chargement impossible');
            const documentPage = new DOMParser().parseFromString(await response.text(), 'text/html');
            const newProducts = documentPage.querySelectorAll('[data-product-grid] > .col');
            const grid = document.querySelector('[data-product-grid]');
            newProducts.forEach((product) => grid.append(product));
            const nextButton = documentPage.querySelector('[data-load-more]');
            if (nextButton) {
                button.href = nextButton.href;
                button.dataset.loading = 'false';
                button.classList.remove('disabled');
                label.textContent = 'Afficher plus de produits';
                button.parentElement.querySelector('small').textContent = nextButton.parentElement.querySelector('small').textContent;
            } else {
                button.parentElement.remove();
            }
        } catch (error) {
            button.dataset.loading = 'false';
            button.classList.remove('disabled');
            label.textContent = 'Réessayer';
        }
    });
});
