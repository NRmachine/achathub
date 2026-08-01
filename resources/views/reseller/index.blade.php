@extends('layouts.app')
@section('title', 'Devenir revendeur AchatHub')
@section('content')
<section class="bg-dark text-white py-5">
    <div class="container py-lg-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge text-bg-warning mb-3">Programme professionnel</span>
                <h1 class="display-5 fw-bold">Développez vos ventes avec un présentoir prêt à poser</h1>
                <p class="lead text-white-50">AchatHub équipe votre commerce avec une sélection d’accessoires utiles et faciles à vendre. Choisissez le dépôt-vente ou l’achat en gros.</p>
                <div class="d-flex flex-wrap gap-2"><a class="btn btn-warning btn-lg" href="#demande">Créer mon accès Pro</a><a class="btn btn-outline-light btn-lg" href="{{ route('professional.login') }}">Déjà client Pro ? Se connecter</a></div>
            </div>
            <div class="col-lg-6"><img class="img-fluid rounded shadow" src="{{ asset('assets/presentoir-achathub.webp') }}" width="1400" height="782" alt="Présentoir professionnel AchatHub" fetchpriority="high" decoding="async"></div>
        </div>
    </div>
</section>

<section class="py-4 bg-white border-bottom" aria-label="Avantages professionnels">
    <div class="container reseller-benefits">
        <article class="reseller-benefit"><i class="bi bi-tags"></i><h2>Tarifs revendeurs HT</h2><p>Prix professionnels et minimums de commande clairement affichés.</p></article>
        <article class="reseller-benefit"><i class="bi bi-box-seam"></i><h2>Présentoirs prêts à vendre</h2><p>Trois formats avec leur sélection de références déjà composée.</p></article>
        <article class="reseller-benefit"><i class="bi bi-receipt"></i><h2>Commandes et factures</h2><p>Suivez vos achats et retrouvez vos factures depuis votre compte Pro.</p></article>
        <article class="reseller-benefit"><i class="bi bi-chat-dots"></i><h2>Conseil commercial</h2><p>Échangez avec AchatHub dans votre messagerie professionnelle.</p></article>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4"><div><span class="text-uppercase small fw-bold text-warning">Offres professionnelles</span><h2 class="fw-bold mb-1">Choisissez votre présentoir</h2><p class="text-secondary mb-0">Les tarifs et le nombre de références proviennent du catalogue AchatHub Pro.</p></div><a class="btn btn-outline-dark" href="{{ route('professional.login') }}">Accéder aux produits en gros</a></div>
        <div class="row g-4">
            @forelse($offers as $offer)
            <div class="col-md-4"><article class="reseller-offer"><span class="badge text-bg-light align-self-start mb-3">{{ $offer->products_count }} références incluses</span><h2>{{ $offer->name }}</h2><p>{{ $offer->description }}</p><div class="reseller-offer-price">{{ number_format($offer->wholesale_price_ht, 2, ',', ' ') }} € <small>HT</small></div><a class="btn btn-dark mt-3" href="#demande">Demander l’accès Pro</a></article></div>
            @empty
            <div class="col-12"><div class="alert alert-light border mb-0">Les offres de présentoirs sont momentanément en cours de mise à jour. Créez votre accès Pro pour être informé.</div></div>
            @endforelse
        </div>
    </div>
</section>

