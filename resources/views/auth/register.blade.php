@extends('layouts.app')
@section('title', 'Créer un compte - AchatHub')
@section('content')
@php($journey = old('redirect_to', $redirectTo))
<div class="container py-4 py-lg-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="stat-card">
                <h1 class="section-title h3">Créer mon compte</h1>
                <p class="text-secondary">Suivez vos commandes et retrouvez plus vite vos informations lors de vos prochains achats.</p>
                @if($journey === 'checkout')
                    <div class="alert alert-light border small"><i class="bi bi-cart-check text-success me-1" aria-hidden="true"></i> Votre panier est conservé et vous reviendrez à la commande après l’inscription.</div>
                @endif

                <form method="post" action="{{ route('register.store') }}">
                    @csrf
                    @if($journey)<input type="hidden" name="redirect_to" value="{{ $journey }}">@endif

                    <label class="form-label" for="register-name">Nom complet</label>
                    <input id="register-name" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror

                    <label class="form-label mt-3" for="register-email">Adresse e-mail</label>
                    <input id="register-email" class="form-control @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror

                    <label class="form-label mt-3" for="register-phone">Téléphone <span class="text-secondary fw-normal">(facultatif)</span></label>
                    <input id="register-phone" class="form-control @error('phone') is-invalid @enderror" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror

                    <div class="row g-3 mt-0">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="register-password">Mot de passe</label>
                            <input id="register-password" class="form-control @error('password') is-invalid @enderror" type="password" name="password" minlength="12" autocomplete="new-password" required aria-describedby="register-password-help">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="register-password-confirmation">Confirmer le mot de passe</label>
                            <input id="register-password-confirmation" class="form-control" type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required>
                        </div>
                    </div>
                    <div id="register-password-help" class="form-text mb-3">Utilisez au moins 12 caractères, avec des lettres et des chiffres.</div>

                    <div class="form-check mb-3">
                        <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" value="1" id="terms" required @checked(old('terms'))>
                        <label class="form-check-label small" for="terms">J’accepte les <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">conditions générales</a> et je reconnais avoir lu la <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">politique de confidentialité</a>.</label>
                        @error('terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-ah btn-lg w-100" type="submit">Créer mon compte{{ $journey === 'checkout' ? ' et continuer' : '' }}</button>
                </form>

                <p class="text-center mt-3 mb-0">Déjà client ? <a href="{{ route('login', $journey ? ['redirect_to' => $journey] : []) }}">Se connecter</a></p>
                @if($journey === 'checkout')<p class="text-center mt-2 mb-0"><a href="{{ route('checkout.index') }}">Continuer sans compte</a></p>@endif
            </div>
        </div>
    </div>
</div>
@endsection
