@extends('layouts.pro')
@section('title', $product->name.' - AchatHub Pro')
@section('pro-content')
<nav class="small mb-3" aria-label="Fil d’Ariane">
    <a href="{{ route('pro.index') }}">Catalogue Pro</a><i class="bi bi-chevron-right mx-2"></i><a href="{{ route('pro.index', ['category' => $product->category]) }}">{{ $product->category }}</a><i class="bi bi-chevron-right mx-2"></i><span>{{ $product->sku }}</span>
</nav>

<article class="bg-white border rounded p-3 p-lg-4 mb-4">
    <div class="row g-4 g-xl-5">
        <div class="col-lg-5">
            <div class="border rounded bg-white d-flex align-items-center justify-content-center p-3" style="min-height:360px">
                <img class="img-fluid" style="max-height:340px;object-fit:contain" src="{{ $product->image ?: asset('assets/category-accessoires.webp').'?v=20260802b' }}" alt="{{ $product->name }}" decoding="async">
            </div>
        </div>
        <div class="col-lg-7">
            <div class="d-flex flex-wrap gap-2 mb-2"><span class="badge text-bg-light">{{ $product->category }}</span><span class="badge {{ $product->stock >= $product->minimum_order_quantity ? 'text-bg-success' : 'text-bg-warning' }}">{{ $product->stock >= $product->minimum_order_quantity ? 'Commandable immédiatement' : 'Réassort sur demande' }}</span></div>
            <h1 class="h2 fw-bold">{{ $product->name }}</h1>
            <p class="text-secondary">Référence {{ $product->sku }}</p>
            <p>{{ $product->description ?: 'Cette référence est disponible dans le catalogue professionnel AchatHub.' }}</p>

            <div class="row g-2 my-4">
                <div class="col-sm-6"><div class="border rounded p-3 h-100"><small class="text-secondary d-block">PRIX UNITAIRE</small><strong class="fs-3">{{ number_format($product->wholesale_price_ht,2,',',' ') }} € HT</strong><span class="d-block small text-secondary">{{ number_format($product->price_ttc,2,',',' ') }} € TTC</span></div></div>
                <div class="col-sm-6"><div class="border rounded p-3 h-100"><small class="text-secondary d-block">COMMANDE MINIMALE</small><strong class="fs-3">{{ $product->minimum_order_quantity }} unités</strong><span class="d-block small text-secondary">{{ number_format($product->minimum_order_total_ht,2,',',' ') }} € HT le lot</span></div></div>
            </div>

            <dl class="row small border-top border-bottom py-3 mb-4">
                <dt class="col-5 text-secondary">Référence catalogue</dt><dd class="col-7 fw-semibold">{{ $product->sku }}</dd>
                <dt class="col-5 text-secondary">Catégorie</dt><dd class="col-7"><a href="{{ route('pro.index', ['category' => $product->category]) }}">{{ $product->category }}</a></dd>
                <dt class="col-5 text-secondary">Stock disponible</dt><dd class="col-7 fw-semibold">{{ $product->stock }} unités</dd>
                <dt class="col-5 text-secondary mb-0">TVA</dt><dd class="col-7 mb-0">{{ \App\Models\ProfessionalProduct::VAT_RATE }} %</dd>
            </dl>

            @if($product->stock >= $product->minimum_order_quantity)
            <form class="d-flex flex-wrap gap-2" method="post" action="{{ route('pro.cart.products.add',$product) }}">@csrf<label class="visually-hidden" for="pro-product-quantity">Quantité</label><input id="pro-product-quantity" class="form-control" style="max-width:110px" type="number" name="quantity" value="{{ $product->minimum_order_quantity }}" min="{{ $product->minimum_order_quantity }}" max="{{ $product->stock }}"><button class="pro-primary-btn px-4 flex-grow-1"><i class="bi bi-cart-plus me-2"></i>Ajouter au panier professionnel</button></form>
            @else
            <form method="post" action="{{ route('pro.products.preorder',$product) }}">@csrf<button class="pro-primary-btn w-100"><i class="bi bi-send me-2"></i>Demander le prochain réassort</button></form>
            @endif
        </div>
    </div>
</article>

@if($product->displays->isNotEmpty())
<section class="mb-5" id="presentoirs">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3"><div><small class="text-success fw-bold">OFFRES ASSOCIÉES</small><h2 class="h4 fw-bold mb-0">Retrouvez ce produit dans un présentoir</h2></div><a href="{{ route('pro.displays') }}">Comparer tous les présentoirs</a></div>
    <div class="row g-3">
        @foreach($product->displays as $display)
        <div class="col-md-6 col-xl-4"><a class="d-block bg-white border rounded p-3 h-100 text-dark text-decoration-none" href="{{ route('pro.show', $display) }}"><span class="badge text-bg-light mb-2">{{ $display->pivot->quantity }} unité(s) incluse(s)</span><strong class="d-block">{{ $display->name }}</strong><span class="text-secondary small">{{ number_format($display->wholesale_price_ht,2,',',' ') }} € HT · Voir la composition <i class="bi bi-arrow-right"></i></span></a></div>
        @endforeach
    </div>
</section>
@endif

@if($relatedProducts->isNotEmpty())
<section>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3"><div><small class="text-success fw-bold">MÊME CATÉGORIE</small><h2 class="h4 fw-bold mb-0">Autres références {{ str($product->category)->lower() }}</h2></div><a href="{{ route('pro.index', ['category' => $product->category]) }}">Voir toute la catégorie</a></div>
    <div class="pro-products">
        @foreach($relatedProducts as $relatedProduct)
            @include('professional.partials.product-card', ['product' => $relatedProduct])
        @endforeach
    </div>
</section>
@endif
@endsection
