@extends('layouts.app')
@section('title', 'Connexion administration - AchatHub')
@section('body-class', 'admin-login-body')
@section('content')
<section class="admin-login-shell">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5 col-xl-4">
                <div class="admin-login-card">
                    <div class="admin-login-mark"><i class="bi bi-shield-lock"></i></div>
                    <span>ACCÈS INTERNE</span>
                    <h1>Administration AchatHub</h1>
                    <p>Gérez les produits, les commandes, les clients et l’agent fournisseur depuis un espace protégé.</p>

                    @if($errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif

                    <form method="post" action="{{ route('admin.login.store') }}">@csrf
                        <label class="form-label" for="admin_email">E-mail administrateur</label>
                        <input id="admin_email" class="form-control mb-3" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                        <label class="form-label" for="admin_password">Mot de passe</label>
                        <input id="admin_password" class="form-control mb-3" type="password" name="password" autocomplete="current-password" required>
                        <div class="form-check mb-4"><input id="admin_remember" class="form-check-input" type="checkbox" name="remember"><label class="form-check-label" for="admin_remember">Garder cette session ouverte</label></div>
                        <button class="btn btn-ah w-100"><i class="bi bi-box-arrow-in-right me-1"></i> Ouvrir l’administration</button>
                    </form>

                    <a class="admin-login-back" href="{{ route('home') }}"><i class="bi bi-arrow-left"></i> Retour à la boutique</a>
                </div>
                <p class="admin-login-security"><i class="bi bi-lock"></i> Accès réservé aux responsables autorisés.</p>
            </div>
        </div>
    </div>
</section>
@endsection
