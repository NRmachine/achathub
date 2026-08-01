@extends('layouts.checkout')
@section('title', 'Finaliser ma commande - AchatHub')
@section('content')
@php($customer = auth()->user())
<div class="container checkout-page py-4 py-lg-5">
    <ol class="checkout-steps" aria-label="Étapes de commande"><li class="done"><i class="bi bi-check"></i><span>Panier</span></li><li class="active"><b>2</b><span>Coordonnées</span></li><li><b>3</b><span>Livraison</span></li><li><b>4</b><span>Paiement</span></li></ol>
    <form method="post" action="{{ route('checkout.store') }}" data-checkout-form>
        @csrf
        <div class="row g-4 align-items-start">
            <div class="col-lg-7">
                <section class="checkout-section">
                    <div class="checkout-section-title"><span>1</span><div><h1>Vos coordonnées</h1><p>Pour la livraison et le suivi de votre commande.</p></div></div>
                    @guest<div class="checkout-login-note"><i class="bi bi-person-check"></i><span>Vous avez déjà un compte ? <a href="{{ route('login') }}">Connectez-vous</a>. Vous pouvez aussi commander sans compte.</span></div>@endguest
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label" for="checkout-email">Adresse e-mail</label><input id="checkout-email" class="form-control" type="email" name="email" autocomplete="email" value="{{ old('email', $customer?->email) }}" required><div class="form-text">La confirmation et le suivi seront envoyés à cette adresse.</div></div>
                        <div class="col-md-7"><label class="form-label" for="checkout-name">Nom complet</label><input id="checkout-name" class="form-control" name="name" autocomplete="name" value="{{ old('name', $customer?->name) }}" required></div>
                        <div class="col-md-5"><label class="form-label" for="checkout-phone">Téléphone</label><input id="checkout-phone" class="form-control" type="tel" name="phone" autocomplete="tel" value="{{ old('phone', $customer?->phone) }}" required></div>
                        <div class="col-12"><label class="form-label" for="checkout-address">Adresse</label><input id="checkout-address" class="form-control" name="address" autocomplete="street-address" value="{{ old('address', $customer?->address) }}" required></div>
                        <div class="col-sm-5"><label class="form-label" for="checkout-postal">Code postal</label><input id="checkout-postal" class="form-control" name="postal_code" autocomplete="postal-code" value="{{ old('postal_code') }}" required></div>
                        <div class="col-sm-7"><label class="form-label" for="checkout-city">Ville</label><input id="checkout-city" class="form-control" name="city" autocomplete="address-level2" value="{{ old('city') }}" required></div>
                    </div>
                </section>
                <section class="checkout-section">
                    <div class="checkout-section-title"><span>2</span><div><h2>Mode de livraison</h2><p>Choisissez le délai qui vous convient.</p></div></div>
                    <div class="checkout-options">@foreach($shippingOptions as $key => $option)<label class="checkout-option"><input type="radio" name="shipping_method" value="{{ $key }}" data-shipping-price="{{ (int) round($option['price'] * 100) }}" @checked(old('shipping_method', 'standard') === $key)><span class="checkout-option-copy"><strong>{{ $option['label'] }}</strong><small>Livraison estimée le {{ $option['date']->translatedFormat('l j F') }}</small></span><b>{{ $option['price'] > 0 ? number_format($option['price'], 2, ',', ' ').' €' : 'Offerte' }}</b></label>@endforeach</div>
                </section>
                <section class="checkout-section">
                    <div class="checkout-section-title"><span>3</span><div><h2>Paiement</h2><p>Choisissez un moyen de paiement disponible.</p></div></div>
                    <div class="checkout-options payment-options">@foreach($paymentOptions as $key => $payment)<label class="checkout-option payment-option"><input type="radio" name="payment_method" value="{{ $key }}" @checked(old('payment_method', array_key_first($paymentOptions)) === $key)><i class="bi {{ $payment[2] }}"></i><span class="checkout-option-copy"><strong>{{ $payment[0] }}</strong><small>{{ $payment[1] }}</small></span></label>@endforeach</div>
                    <label class="form-label mt-3" for="checkout-notes">Instruction de livraison <span class="text-secondary fw-normal">(facultatif)</span></label><textarea id="checkout-notes" class="form-control" name="notes" rows="2" maxlength="1000" placeholder="Code d'accès, étage, précision utile...">{{ old('notes') }}</textarea>
                </section>
            </div>
            <div class="col-lg-5"><aside class="checkout-summary"><div class="d-flex justify-content-between align-items-center mb-3"><h2>Votre commande</h2><a href="{{ route('cart.index') }}">Modifier</a></div><div class="checkout-summary-items">@foreach($cart['items'] as $item)<div class="checkout-summary-item"><img src="{{ $item['product']->image }}" alt=""><span><strong>{{ str($item['product']->name)->limit(48) }}</strong><small>Quantité : {{ $item['quantity'] }}</small></span><b>{{ number_format($item['total'], 2, ',', ' ') }} €</b></div>@endforeach</div><div class="checkout-totals" data-subtotal-cents="{{ (int) round($cart['subtotal'] * 100) }}"><div><span>Sous-total</span><strong>{{ number_format($cart['subtotal'], 2, ',', ' ') }} €</strong></div><div><span>Livraison</span><strong data-checkout-shipping>0,00 €</strong></div><div class="checkout-grand-total"><span>Total TTC</span><strong data-checkout-total>{{ number_format($cart['subtotal'], 2, ',', ' ') }} €</strong></div></div><button class="btn btn-ah btn-lg w-100 checkout-submit" type="submit"><i class="bi bi-lock"></i> <span>Confirmer la commande</span> <b data-checkout-button-total></b></button><p class="checkout-legal">En validant, vous acceptez les <a href="{{ route('legal.terms') }}" target="_blank">conditions générales de vente</a>.</p><div class="checkout-trust"><span><i class="bi bi-shield-check"></i> Données protégées</span><span><i class="bi bi-arrow-counterclockwise"></i> Retours simplifiés</span></div></aside></div>
        </div>
    </form>
</div>
@endsection
@push('scripts')<script src="{{ asset('js/checkout.js') }}" defer></script>@endpush
