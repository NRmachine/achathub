document.addEventListener('DOMContentLoaded', () => {
    const mainImage = document.querySelector('[data-gallery-main]');
    document.querySelectorAll('[data-gallery-image]').forEach((button) => button.addEventListener('click', () => {
        if (!mainImage) return;
        mainImage.src = button.dataset.galleryImage;
        document.querySelectorAll('[data-gallery-image]').forEach((item) => item.classList.toggle('active', item === button));
    }));
    const quantity = document.getElementById('product-quantity');
    const buyNowQuantity = document.querySelector('[data-buy-now-quantity]');
    quantity?.addEventListener('input', () => {
        if (buyNowQuantity) buyNowQuantity.value = quantity.value;
    });
});
