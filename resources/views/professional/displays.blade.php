@extends('layouts.pro')
@section('title', 'Présentoirs complets - AchatHub Pro')
@section('pro-content')
<div class="pro-page-head"><div><h1>Présentoirs prêts à vendre</h1><p>Une offre complète livrée avec une sélection de produits adaptée à votre commerce.</p></div><a class="btn btn-outline-dark" href="{{ route('pro.index') }}">Commander à l’unité</a></div>
<div class="row g-3">
@foreach($displays as $display)
<div class="col-md-6 col-xl-4"><article class="pro-display-card h-100"><img src="{{ $display->image }}" alt="{{ $display->name }}" loading="lazy" decoding="async"><div class="card-body"><span class="badge text-bg-light mb-2">{{ $display->products_count }} références incluses</span><h2 class="h5 fw-bold">{{ $display->name }}</h2><p class="text-secondary small">{{ $display->description }}</p><div class="fs-3 fw-bold">{{ number_format($display->wholesale_price_ht,2,',',' ') }} € <small class="fs-6 text-secondary">HT</small></div><div class="small text-secondary mb-3">{{ number_format($display->price_ttc,2,',',' ') }} € TTC</div><a class="btn btn-dark w-100" href="{{ route('pro.show',$display) }}">Voir les produits inclus</a></div></article></div>
@endforeach
</div>
@endsection
