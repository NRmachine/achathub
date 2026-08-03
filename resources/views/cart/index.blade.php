@extends('layouts.app')
@section('title', 'Mon panier - AchatHub')
@section('content')
@php
    $itemCount = (int) $cart['items']->sum('quantity');
    $shippingEstimate = $cart['subtotal'] >= 80 ? 0 : 4.90;
    $estimatedTotal = $cart['subtotal'] + $shippingEstimate;
    $freeShippingRemaining = max(0, 80 - $cart['subtotal']);
@endphp
<div class="container py-4 py-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
        <div>
            <h1 class="section-title mb-1">Mon panier</h1>
            <p class="text-secondary mb-0">{{ $itemCount }} {{ $itemCount > 1 ? 'articles' : 'article' }}</p>
        </div>
        @if($cart['items']->isNotEmpty())<a href="{{ route('home') }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Continuer mes achats</a>@endif
    </div>

    @if($cart['items']->isEmpty())
        <div class="stat-card text-center py-5">
            <i class="bi bi-cart3 display-4 text-secondary" aria-hidden="true"></i>
            <h2 class="h4 mt-3">Votre panier est vide</h2>
            <p class="text-secondary">Découvrez le catalogue et ajoutez les produits qui vous plaisent.</p>
            <a href="{{ route('home') }}" class="btn btn-ah btn-lg">Découvrir les produits</a>
        </div>
    @else
        <div class="row g-4">
            <div class="col-lg-8">
                @foreach($cart['items'] as $item)
                    <article class="stat-card mb-3">
                        <div class="row align-items-center g-3">
                            <div class="col-3 col-md-2"><img class="img-fluid" src="{{ $item['product']->image }}" alt="{{ $item['product']->name }}" loading="lazy" decoding="async"></div>
                            <div class="col-9 col-md-5">
                                <a class="fw-bold text-dark text-decoration-none" href="{{ route('products.show', $item['product']) }}">{{ $item['product']->name }}</a>
                                <div class="text-secondary small">Réf. {{ $item['product']->sku }}</div>
                                <div class="{{ $item['product']->stock <= 5 ? 'text-warning-emphasis' : 'text-success' }} small mt-1"><i class="bi bi-check-circle" aria-hidden="true"></i> {{ $item['product']->stock <= 5 ? 'Plus que '.$item['product']->stock.' en stock' : 'En stock' }}</div>
                            </div>
                            <div class="col-8 col-md-3">
                                <form class="d-flex" method="post" action="{{ route('cart.update', $item['product']) }}">
                                    @csrf @method('patch')
                                    <label class="visually-hidden" for="quantity-{{ $item['product']->id }}">Quantité de {{ $item['product']->name }}</label>
                                    <input id="quantity-{{ $item['product']->id }}" class="form-control" type="number" name="quantity" inputmode="numeric" min="1" max="{{ $item['product']->stock }}" value="{{ $item['quantity'] }}" required>
                                    <button class="btn btn-outline-dark ms-2" type="submit" aria-label="Mettre à jour la quantité de {{ $item['product']->name }}"><i class="bi bi-arrow-repeat" aria-hidden="true"></i><span class="visually-hidden">Mettre à jour</span></button>
                                </form>
                            </div>
                            <div class="col-8 col-md-1 fw-bold text-nowrap">{{ number_format($item['total'], 2, ',', ' ') }} €</div>
                            <div class="col-4 col-md-1 text-end"><form method="post" action="{{ route('cart.remove', $item['product']) }}">@csrf @method('delete')<button class="btn btn-outline-danger" type="submit" aria-label="Supprimer {{ $item['product']->name }} du panier"><i class="bi bi-trash" aria-hidden="true"></i></button></form></div>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="col-lg-4">
                <aside class="stat-card position-sticky" style="top:150px" aria-labelledby="cart-summary-title">
                    <h2 id="cart-summary-title" class="h5 fw-bold">Récapitulatif</h2>
                    <div class="d-flex justify-content-between pt-3 pb-2"><span>Sous-total</span><strong>{{ number_format($cart['subtotal'], 2, ',', ' ') }} €</strong></div>
                    <div class="d-flex justify-content-between pb-3 border-bottom"><span>Livraison standard</span><strong>{{ $shippingEstimate > 0 ? number_format($shippingEstimate, 2, ',', ' ').' €' : 'Offerte' }}</strong></div>
                    <div class="d-flex justify-content-between py-3 fs-5"><strong>Total estimé</strong><strong>{{ number_format($estimatedTotal, 2, ',', ' ') }} €</strong></div>
                    @if($freeShippingRemaining > 0)
                        <p class="small text-secondary"><i class="bi bi-truck me-1" aria-hidden="true"></i> Ajoutez {{ number_format($freeShippingRemaining, 2, ',', ' ') }} € pour profiter de la livraison standard offerte.</p>
                    @else
                        <p class="small text-success"><i class="bi bi-truck me-1" aria-hidden="true"></i> Livraison standard offerte.</p>
                    @endif
                    <a class="btn btn-ah btn-lg w-100" href="{{ route('checkout.index') }}">Continuer vers la livraison <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
                    <div class="small text-secondary mt-3 d-grid gap-2">
                        <span><i class="bi bi-person-check text-success me-1" aria-hidden="true"></i> Commande sans compte possible</span>
                        <span><i class="bi bi-shield-check text-success me-1" aria-hidden="true"></i> Données et paiement sécurisés</span>
                    </div>
                </aside>
            </div>
        </div>
    @endif
</div>
@endsection
