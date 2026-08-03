@extends('layouts.pro')
@section('title', 'Catalogue grossiste - AchatHub Pro')
@section('pro-content')
<div class="pro-page-head">
    <div><span class="badge text-bg-success mb-2">Compte professionnel validé</span><h1>Catalogue professionnel</h1><p>Commandez directement les références en gros aux tarifs revendeurs HT.</p></div>
    <a class="btn btn-outline-dark d-none d-md-inline-flex" href="{{ route('pro.displays') }}"><i class="bi bi-box-seam me-2"></i>Voir les présentoirs complets</a>
</div>

<section class="pro-commercial-bar" aria-label="Services professionnels">
    <div><i class="bi bi-grid"></i><span><strong>{{ $categories->sum('total') }} références</strong><small>Catalogue grossiste</small></span></div>
    <div><i class="bi bi-box-seam"></i><span><strong>Stock visible</strong><small>Quantités indiquées par produit</small></span></div>
    <div><i class="bi bi-receipt"></i><span><strong>Facture Pro</strong><small>Disponible dans votre compte</small></span></div>
    <a href="{{ route('messages.index') }}"><i class="bi bi-chat-dots"></i><span><strong>Besoin d’un conseil ?</strong><small>Écrire au service commercial</small></span><i class="bi bi-arrow-right ms-auto"></i></a>
</section>

<form class="bg-white border rounded p-3 mb-3" method="get" action="{{ route('pro.index') }}">
    <div class="row g-2 align-items-end">
        <div class="col-lg-5"><label class="form-label small fw-semibold" for="pro-catalog-q">Produit, référence ou description</label><input id="pro-catalog-q" class="form-control" name="q" value="{{ request('q') }}" placeholder="Ex. USB-C, chargeur, F8002"></div>
        <div class="col-sm-6 col-lg-3"><label class="form-label small fw-semibold" for="pro-availability">Disponibilité</label><select id="pro-availability" class="form-select" name="availability"><option value="">Tous les produits</option><option value="available" @selected(request('availability')==='available')>Commandables maintenant</option><option value="preorder" @selected(request('availability')==='preorder')>Sur précommande</option></select></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label small fw-semibold" for="pro-sort">Trier par</label><select id="pro-sort" class="form-select" name="sort"><option value="">Catégorie</option><option value="price_asc" @selected(request('sort')==='price_asc')>Prix croissant</option><option value="price_desc" @selected(request('sort')==='price_desc')>Prix décroissant</option><option value="stock_desc" @selected(request('sort')==='stock_desc')>Stock disponible</option><option value="minimum_asc" @selected(request('sort')==='minimum_asc')>Petit minimum</option></select></div>
        <div class="col-lg-2 d-flex gap-2"><button class="btn btn-dark flex-grow-1">Filtrer</button>@if(request()->hasAny(['q','availability','sort','category']))<a class="btn btn-outline-secondary" href="{{ route('pro.index') }}" aria-label="Effacer les filtres"><i class="bi bi-x-lg"></i></a>@endif</div>
    </div>
    @if(request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
</form>

<div class="pro-steps">
    <div class="pro-step"><b>1</b><span><strong>Consultez le catalogue</strong>Découvrez les références et leurs tarifs indicatifs HT.</span></div>
    <div class="pro-step"><b>2</b><span><strong>Choisissez vos quantités</strong>Respectez le minimum indiqué pour chaque référence.</span></div>
    <div class="pro-step"><b>3</b><span><strong>Commandez en ligne</strong>Le stock est réservé et votre facture est disponible dans le compte Pro.</span></div>
</div>

<nav class="pro-category-strip" aria-label="Catégories professionnelles">
    <a class="{{ !request('category') ? 'active' : '' }}" href="{{ route('pro.index', request()->only('q')) }}">Tous <span class="opacity-75">{{ $categories->sum('total') }}</span></a>
    @foreach($categories as $category)
        <a class="{{ request('category') === $category->category ? 'active' : '' }}" href="{{ route('pro.index', array_filter(['category'=>$category->category,'q'=>request('q')])) }}">{{ $category->category }} <span class="opacity-75">{{ $category->total }}</span></a>
    @endforeach
</nav>

@if(request('q'))<div class="d-flex justify-content-between align-items-center mb-3"><span>Résultats pour <strong>{{ request('q') }}</strong></span><a href="{{ route('pro.index') }}">Effacer</a></div>@endif

<div class="pro-products">
@forelse($products as $product)
    @include('professional.partials.product-card', ['product' => $product])
@empty
    <div class="alert alert-light border grid-column-all">Aucun produit ne correspond à votre recherche.</div>
@endforelse
</div>
@if($products->hasPages())
<nav class="pro-load-more" aria-label="Navigation du catalogue professionnel">
    @if(!$products->onFirstPage())<a class="pro-page-back" href="{{ $products->previousPageUrl() }}"><i class="bi bi-arrow-left"></i> Produits précédents</a>@endif
    <span>Page {{ $products->currentPage() }} sur {{ $products->lastPage() }}</span>
    @if($products->hasMorePages())<a class="pro-page-next" href="{{ $products->nextPageUrl() }}">Afficher plus de produits <i class="bi bi-arrow-down"></i></a>@endif
</nav>
@endif
@endsection
