@extends('layouts.app')
@section('title', 'Connexion professionnelle - AchatHub Pro')
@section('content')
<section class="professional-login-shell">
    <div class="container py-5">
        <div class="row justify-content-center align-items-center g-4">
            <div class="col-lg-5">
                <span class="badge text-bg-warning mb-3">ACCÈS SÉCURISÉ</span>
                <h1 class="display-6 fw-bold">AchatHub Pro</h1>
                <p class="lead text-secondary">Un portail séparé pour vos tarifs professionnels, précommandes et présentoirs.</p>
                <div class="professional-login-points">
                    <span><i class="bi bi-shield-check"></i> Compte entreprise vérifié</span>
                    <span><i class="bi bi-tags"></i> Tarifs réservés aux revendeurs</span>
                    <span><i class="bi bi-headset"></i> Suivi par notre équipe professionnelle</span>
                </div>
            </div>
            <div class="col-md-7 col-lg-5">
                <div class="professional-login-card">
                    <h2 class="h3 fw-bold">Connexion professionnelle</h2>
                    <p class="text-secondary">Utilisez les identifiants de votre entreprise.</p>
                    <form method="post" action="{{ route('professional.login.store') }}">
                        @csrf
                        <label class="form-label" for="professional_email">E-mail professionnel</label>
                        <input id="professional_email" class="form-control mb-3" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                        <label class="form-label" for="professional_password">Mot de passe</label>
                        <input id="professional_password" class="form-control mb-2" type="password" name="password" autocomplete="current-password" required>
                        <div class="text-end mb-3"><a class="small" href="{{ route('password.request') }}">Mot de passe oublié ?</a></div>
                        <div class="form-check mb-3"><input id="professional_remember" class="form-check-input" type="checkbox" name="remember"><label class="form-check-label" for="professional_remember">Rester connecté</label></div>
                        <button class="btn btn-warning btn-lg w-100">Accéder à AchatHub Pro</button>
                    </form>
                    <div class="professional-login-links"><a href="{{ route('professional.register') }}">Créer un compte professionnel</a><a href="{{ route('login') }}">Accès client particulier</a></div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
