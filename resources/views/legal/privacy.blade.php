@extends('layouts.app')
@section('title', 'Politique de confidentialité - AchatHub')
@section('content')
@php
    $missing = fn (string $key): bool => $missingLegalSettings->contains($key);
    $legalName = $missing('legal_name') ? 'Identité juridique à renseigner par l’exploitant AchatHub' : $settings->get('legal_name');
    $privacyEmail = $settings->get('dpo_email') ?: $settings->get('support_email', 'contact@achathub.fr');
@endphp
<div class="legal-page">
    <header><span>VIE PRIVÉE</span><h1>Politique de confidentialité</h1><p>Version du 3 août 2026 — l’essentiel sur l’utilisation de vos données et vos choix.</p></header>

    @if($missing('legal_name') || $missing('company_address'))
        <div class="alert alert-warning" role="alert"><strong>Identité à finaliser.</strong> L’exploitant doit compléter son identité juridique et son adresse avant l’ouverture commerciale.</div>
    @endif

    <section><h2>Responsable du traitement</h2><p>{{ $legalName }}, {{ $missing('company_address') ? 'adresse à renseigner' : $settings->get('company_address') }}. Point de contact vie privée : <a href="mailto:{{ $privacyEmail }}">{{ $privacyEmail }}</a>.</p></section>

    <section>
        <h2>Données, finalités et bases légales</h2>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Traitement</th><th>Données principales</th><th>Pourquoi et base légale</th></tr></thead><tbody>
            <tr><td>Compte client ou professionnel</td><td>Identité, e-mail, téléphone, adresse, mot de passe chiffré ; pour les professionnels, identité et informations d’entreprise</td><td>Créer et sécuriser le compte, vérifier l’accès professionnel — exécution de mesures précontractuelles et du contrat</td></tr>
            <tr><td>Commande et livraison</td><td>Panier, coordonnées, adresse, produits, prix, paiement et suivi</td><td>Traiter, livrer et suivre la commande — exécution du contrat</td></tr>
            <tr><td>Facturation et comptabilité</td><td>Commande, facture, identité et paiement</td><td>Respect des obligations comptables et fiscales — obligation légale</td></tr>
            <tr><td>Support, retours et garanties</td><td>Coordonnées, messages, commande, justificatifs utiles</td><td>Répondre, traiter un retour ou une garantie — contrat et intérêt légitime à défendre les droits des parties</td></tr>
            <tr><td>Sécurité et fraude</td><td>Données de connexion, événements techniques, compte et transaction</td><td>Protéger les utilisateurs, le site et les paiements — intérêt légitime</td></tr>
            <tr><td>Cookies optionnels</td><td>Préférences et, seulement si un outil est activé, identifiants de mesure</td><td>Mesure d’audience ou personnalisation non essentielle — consentement, révocable à tout moment</td></tr>
        </tbody></table></div>
        <p>Les champs signalés comme obligatoires sont nécessaires pour fournir le service concerné. Sans eux, le compte ou la commande ne peut pas être traité. Les champs explicitement facultatifs peuvent être laissés vides.</p>
    </section>

    <section><h2>Destinataires et sous-traitants</h2><p>Seules les personnes habilitées d’AchatHub et les prestataires nécessaires à l’hébergement, la base de données, l’envoi d’e-mails, au paiement et à la livraison accèdent aux données dans la limite de leur mission. Les numéros complets de carte bancaire ne sont pas conservés par AchatHub. Les autorités peuvent recevoir des données lorsqu’un texte l’impose.</p></section>

    <section><h2>Transferts hors Espace économique européen</h2><p>Certains prestataires techniques peuvent traiter des données hors de l’Espace économique européen. AchatHub doit alors vérifier le mécanisme applicable, par exemple une décision d’adéquation ou les clauses contractuelles types de la Commission européenne, et tenir la documentation correspondante à disposition sur demande.</p></section>

    <section>
        <h2>Durées de conservation</h2>
        <ul>
            <li>Compte et profil : pendant la relation active, puis jusqu’à trois ans après la dernière activité, sauf obligation ou litige en cours.</li>
            <li>Commandes, factures et pièces comptables : dix ans conformément aux obligations comptables.</li>
            <li>Panier et session : pendant la session ; la session expire après une période d’inactivité configurée à deux heures.</li>
            <li>Échanges de support, retours et garanties : pendant leur traitement puis pendant la durée nécessaire à la preuve et à la défense des droits.</li>
            <li>Dossiers professionnels non retenus : jusqu’à trois ans après le dernier contact, sauf demande d’effacement compatible avec les obligations applicables.</li>
            <li>Journaux de sécurité : durée limitée au besoin de détection et d’investigation, avec un objectif maximal de douze mois hors incident nécessitant une conservation justifiée.</li>
            <li>Choix relatif aux cookies : six mois avant un nouveau recueil du choix.</li>
        </ul>
        <p>À l’issue de ces durées, les données sont supprimées ou anonymisées, sauf conservation imposée par la loi ou nécessaire à un contentieux.</p>
    </section>

    <section><h2>Vos droits</h2><p>Selon le traitement, vous pouvez demander l’accès, la rectification, l’effacement, la limitation, la portabilité ou vous opposer à l’utilisation de vos données. Vous pouvez retirer un consentement à tout moment, sans remettre en cause les traitements déjà réalisés. Une réponse est apportée en principe dans un délai d’un mois ; ce délai peut être prolongé de deux mois pour une demande complexe ou nombreuse, avec information du demandeur.</p><p>Depuis un compte authentifié, utilisez « Mes données et mes droits ». Vous pouvez aussi écrire à <a href="mailto:{{ $privacyEmail }}">{{ $privacyEmail }}</a>. Une preuve d’identité proportionnée peut être demandée en cas de doute raisonnable. Si la réponse ne vous satisfait pas, vous pouvez adresser une réclamation à la <a href="https://www.cnil.fr/fr/plaintes" rel="noopener noreferrer">CNIL</a>.</p></section>

    <section><h2>Décision automatisée</h2><p>AchatHub ne prend pas de décision produisant un effet juridique exclusivement sur la base d’un profil automatisé. Les demandes d’accès professionnel font l’objet d’une vérification.</p></section>
    <section><h2>Sécurité</h2><p>AchatHub applique des mesures destinées à limiter les accès non autorisés : communications chiffrées, mots de passe hachés, contrôle des rôles, protection des formulaires, limitation des tentatives et journalisation adaptée. Aucun dispositif ne supprime totalement le risque ; tout incident avéré est traité selon les obligations applicables.</p></section>
    <section><h2>Mise à jour</h2><p>Cette politique est mise à jour lorsqu’un traitement ou un prestataire change de manière importante. La date de version figure en tête de page.</p></section>
</div>
@endsection
