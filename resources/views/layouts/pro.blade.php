<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AchatHub Pro')</title>
    <meta name="description" content="Espace professionnel AchatHub pour commander des produits grossistes et des présentoirs prêts à vendre.">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/achathub-pro.css') }}" rel="stylesheet">
    <link href="{{ asset('css/achathub-pro-mobile.css') }}" rel="stylesheet">
    <link href="{{ asset('css/achathub-management.css') }}" rel="stylesheet">
    <link href="{{ asset('css/achathub-pro-commercial.css') }}" rel="stylesheet">
</head>
<body>
@php
    $proCart = session('professional_cart', []);
    $proCartCount = collect($proCart['displays'] ?? (isset($proCart['products']) ? [] : $proCart))->sum();
    $proLinks = [
        ['route' => 'pro.index', 'label' => 'Produits grossistes', 'icon' => 'bi-grid'],
        ['route' => 'pro.displays', 'label' => 'Présentoirs complets', 'icon' => 'bi-box-seam'],
        ['route' => 'pro.cart', 'label' => 'Panier professionnel', 'icon' => 'bi-cart3'],
        ['route' => 'pro.account', 'label' => 'Commandes et compte', 'icon' => 'bi-receipt'],
        ['route' => 'messages.index', 'label' => 'Messagerie sécurisée', 'icon' => 'bi-chat-dots'],
        ['route' => 'data-rights.index', 'label' => 'Mes données et droits', 'icon' => 'bi-shield-check'],
    ];
@endphp

<header class="pro-header sticky-top">
    <div class="container-fluid px-3 px-lg-4 py-2">
        <div class="d-flex align-items-center gap-3">
            <a class="pro-brand" href="{{ route('pro.index') }}"><img src="{{ asset('assets/achathub-mark.webp') }}" width="38" height="38" alt=""><span>AchatHub <strong>PRO</strong></span></a>
            <form class="pro-search d-none d-md-flex" action="{{ route('pro.index') }}">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input name="q" value="{{ request('q') }}" placeholder="Rechercher une référence ou un produit" aria-label="Rechercher dans le catalogue professionnel">
            </form>
            <div class="ms-auto d-flex align-items-center gap-2">
                <a class="pro-header-action d-none d-lg-flex" href="{{ route('home') }}"><i class="bi bi-shop"></i> Boutique classique</a>
                <a class="pro-header-action d-none d-lg-flex" href="{{ route('pro.account') }}"><i class="bi bi-person"></i><span>{{ auth()->user()->name }}</span></a>
                <a class="pro-cart-action" href="{{ route('pro.cart') }}" aria-label="Panier professionnel"><i class="bi bi-cart3"></i><span>{{ $proCartCount }}</span></a>
            </div>
        </div>
        <form class="pro-search d-flex d-md-none mt-2" action="{{ route('pro.index') }}">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input name="q" value="{{ request('q') }}" placeholder="Produit ou référence" aria-label="Rechercher dans le catalogue professionnel">
        </form>
    </div>
</header>

<div class="offcanvas offcanvas-end pro-mobile-menu d-lg-none" tabindex="-1" id="proMobileMenu" aria-labelledby="proMobileMenuTitle">
    <div class="offcanvas-header border-bottom">
        <div><small class="text-success fw-bold">COMPTE PROFESSIONNEL</small><h2 class="h5 mb-0" id="proMobileMenuTitle">{{ auth()->user()->resellerRequest?->business_name }}</h2></div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <nav class="pro-mobile-menu-links" aria-label="Menu du compte professionnel">
            @foreach($proLinks as $link)
                <a class="{{ request()->routeIs($link['route']) ? 'active' : '' }}" href="{{ route($link['route']) }}"><i class="bi {{ $link['icon'] }}"></i><span>{{ $link['label'] }}</span><i class="bi bi-chevron-right ms-auto"></i></a>
            @endforeach
        </nav>
        <div class="mt-auto pt-3 border-top">
            <a class="pro-switch-shop" href="{{ route('home') }}"><i class="bi bi-shop"></i> Voir la boutique classique</a>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="pro-logout-btn"><i class="bi bi-box-arrow-right"></i> Se déconnecter</button></form>
        </div>
    </div>
</div>

<div class="pro-shell">
    <aside class="pro-sidebar d-none d-lg-flex">
        <div class="pro-business"><small>ESPACE VALIDÉ</small><strong>{{ auth()->user()->resellerRequest?->business_name }}</strong><span>Tarifs professionnels HT</span></div>
        <nav aria-label="Navigation professionnelle">
            @foreach($proLinks as $link)
                <a class="{{ request()->routeIs($link['route']) ? 'active' : '' }}" href="{{ route($link['route']) }}"><i class="bi {{ $link['icon'] }}"></i>{{ $link['label'] }}</a>
            @endforeach
        </nav>
        <div class="mt-auto">
            <a href="{{ route('home') }}"><i class="bi bi-arrow-left-right"></i>Passer à la boutique</a>
            <form method="post" action="{{ route('logout') }}">@csrf<button><i class="bi bi-box-arrow-right"></i>Se déconnecter</button></form>
        </div>
    </aside>

    <main class="pro-main">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><strong>Vérifiez les informations :</strong> {{ $errors->first() }}</div>@endif
        @yield('pro-content')
    </main>
</div>

<nav class="pro-mobile-nav d-lg-none" aria-label="Navigation professionnelle mobile">
    <a class="{{ request()->routeIs('pro.index') ? 'active' : '' }}" href="{{ route('pro.index') }}"><i class="bi bi-grid"></i><span>Produits</span></a>
    <a class="{{ request()->routeIs('pro.displays') || request()->routeIs('pro.show') ? 'active' : '' }}" href="{{ route('pro.displays') }}"><i class="bi bi-box-seam"></i><span>Présentoirs</span></a>
    <a class="{{ request()->routeIs('pro.cart') || request()->routeIs('pro.checkout') ? 'active' : '' }}" href="{{ route('pro.cart') }}"><i class="bi bi-cart3"></i><span>Panier</span>@if($proCartCount)<b>{{ $proCartCount }}</b>@endif</a>
    <button type="button" data-bs-toggle="offcanvas" data-bs-target="#proMobileMenu" aria-controls="proMobileMenu"><i class="bi bi-list"></i><span>Menu</span></button>
</nav>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
@include('components.cookie-banner')
</body>
</html>
