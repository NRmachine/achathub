@extends('layouts.app')
@section('title', 'Mot de passe oublié - AchatHub')
@section('content')
<div class="container py-5"><div class="row justify-content-center"><div class="col-md-6 col-lg-5"><div class="stat-card">
    <span class="badge text-bg-light mb-2">ACCÈS SÉCURISÉ</span>
    <h1 class="section-title h3">Mot de passe oublié</h1>
    <p class="text-secondary">Indiquez l’e-mail de votre compte client ou professionnel. Le lien reçu sera temporaire.</p>
    <form method="post" action="{{ route('password.email') }}">@csrf
        <label class="form-label" for="reset_email">Adresse e-mail</label>
        <input id="reset_email" class="form-control mb-3" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        <button class="btn btn-ah w-100">Recevoir le lien sécurisé</button>
    </form>
    <a class="d-block text-center mt-3" href="{{ route('login') }}">Retour à la connexion</a>
</div></div></div></div>
@endsection
