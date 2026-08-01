<article class="shop-card p-3 d-flex flex-column">
    <a class="product-card-image" href="{{ route('products.show',$product) }}"><img src="{{ $product->image ?: asset('assets/category-accessoires.webp') }}" class="product-img" alt="{{ $product->name }}" loading="lazy" decoding="async">@if($product->discount)<span class="badge discount">-{{ $product->discount }} %</span>@endif</a>
    <div class="product-card-brand">{{ $product->brand ?: $product->category?->name }}</div>
    <a class="product-card-name" href="{{ route('products.show',$product) }}">{{ $product->name }}</a>
    @if($product->model)<div class="product-card-compatibility"><i class="bi bi-check-circle"></i> {{ str($product->model)->limit(38) }}</div>@endif
    @if($product->reviews_count)<div class="product-card-rating"><span><i class="bi bi-star-fill"></i> {{ number_format($product->rating,1,',',' ') }}</span><small>{{ $product->reviews_count }} avis</small></div>@endif
    <div class="product-card-price"><span class="price">{{ number_format($product->price,2,',',' ') }} €</span>@if($product->old_price)<span class="old-price">{{ number_format($product->old_price,2,',',' ') }} €</span>@endif</div>
    <div class="product-card-status"><span class="{{ $product->stock ? 'in-stock' : 'out-stock' }}"><i class="bi bi-circle-fill"></i> {{ $product->stock ? 'En stock' : 'Épuisé' }}</span><small><i class="bi bi-truck"></i> 2 à 4 jours</small></div>
    <form method="post" action="{{ route('cart.add',$product) }}" class="mt-auto pt-3 js-add-to-cart">@csrf<button class="btn btn-ah w-100" {{ $product->stock<1?'disabled':'' }}><i class="bi bi-cart-plus"></i><span>{{ $product->stock<1?'Indisponible':'Ajouter au panier' }}</span></button></form>
</article>
