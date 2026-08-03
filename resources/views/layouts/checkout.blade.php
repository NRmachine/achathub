<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Commande sécurisée - AchatHub')</title>
    <meta name="robots" content="noindex,follow">
    @include('components.brand-head')
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}?v=5.3.8" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}?v=1.11.3" rel="stylesheet">
    <link href="{{ asset('css/achathub.css') }}?v=20260802b" rel="stylesheet">
    <link href="{{ asset('css/achathub-commerce.css') }}?v=20260802b" rel="stylesheet">
    <link href="{{ asset('css/achathub-design.css') }}?v=20260803a" rel="stylesheet">
</head>
<body class="checkout-body">
@include('components.brand-loader')
<header class="checkout-header"><div class="container d-flex align-items-center justify-content-between py-3"><a href="{{ route('home') }}" class="checkout-brand" aria-label="AchatHub - Accueil"><img src="{{ asset('assets/achathub-logo.webp') }}?v=20260803b" width="42" height="37" alt=""><strong>AchatHub</strong></a><span class="checkout-secure"><i class="bi bi-shield-check"></i> Paiement sécurisé</span></div></header>
@if($errors->any())
<div class="container pt-3">
    <div class="alert alert-danger mb-0" role="alert" aria-live="assertive" tabindex="-1" id="checkout-error-summary">
        <strong>Vérifiez les informations indiquées.</strong>
        <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
</div>
@endif
<main>@yield('content')</main>
<footer class="checkout-footer"><div class="container d-flex flex-wrap justify-content-between gap-2"><small>© {{ date('Y') }} AchatHub</small><div><a href="{{ route('legal.terms') }}">CGV</a><a href="{{ route('legal.privacy') }}">Confidentialité</a><a href="{{ route('support.index') }}">Aide</a></div></div></footer>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}?v=5.3.8" defer></script>
@stack('scripts')
</body>
</html>
