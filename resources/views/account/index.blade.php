@extends('layouts.account')
@section('title','Mon compte - AchatHub')
@section('account-content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="section-title mb-1">Bonjour {{ auth()->user()->name }}</h1><p class="text-secondary mb-0">Vos commandes, factures et favoris au même endroit.</p></div>
    <a class="btn btn-ah" href="{{ route('home') }}"><i class="bi bi-bag me-1"></i> Continuer mes achats</a>
</div>

@unless(auth()->user()->hasVerifiedEmail())
<div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-3">
    <span><strong>Confirmez votre e-mail.</strong> Cette étape protège vos commandes et vos factures.</span>
    <form method="post" action="{{ route('verification.send') }}">@csrf<button class="btn btn-sm btn-dark">Renvoyer le lien</button></form>
</div>
@endunless

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="stat-card h-100"><small class="text-secondary">COMMANDES</small><div class="stat-value">{{ $stats['orders'] }}</div><a href="{{ route('account.orders') }}">Voir l’historique</a></div></div>
    <div class="col-6 col-xl-3"><div class="stat-card h-100"><small class="text-secondary">EN COURS</small><div class="stat-value">{{ $stats['pending'] }}</div><span>À préparer ou livrer</span></div></div>
    <div class="col-6 col-xl-3"><div class="stat-card h-100"><small class="text-secondary">ACHATS PAYÉS</small><div class="stat-value">{{ number_format($stats['spent'],2,',',' ') }} €</div><span>Factures disponibles</span></div></div>
    <div class="col-6 col-xl-3"><div class="stat-card h-100"><small class="text-secondary">FAVORIS</small><div class="stat-value">{{ $stats['wishlist'] }}</div><a href="{{ route('account.wishlist') }}">Ouvrir ma liste</a></div></div>
</div>

<div class="stat-card">
    <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h4 mb-0">Dernières commandes</h2><a href="{{ route('account.orders') }}">Tout afficher</a></div>
    @forelse($orders as $order)
        <a class="d-flex flex-wrap justify-content-between gap-3 text-decoration-none text-dark border-bottom py-3" href="{{ route('account.order',$order) }}">
            <span><strong>{{ $order->number }}</strong><br><small>{{ $order->created_at->format('d/m/Y') }}</small></span>
            <span><span class="badge text-bg-light">{{ $order->status }}</span> <strong class="ms-2">{{ number_format($order->total,2,',',' ') }} €</strong></span>
        </a>
    @empty
        <div class="text-center py-4"><p class="text-secondary">Aucune commande pour le moment.</p><a class="btn btn-outline-dark" href="{{ route('home') }}">Découvrir le catalogue</a></div>
    @endforelse
</div>
@endsection
