@extends('layouts.app')
@section('title', 'Politique des cookies - AchatHub')
@section('content')
<div class="legal-page">
    <header><span>VOS PRÉFÉRENCES</span><h1>Politique des cookies</h1><p>Version du 3 août 2026 — accepter, refuser ou revenir sur votre choix est tout aussi simple.</p></header>

    <section><h2>À quoi servent les cookies ?</h2><p>Un cookie est un petit fichier enregistré par le navigateur. AchatHub utilise des cookies strictement nécessaires au panier, à la connexion, à la protection des formulaires et à la mémorisation de votre choix. Ils ne servent pas à vous suivre sur d’autres sites.</p></section>

    <section>
        <h2>Cookies utilisés</h2>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Cookie</th><th>Finalité</th><th>Durée</th><th>Consentement</th></tr></thead><tbody>
            <tr><td>Session AchatHub</td><td>Connexion, panier et sécurité de la session</td><td>Deux heures d’inactivité par défaut</td><td>Strictement nécessaire</td></tr>
            <tr><td>XSRF-TOKEN</td><td>Protection des formulaires contre les requêtes frauduleuses</td><td>Session</td><td>Strictement nécessaire</td></tr>
            <tr><td>cookie_consent</td><td>Mémoriser votre choix pour ne pas réafficher le bandeau</td><td>Six mois</td><td>Strictement nécessaire au choix demandé</td></tr>
        </tbody></table></div>
    </section>

    <section><h2>Cookies optionnels</h2><p>La version actuelle d’AchatHub n’active aucun cookie de publicité, de personnalisation ou de mesure d’audience soumis au consentement. Si un tel outil est ajouté, il devra rester désactivé avant votre accord et la présente liste devra être mise à jour. Cliquer sur « Tout accepter » enregistre actuellement votre préférence sans déclencher de traceur optionnel.</p></section>

    <section>
        <h2>Modifier ou retirer votre choix</h2>
        <p>Votre nouveau choix remplace immédiatement le précédent. Le refus n’empêche pas l’utilisation du site ni les achats.</p>
        <form method="post" action="{{ route('cookies.consent') }}" class="d-flex gap-2 flex-wrap">
            @csrf
            <button name="choice" value="refused" class="btn btn-dark">Tout refuser</button>
            <button name="choice" value="accepted" class="btn btn-dark">Tout accepter</button>
        </form>
        <p class="mt-3 mb-0">Vous pouvez également supprimer les cookies depuis les réglages de votre navigateur. Les cookies indispensables seront recréés si vous utilisez à nouveau le panier ou la connexion.</p>
    </section>
</div>
@endsection
