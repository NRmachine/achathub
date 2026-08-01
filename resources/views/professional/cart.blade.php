@extends('layouts.pro')
@section('title', 'Panier professionnel - AchatHub Pro')
@section('pro-content')
<div class="pro-page-head"><div><h1>Panier des présentoirs</h1><p>Seuls les présentoirs complets sont achetables directement en ligne.</p></div><a class="btn btn-outline-dark" href="{{ route('pro.displays') }}">Voir les présentoirs</a></div>

@if($itemCount === 0)
<div class="bg-white border text-center p-5"><i class="bi bi-cart3 display-5 text-secondary"></i><h2 class="h5 mt-3">Aucun présentoir dans le panier</h2><p class="text-secondary">Les produits à l’unité passent par une précommande. Ici, vous pouvez acheter uniquement un présentoir complet.</p><a class="pro-primary-btn btn px-4" href="{{ route('pro.displays') }}">Choisir un présentoir</a></div>
@else
<div class="row g-4"><div class="col-xl-8"><div class="border rounded overflow-hidden">
@foreach($displays as $display)
<div class="pro-cart-line"><img src="{{ $display->image }}" alt=""><div><span class="badge text-bg-warning mb-1">Présentoir complet</span><h2 class="h6 fw-bold mb-1">{{ $display->name }}</h2><span class="small text-secondary">{{ number_format($display->wholesale_price_ht,2,',',' ') }} € HT / unité</span></div><div class="pro-cart-actions"><form method="post" action="{{ route('pro.cart.update',$display) }}" class="d-flex gap-2">@csrf @method('patch')<input class="form-control" style="width:80px" type="number" name="quantity" value="{{ $display->cart_quantity }}" min="1" max="20" aria-label="Quantité"><button class="btn btn-outline-dark" aria-label="Mettre à jour"><i class="bi bi-arrow-clockwise"></i></button></form><form method="post" action="{{ route('pro.cart.remove',$display) }}">@csrf @method('delete')<button class="btn btn-outline-danger" aria-label="Retirer"><i class="bi bi-trash"></i></button></form></div></div>
@endforeach
</div></div><div class="col-xl-4"><div class="pro-summary"><h2 class="h5 fw-bold">Récapitulatif</h2><div class="d-flex justify-content-between py-2"><span>Sous-total HT</span><strong>{{ number_format($subtotalHt,2,',',' ') }} €</strong></div><div class="d-flex justify-content-between py-2"><span>TVA</span><strong>{{ number_format($vatAmount,2,',',' ') }} €</strong></div><hr><div class="d-flex justify-content-between fs-5"><span>Total TTC</span><strong>{{ number_format($totalTtc,2,',',' ') }} €</strong></div><a class="pro-primary-btn btn btn-lg w-100 mt-4" href="{{ route('pro.checkout') }}">Choisir le paiement</a><p class="small text-secondary text-center mt-2 mb-0">Espèces, carte à la livraison ou virement</p></div></div></div>
@endif
@endsection
