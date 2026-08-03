@extends('layouts.checkout')
@section('title', 'Finaliser ma commande - AchatHub')
@section('content')
@php($customer = auth()->user())
<div class="container checkout-page py-4 py-lg-5">
    <ol class="checkout-steps" aria-label="Étapes de commande">
        <li class="done"><i class="bi bi-check" aria-hidden="true"></i><span>Panier</span></li>
        <li class="active" aria-current="step"><b>2</b><span>Livraison et paiement</span></li>
        <li><b>3</b><span>Confirmation</span></li>
    </ol>

    <form method="post" action="{{ route('checkout.store') }}" data-checkout-form>
        @csrf
        <div class="row g-4 align-items-start">
            <div class="col-lg-7">
                <section class="checkout-section" aria-labelledby="contact-title">
                    <div class="checkout-section-title"><span>1</span><div><h1 id="contact-title">Vos coordonnées</h1><p>Pour la livraison et le suivi de votre commande.</p></div></div>
                    @guest
                        <div class="checkout-login-note"><i class="bi bi-person-check" aria-hidden="true"></i><span>Vous pouvez commander sans compte. Déjà client ? <a href="{{ route('login', ['redirect_to' => 'checkout']) }}">Connectez-vous sans perdre votre panier</a>.</span></div>
                    @else
                        @if($savedAddress)<div class="alert alert-light border small"><i class="bi bi-geo-alt text-success me-1" aria-hidden="true"></i> Votre adresse de livraison habituelle a été préremplie. Vous pouvez la modifier ci-dessous.</div>@endif
                    @endguest
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="checkout-email">Adresse e-mail</label>
                            <input id="checkout-email" class="form-control @error('email') is-invalid @enderror" type="email" name="email" autocomplete="email" value="{{ old('email', $customer?->email) }}" required aria-describedby="checkout-email-help @error('email') checkout-email-error @enderror">
                            <div id="checkout-email-help" class="form-text">La confirmation et le suivi seront envoyés à cette adresse.</div>
                            @error('email')<div id="checkout-email-error" class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="checkout-name">Nom complet</label>
                            <input id="checkout-name" class="form-control @error('name') is-invalid @enderror" name="name" autocomplete="name" value="{{ old('name', $savedAddress?->name ?? $customer?->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="checkout-phone">Téléphone</label>
                            <input id="checkout-phone" class="form-control @error('phone') is-invalid @enderror" type="tel" name="phone" autocomplete="tel" inputmode="tel" value="{{ old('phone', $savedAddress?->phone ?? $customer?->phone) }}" required>
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="checkout-address">Adresse et numéro de voie</label>
                            <input id="checkout-address" class="form-control @error('address') is-invalid @enderror" name="address" autocomplete="street-address" value="{{ old('address', $savedAddress?->address ?? $customer?->address) }}" required>
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label" for="checkout-postal">Code postal</label>
                            <input id="checkout-postal" class="form-control @error('postal_code') is-invalid @enderror" name="postal_code" autocomplete="postal-code" inputmode="numeric" maxlength="10" value="{{ old('postal_code', $savedAddress?->postal_code) }}" required>
                            @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-7">
                            <label class="form-label" for="checkout-city">Ville</label>
                            <input id="checkout-city" class="form-control @error('city') is-invalid @enderror" name="city" autocomplete="address-level2" value="{{ old('city', $savedAddress?->city) }}" required>
                            @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @auth
                            <div class="col-12"><div class="form-check"><input id="save-address" class="form-check-input" type="checkbox" name="save_address" value="1" @checked(old('save_address', true))><label class="form-check-label" for="save-address">Enregistrer cette adresse pour ma prochaine commande</label></div></div>
                        @endauth
                    </div>
                </section>

                <section class="checkout-section" aria-labelledby="shipping-title">
                    <div class="checkout-section-title"><span>2</span><div><h2 id="shipping-title">Mode de livraison</h2><p>Le prix et la date estimée sont affichés avant validation.</p></div></div>
                    <div class="checkout-options">
                        @foreach($shippingOptions as $key => $option)
                            <label class="checkout-option">
                                <input type="radio" name="shipping_method" value="{{ $key }}" data-shipping-price="{{ (int) round($option['price'] * 100) }}" @checked(old('shipping_method', 'standard') === $key) required>
                                <span class="checkout-option-copy"><strong>{{ $option['label'] }}</strong><small>Livraison estimée le {{ $option['date']->translatedFormat('l j F') }}</small></span>
                                <b>{{ $option['price'] > 0 ? number_format($option['price'], 2, ',', ' ').' €' : 'Offerte' }}</b>
                            </label>
                        @endforeach
                    </div>
                    @error('shipping_method')<div class="text-danger small mt-2" role="alert">{{ $message }}</div>@enderror
                </section>

                <section class="checkout-section" aria-labelledby="payment-title">
                    <div class="checkout-section-title"><span>3</span><div><h2 id="payment-title">Paiement</h2><p>Aucun débit n’est effectué avant votre confirmation.</p></div></div>
                    <div class="checkout-options payment-options">
                        @foreach($paymentOptions as $key => $payment)
                            <label class="checkout-option payment-option">
                                <input type="radio" name="payment_method" value="{{ $key }}" @checked(old('payment_method', array_key_first($paymentOptions)) === $key) required>
                                <i class="bi {{ $payment[2] }}" aria-hidden="true"></i>
                                <span class="checkout-option-copy"><strong>{{ $payment[0] }}</strong><small>{{ $payment[1] }}</small></span>
                            </label>
                        @endforeach
                    </div>
                    @error('payment_method')<div class="text-danger small mt-2" role="alert">{{ $message }}</div>@enderror
                    <label class="form-label mt-3" for="checkout-notes">Instruction de livraison <span class="text-secondary fw-normal">(facultatif)</span></label>
                    <textarea id="checkout-notes" class="form-control @error('notes') is-invalid @enderror" name="notes" rows="2" maxlength="1000" placeholder="Code d'accès, étage, précision utile...">{{ old('notes') }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </section>
            </div>

            <div class="col-lg-5">
                <aside class="checkout-summary" aria-labelledby="order-summary-title">
                    <div class="d-flex justify-content-between align-items-center mb-3"><h2 id="order-summary-title">Votre commande</h2><a href="{{ route('cart.index') }}">Modifier le panier</a></div>
                    <div class="checkout-summary-items">
                        @foreach($cart['items'] as $item)
                            <div class="checkout-summary-item"><img src="{{ $item['product']->image }}" alt="" loading="lazy" decoding="async"><span><strong>{{ str($item['product']->name)->limit(48) }}</strong><small>Quantité : {{ $item['quantity'] }}</small></span><b>{{ number_format($item['total'], 2, ',', ' ') }} €</b></div>
                        @endforeach
                    </div>
                    <div class="checkout-totals" data-subtotal-cents="{{ (int) round($cart['subtotal'] * 100) }}">
                        <div><span>Sous-total</span><strong>{{ number_format($cart['subtotal'], 2, ',', ' ') }} €</strong></div>
                        <div><span>Livraison</span><strong data-checkout-shipping>Calcul en cours</strong></div>
                        <div class="checkout-grand-total"><span>Total TTC</span><strong data-checkout-total>{{ number_format($cart['subtotal'], 2, ',', ' ') }} €</strong></div>
                    </div>
                    <button class="btn btn-ah btn-lg w-100 checkout-submit" type="submit"><i class="bi bi-lock" aria-hidden="true"></i> <span>Commander avec obligation de paiement</span> <b data-checkout-button-total></b></button>
                    <p class="checkout-legal">Vous pourrez vérifier le récapitulatif ci-dessus avant de confirmer. En validant, vous acceptez les <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">conditions générales de vente</a>.</p>
                    <div class="checkout-trust"><span><i class="bi bi-shield-check" aria-hidden="true"></i> Données protégées</span><span><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Retours simplifiés</span></div>
                    <p class="text-center small mt-3 mb-0"><a href="{{ route('support.index') }}" target="_blank" rel="noopener">Une question avant de commander ?</a></p>
                </aside>
            </div>
        </div>
    </form>
</div>
@endsection
@push('scripts')<script src="{{ asset('js/checkout.js') }}?v=20260803a" defer></script>@endpush
