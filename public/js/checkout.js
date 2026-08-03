document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-checkout-form]');
    if (!form) return;
    const totals = form.querySelector('[data-subtotal-cents]');
    const shippingOutput = form.querySelector('[data-checkout-shipping]');
    const totalOutput = form.querySelector('[data-checkout-total]');
    const buttonTotal = form.querySelector('[data-checkout-button-total]');
    const formatPrice = (cents) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(cents / 100);
    const refresh = () => {
        const selected = form.querySelector('[name="shipping_method"]:checked');
        const subtotal = Number(totals.dataset.subtotalCents);
        const shipping = Number(selected?.dataset.shippingPrice || 0);
        shippingOutput.textContent = shipping === 0 ? 'Offerte' : formatPrice(shipping);
        totalOutput.textContent = formatPrice(subtotal + shipping);
        buttonTotal.textContent = formatPrice(subtotal + shipping);
    };
    form.querySelectorAll('[name="shipping_method"]').forEach((input) => input.addEventListener('change', refresh));
    form.addEventListener('submit', () => {
        const button = form.querySelector('.checkout-submit');
        if (!button || button.disabled) return;
        button.disabled = true;
        button.querySelector('span').textContent = 'Validation en cours...';
    });
    refresh();
    const errorSummary = document.querySelector('#checkout-error-summary');
    if (errorSummary) errorSummary.focus();
});
