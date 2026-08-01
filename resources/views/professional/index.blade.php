@extends('layouts.pro')
@section('title', 'Catalogue grossiste - AchatHub Pro')
@section('pro-content')
<div class="pro-page-head">
    <div><span class="badge text-bg-success mb-2">Compte professionnel validé</span><h1>Catalogue professionnel</h1><p>Commandez directement les références en gros aux tarifs revendeurs HT.</p></div>
    <a class="btn btn-outline-dark d-none d-md-inline-flex" href="{{ route('pro.displays') }}"><i class="bi bi-box-seam me-2"></i>Voir les présentoirs complets</a>
</div>

<div class="pro-steps">
    <div class="pro-step"><b>1</b><span><strong>Consultez le catalogue</strong>Découvrez les références et leurs tarifs indicatifs HT.</span></div>
    <div class="pro-step"><b>2</b><span><strong>Choisissez vos quantités</strong>Respectez le minimum indiqué pour chaque référence.</span></div>
    <div class="pro-step"><b>3</b><span><strong>Commandez en ligne</strong>Le stock est réservé et votre facture est disponible dans le compte Pro.</span></div>
</div>

<nav class="pro-category-strip" aria-label="Catégories professionnelles">
    <a class="{{ !request('category') ? 'active' : '' }}" href="{{ route('pro.index', request()->only('q')) }}">Tous <span class="opacity-75">{{ $categories->sum('total') }}</span></a>
    @foreach($categories as $category)
        <a class="{{ request('category') === $category->category ? 'active' : '' }}" href="{{ route('pro.index', array_filter(['category'=>$category->category,'q'=>request('q')])) }}">{{ $category->category }} <span class="opacity-75">{{ $category->total }}</span></a>
    @endforeach
</nav>

@if(request('q'))<div class="d-flex justify-content-between align-items-center mb-3"><span>Résultats pour <strong>{{ request('q') }}</strong></span><a href="{{ route('pro.index') }}">Effacer</a></div>@endif

<div class="pro-products">
@forelse($products as $product)
    <article class="pro-product-card">
        <div class="pro-product-image"><img src="{{ $product->image }}" alt="{{ $product->name }}" loading="lazy" decoding="async"><span>{{ $product->stock > 0 ? 'EN STOCK' : 'ÉPUISÉ' }}</span></div>
        <div class="pro-product-body">
            <div class="pro-product-meta">{{ $product->category }} · {{ $product->sku }}</div>
            <h2>{{ $product->name }}</h2>
            <div class="pro-product-price">{{ number_format($product->wholesale_price_ht,2,',',' ') }} € <small>HT / unité</small></div>
            <div class="pro-product-min">Minimum {{ $product->minimum_order_quantity }} unités · Stock {{ $product->stock }}</div>
            @if($product->stock >= $product->minimum_order_quantity)
            <form class="pro-preorder-form d-flex gap-2" method="post" action="{{ route('pro.cart.products.add',$product) }}">@csrf<input class="form-control" style="max-width:90px" type="number" name="quantity" value="{{ $product->minimum_order_quantity }}" min="{{ $product->minimum_order_quantity }}" max="{{ $product->stock }}" aria-label="Quantité de {{ $product->name }}"><button class="flex-grow-1"><i class="bi bi-cart-plus me-2"></i>Ajouter</button></form>
            @else
            <form class="pro-preorder-form" method="post" action="{{ route('pro.products.preorder',$product) }}">@csrf<button><i class="bi bi-send me-2"></i>Précommander le réassort</button></form>
            @endif
        </div>
    </article>
@empty
    <div class="alert alert-light border grid-column-all">Aucun produit ne correspond à votre recherche.</div>
@endforelse
</div>
@if($products->hasPages())
<nav class="pro-load-more" aria-label="Navigation du catalogue professionnel">
    @if(!$products->onFirstPage())<a class="pro-page-back" href="{{ $products->previousPageUrl() }}"><i class="bi bi-arrow-left"></i> Produits précédents</a>@endif
    <span>Page {{ $products->currentPage() }} sur {{ $products->lastPage() }}</span>
    @if($products->hasMorePages())<a class="pro-page-next" href="{{ $products->nextPageUrl() }}">Afficher plus de produits <i class="bi bi-arrow-down"></i></a>@endif
</nav>
@endif
@endsection
