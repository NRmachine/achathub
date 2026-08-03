<article class="pro-product-card">
    <a class="pro-product-image" href="{{ route('pro.products.show', $product) }}">
        <img src="{{ $product->image ?: asset('assets/category-accessoires.webp').'?v=20260802b' }}" alt="{{ $product->name }}" loading="lazy" decoding="async">
        <span>{{ $product->stock >= $product->minimum_order_quantity ? 'EN STOCK' : 'SUR PRÉCOMMANDE' }}</span>
    </a>
    <div class="pro-product-body">
        <div class="pro-product-meta">{{ $product->category }} · {{ $product->sku }}</div>
        <h2><a class="text-dark text-decoration-none" href="{{ route('pro.products.show', $product) }}">{{ $product->name }}</a></h2>
        @if($product->description)<p class="small text-secondary mb-2">{{ str($product->description)->limit(105) }}</p>@endif
        <div class="pro-product-price">{{ number_format($product->wholesale_price_ht,2,',',' ') }} € <small>HT / unité</small></div>
        <div class="pro-product-min">Lot minimum {{ $product->minimum_order_quantity }} unités · {{ number_format($product->minimum_order_total_ht,2,',',' ') }} € HT</div>
        <div class="d-flex justify-content-between align-items-center gap-2 small mb-3">
            <span class="{{ $product->stock >= $product->minimum_order_quantity ? 'text-success' : 'text-warning' }}"><i class="bi bi-box-seam me-1"></i>Stock {{ $product->stock }}</span>
            @if(($product->displays_count ?? 0) > 0)<a href="{{ route('pro.products.show', $product) }}#presentoirs">Dans {{ $product->displays_count }} présentoir(s)</a>@endif
        </div>
        @if($product->stock >= $product->minimum_order_quantity)
        <form class="pro-preorder-form d-flex gap-2" method="post" action="{{ route('pro.cart.products.add',$product) }}">@csrf<input class="form-control" style="max-width:90px" type="number" name="quantity" value="{{ $product->minimum_order_quantity }}" min="{{ $product->minimum_order_quantity }}" max="{{ $product->stock }}" aria-label="Quantité de {{ $product->name }}"><button class="flex-grow-1"><i class="bi bi-cart-plus me-2"></i>Ajouter au panier</button></form>
        @else
        <form class="pro-preorder-form" method="post" action="{{ route('pro.products.preorder',$product) }}">@csrf<button><i class="bi bi-send me-2"></i>Précommander le réassort</button></form>
        @endif
        <a class="btn btn-sm btn-link text-dark w-100 mt-2" href="{{ route('pro.products.show', $product) }}">Voir la fiche et les offres associées <i class="bi bi-arrow-right"></i></a>
    </div>
</article>
