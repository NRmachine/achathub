<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Commande sécurisée - AchatHub')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/achathub.css') }}" rel="stylesheet">
    <link href="{{ asset('css/achathub-commerce.css') }}" rel="stylesheet">
</head>
<body class="checkout-body">
<header class="checkout-header"><div class="container d-flex align-items-center justify-content-between py-3"><a href="{{ route('home') }}" class="checkout-brand" aria-label="AchatHub - Accueil"><img src="{{ asset('assets/achathub-mark.webp') }}" width="42" height="42" alt=""><strong>AchatHub</strong></a><span class="checkout-secure"><i class="bi bi-shield-check"></i> Paiement sécurisé</span></div></header>
@if($errors->any())<div class="container pt-3"><div class="alert alert-danger mb-0"><strong>Certains champs sont à vérifier.</strong><br>{{ $errors->first() }}</div></div>@endif
<main>@yield('content')</main>
<footer class="checkout-footer"><div class="container d-flex flex-wrap justify-content-between gap-2"><small>© {{ date('Y') }} AchatHub</small><div><a href="{{ route('legal.terms') }}">CGV</a><a href="{{ route('legal.privacy') }}">Confidentialité</a><a href="{{ route('support.index') }}">Aide</a></div></div></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