<section class="py-5" id="demande">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <h2 class="fw-bold">Comment accéder aux prix professionnels ?</h2>
                <ol class="ps-3 text-secondary">
                    <li class="mb-3">Créez votre compte AchatHub.</li>
                    <li class="mb-3">Complétez la demande avec les informations de votre commerce.</li>
                    <li class="mb-3">Notre équipe contrôle et valide votre dossier.</li>
                    <li>Accédez au catalogue grossiste et commandez vos présentoirs.</li>
                </ol>
                <div class="border rounded p-3 bg-light"><strong><i class="bi bi-check-circle text-success me-1"></i> Un seul compte Pro</strong><p class="small text-secondary mb-0 mt-1">Catalogue en gros, présentoirs, panier, commandes, factures et messagerie sont réunis au même endroit.</p></div>
            </div>
            <div class="col-lg-8">
                @guest
                    <div class="border rounded p-4 p-lg-5 bg-light">
                        <h2 class="h4 fw-bold">Créez votre compte professionnel</h2>
                        <p class="text-secondary">Un formulaire dédié permet d’enregistrer votre entreprise française ou votre micro-entreprise avec son SIRET.</p>
                        <a class="btn btn-warning" href="{{ route('professional.register') }}">Créer mon compte professionnel</a>
                        <a class="btn btn-outline-dark" href="{{ route('login') }}">Me connecter</a>
                    </div>
                @elseif($application && in_array($application->status, ['En attente','Approuvée']))
                    <div class="border rounded p-4 p-lg-5">
                        <span class="badge {{ $application->status === 'Approuvée' ? 'text-bg-success' : 'text-bg-warning' }} mb-3">{{ $application->status }}</span>
                        <h2 class="h4 fw-bold">Votre demande est déjà enregistrée</h2>
                        <p class="text-secondary">Commerce : {{ $application->business_name }}. Suivez son traitement depuis votre espace revendeur.</p>
                        <a class="btn btn-dark" href="{{ route('reseller.dashboard') }}">Ouvrir mon espace revendeur</a>
                    </div>
                @else
                    <form method="post" action="{{ route('reseller.store') }}" class="border rounded p-4 p-lg-5">
                        @csrf
                        <h2 class="h4 fw-bold mb-4">Demande d’accès revendeur</h2>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Nom du commerce</label><input class="form-control" name="business_name" value="{{ old('business_name') }}" required></div>
                            <div class="col-md-6"><label class="form-label">Nom du responsable</label><input class="form-control" name="manager_name" value="{{ old('manager_name', auth()->user()->name) }}" required></div>
                            <div class="col-md-6"><label class="form-label">Téléphone</label><input class="form-control" name="phone" value="{{ old('phone', auth()->user()->phone) }}" required></div>
                            <div class="col-md-6"><label class="form-label">E-mail du compte</label><input class="form-control" value="{{ auth()->user()->email }}" disabled></div>
                            <div class="col-md-8"><label class="form-label">Adresse</label><input class="form-control" name="address" value="{{ old('address', auth()->user()->address) }}" required></div>
                            <div class="col-md-4"><label class="form-label">Ville</label><input class="form-control" name="city" value="{{ old('city') }}" required></div>
                            <div class="col-md-6"><label class="form-label">Type de commerce</label><select class="form-select" name="business_type" required><option value="">Sélectionner</option>@foreach(['Boutique téléphonie','Supérette','Commerce de proximité','Salon ou institut','Magasin informatique','Autre commerce'] as $type)<option @selected(old('business_type')===$type)>{{ $type }}</option>@endforeach</select></div>
                            <div class="col-md-6"><label class="form-label">Formule souhaitée</label><select class="form-select" name="formula" required><option value="Achat en gros">Achat en gros</option><option value="Dépôt-vente">Dépôt-vente</option></select></div>
                            <div class="col-md-6"><label class="form-label">Présentoir envisagé</label><select class="form-select" name="display_type" required>@foreach(['Petit','Moyen','Grand'] as $size)<option @selected(old('display_type')===$size)>{{ $size }}</option>@endforeach</select></div>
                            <div class="col-md-6"><label class="form-label">Catégories recherchées</label><input class="form-control" name="categories" value="{{ old('categories') }}" placeholder="Câbles, chargeurs, audio..."></div>
                            <div class="col-12"><label class="form-label">Message (facultatif)</label><textarea class="form-control" rows="4" name="message">{{ old('message') }}</textarea></div>
                            <div class="col-12"><button class="btn btn-warning btn-lg">Envoyer ma demande revendeur</button></div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
