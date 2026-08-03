@extends('layouts.app')
@section('title', 'Connexion client - AchatHub')
@section('content')
@php($journey = old('redirect_to', $redirectTo))
<div class="container py-4 py-lg-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="stat-card">
                <span class="badge text-bg-light mb-2">CLIENT PARTICULIER</span>
                <h1 class="section-title h3">Connexion client</h1>
                <p class="text-secondary">Accédez à vos commandes, favoris et informations enregistrées.</p>

                @if($journey === 'checkout')
                    <div class="alert alert-light border d-flex gap-2 align-items-start" role="status">
                        <i class="bi bi-cart-check text-success" aria-hidden="true"></i>
                        <span>Votre panier est conservé. Après connexion, vous reviendrez directement à votre commande.</span>
                    </div>
                @endif

                <form method="post" action="{{ route('login.store') }}">
                    @csrf
                    @if($journey)<input type="hidden" name="redirect_to" value="{{ $journey }}">@endif

                    <label class="form-label" for="customer-email">Adresse e-mail</label>
                    <input id="customer-email" class="form-control @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus aria-describedby="@error('email') customer-email-error @enderror">
                    @error('email')<div id="customer-email-error" class="invalid-feedback">{{ $message }}</div>@enderror

                    <div class="d-flex justify-content-between align-items-end mt-3">
                        <label class="form-label mb-0" for="customer-password">Mot de passe</label>
                        <a class="small" href="{{ route('password.request') }}">Mot de passe oublié ?</a>
                    </div>
                    <input id="customer-password" class="form-control mt-2 @error('password') is-invalid @enderror" type="password" name="password" autocomplete="current-password" required aria-describedby="@error('password') customer-password-error @enderror">
                    @error('password')<div id="customer-password-error" class="invalid-feedback">{{ $message }}</div>@enderror

                    <div class="form-check my-3">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                        <label class="form-check-label" for="remember">Rester connecté sur cet appareil</label>
                    </div>
                    <button class="btn btn-ah btn-lg w-100" type="submit">Se connecter{{ $journey === 'checkout' ? ' et continuer' : ' à mon compte' }}</button>
                </form>

                <hr>
                <p class="text-center mb-2">Nouveau client ? <a href="{{ route('register', $journey ? ['redirect_to' => $journey] : []) }}">Créer un compte</a></p>
                @if($journey === 'checkout')
                    <a class="btn btn-outline-dark w-100 mb-2" href="{{ route('checkout.index') }}">Continuer sans compte</a>
                @else
                    <a class="btn btn-outline-dark w-100" href="{{ route('professional.login') }}">Connexion professionnelle</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
