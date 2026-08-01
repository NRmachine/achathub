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
                <a class="btn btn-warning btn-lg" href="#demande">Déposer ma demande</a>
            </div>
            <div class="col-lg-6"><img class="img-fluid rounded shadow" src="{{ asset('assets/presentoir-achathub.png') }}" alt="Présentoir professionnel AchatHub"></div>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        <div class="row g-4">
            @foreach([['Petit présentoir','Comptoir, salon, institut ou petite boutique.'],['Présentoir moyen','Supérette, téléphonie ou commerce de proximité.'],['Grand présentoir','Magasin high-tech ou point de vente à fort passage.']] as $offer)
            <div class="col-md-4"><div class="card h-100 shadow-sm"><div class="card-body p-4"><h2 class="h5 fw-bold">{{ $offer[0] }}</h2><p class="text-secondary mb-0">{{ $offer[1] }}</p></div></div></div>
            @endforeach
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
