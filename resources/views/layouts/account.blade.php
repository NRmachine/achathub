@extends('layouts.app')

@section('content')
@php
    $accountLinks = [
        ['route' => 'account.index', 'label' => "Vue d'ensemble"],
        ['route' => 'account.orders', 'label' => 'Mes commandes'],
        ['route' => 'account.wishlist', 'label' => 'Mes favoris'],
        ['route' => 'account.settings', 'label' => 'Mes informations'],
        ['route' => 'messages.index', 'label' => 'Messagerie'],
        ['route' => 'data-rights.index', 'label' => 'Mes données et droits'],
        ['route' => 'reseller.dashboard', 'label' => 'Espace revendeur'],
    ];

    if (auth()->user()->role === 'reseller') {
        $accountLinks[] = ['route' => 'pro.index', 'label' => 'Catalogue professionnel'];
        $accountLinks[] = ['route' => 'pro.cart', 'label' => 'Panier professionnel'];
    }

@endphp

<div class="container-fluid px-0">
    <details class="dashboard-mobile-menu d-md-none bg-white border-bottom">
        <summary class="d-flex align-items-center justify-content-between px-3 py-3 fw-bold">
            <span>Menu du compte</span>
            <span class="menu-chevron" aria-hidden="true">+</span>
        </summary>
        <nav class="px-3 pb-3" aria-label="Navigation du compte mobile">
            @foreach($accountLinks as $link)
                <a class="mobile-account-link {{ request()->routeIs($link['route']) ? 'active' : '' }}" href="{{ route($link['route']) }}">{{ $link['label'] }}</a>
            @endforeach
            <form method="post" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button class="btn btn-outline-dark w-100">Se déconnecter</button>
            </form>
        </nav>
    </details>

    <div class="row g-0">
        <aside class="col-md-3 col-xl-2 dashboard-sidebar p-3 d-none d-md-block">
            <h5 class="text-white p-2">Espace client</h5>
            @foreach($accountLinks as $link)
                <a class="{{ request()->routeIs($link['route']) ? 'active' : '' }}" href="{{ route($link['route']) }}">{{ $link['label'] }}</a>
            @endforeach
            <form method="post" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button class="btn btn-outline-light w-100">Se déconnecter</button>
            </form>
        </aside>
        <section class="col-12 col-md-9 col-xl-10 p-3 p-lg-5">@yield('account-content')</section>
    </div>
</div>
@endsection
