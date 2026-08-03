@extends('layouts.app')
@section('title', 'Mentions légales - AchatHub')
@section('content')
@php
    $missing = fn (string $key): bool => $missingLegalSettings->contains($key);
    $show = fn (string $key, string $fallback = 'Information à renseigner par l’exploitant') => $missing($key) ? $fallback : $settings->get($key, $fallback);
@endphp
<div class="legal-page">
    <header><span>IDENTIFICATION</span><h1>Mentions légales</h1><p>Informations relatives à l’éditeur et à l’hébergement d’AchatHub.</p></header>

    @if($missingLegalSettings->intersect(['legal_name','company_address','company_siret','company_siren','company_legal_form','publication_director','host_address','host_phone'])->isNotEmpty())
        <div class="alert alert-warning" role="alert"><strong>Informations à finaliser.</strong> L’exploitant doit compléter les champs signalés dans l’administration avant l’ouverture commerciale.</div>
    @endif

    <section>
        <h2>Éditeur du site</h2>
        <p><strong>{{ $settings->get('company_name', 'AchatHub') }}</strong> — {{ $show('legal_name') }}<br>
            Forme juridique : {{ $show('company_legal_form') }}<br>
            Capital social : {{ $show('company_capital') }}<br>
            Siège social : {{ $show('company_address') }}<br>
            SIREN : {{ $show('company_siren') }} · SIRET : {{ $show('company_siret') }}<br>
            Immatriculation RCS/RNE : {{ $show('company_rcs') }}<br>
            TVA intracommunautaire : {{ $show('company_vat_number') }}<br>
            Téléphone : {{ $show('support_phone') }}<br>
            E-mail : <a href="mailto:{{ $settings->get('support_email', 'contact@achathub.fr') }}">{{ $settings->get('support_email', 'contact@achathub.fr') }}</a>
        </p>
    </section>

    <section><h2>Direction de la publication</h2><p>{{ $show('publication_director') }}</p></section>

    <section>
        <h2>Hébergement</h2>
        <p>{{ $show('host_name') }}<br>{{ $show('host_address') }}<br>Téléphone : {{ $show('host_phone') }}
            @if(!$missing('host_website'))<br>Site : <a href="{{ $settings->get('host_website') }}" rel="noopener noreferrer">{{ $settings->get('host_website') }}</a>@endif
        </p>
    </section>

    <section><h2>Propriété intellectuelle</h2><p>Les marques, textes, photographies, logiciels et éléments graphiques restent la propriété de leurs titulaires. Toute reproduction ou réutilisation non autorisée est interdite, sauf exception prévue par la loi.</p></section>
    <section><h2>Données personnelles et cookies</h2><p>Les modalités de traitement des données sont détaillées dans la <a href="{{ route('legal.privacy') }}">politique de confidentialité</a>. Les préférences relatives aux traceurs peuvent être modifiées depuis la <a href="{{ route('legal.cookies') }}">page Cookies</a>.</p></section>
    <section><h2>Contact</h2><p>Pour toute question sur le site ou son contenu, utilisez le <a href="{{ route('support.index') }}">service client</a> ou l’adresse électronique indiquée ci-dessus.</p></section>
</div>
@endsection
