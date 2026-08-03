@extends('layouts.app')
@section('title', 'Conditions générales de vente - AchatHub')
@section('content')
@php
    $missing = fn (string $key): bool => $missingLegalSettings->contains($key);
    $legalName = $missing('legal_name') ? 'l’exploitant AchatHub (identité à compléter)' : $settings->get('legal_name');
    $supportEmail = $settings->get('support_email', 'contact@achathub.fr');
@endphp
<div class="legal-page">
    <header><span>INFORMATIONS CONTRACTUELLES</span><h1>Conditions générales d’utilisation et de vente</h1><p>Version du 3 août 2026</p></header>

    @if($missingLegalSettings->intersect(['legal_name','company_address','company_siret','returns_address','mediator_name','mediator_address','mediator_website'])->isNotEmpty())
        <div class="alert alert-warning" role="alert"><strong>Document à faire valider avant commercialisation.</strong> L’identité du vendeur, l’adresse de retour et le médiateur de la consommation doivent encore être renseignés par l’exploitant.</div>
    @endif

    <section><h2>1. Vendeur et champ d’application</h2><p>AchatHub est exploité par {{ $legalName }}. Ses coordonnées complètes figurent dans les <a href="{{ route('legal.notice') }}">mentions légales</a>. Les présentes conditions s’appliquent aux ventes à distance réalisées sur le site. Le client consommateur est une personne physique agissant à des fins non professionnelles. Les ventes aux professionnels sont également soumises à la section 11 et, lorsqu’elles existent, aux conditions particulières communiquées avant la commande.</p></section>

    <section><h2>2. Produits et disponibilité</h2><p>Les caractéristiques essentielles, la référence, l’état du produit, le prix et la disponibilité sont présentés sur chaque fiche. Les photographies illustrent le produit sans remplacer sa description. Une indisponibilité constatée après la commande est signalée au client afin de proposer une solution ou un remboursement.</p></section>

    <section><h2>3. Prix et frais</h2><p>Pour les consommateurs, les prix sont affichés en euros toutes taxes comprises. Les frais de livraison et le total à payer sont présentés avant la validation. Pour les professionnels, les prix peuvent être affichés hors taxes et la TVA applicable est ajoutée au récapitulatif. Une réduction de prix, lorsqu’elle est affichée, doit être calculée à partir du prix de référence légalement applicable.</p></section>

    <section><h2>4. Commande</h2><p>Le client peut vérifier les produits, quantités, coordonnées, livraison, paiement et prix total avant de commander. La validation au moyen du bouton indiquant explicitement l’obligation de paiement constitue une commande ferme. AchatHub envoie une confirmation récapitulative à l’adresse fournie. AchatHub peut refuser ou annuler une commande pour un motif légitime, notamment fraude présumée, impayé, erreur manifeste de prix ou indisponibilité, et en informe le client.</p></section>

    <section><h2>5. Paiement</h2><p>Les moyens et le moment du paiement sont indiqués pendant la commande. Le client garantit qu’il est autorisé à utiliser le moyen choisi. Les données complètes de carte ne sont pas conservées par AchatHub ; lorsqu’un prestataire de paiement intervient, il traite ces données selon ses propres mesures de sécurité.</p></section>

    <section><h2>6. Livraison</h2><p>La zone desservie, le coût ainsi que la date ou le délai estimé sont indiqués avant validation. À défaut d’engagement plus précis, la livraison intervient au plus tard trente jours après la conclusion du contrat. Le risque de perte ou d’endommagement est transféré au consommateur lorsqu’il prend physiquement possession du bien, sauf transporteur choisi par lui hors des options proposées. En cas de retard, le client dispose des recours prévus par le Code de la consommation.</p></section>

    <section>
        <h2>7. Droit de rétractation des consommateurs</h2>
        <p>Le consommateur dispose de quatorze jours à compter de la réception du bien pour notifier sans ambiguïté sa décision de se rétracter, sans avoir à la justifier. Il renvoie ensuite le bien au plus tard quatorze jours après cette notification. Les frais directs de retour restent à sa charge sauf indication contraire ou défaut d’information préalable.</p>
        <p>Le remboursement comprend le prix et les frais de livraison standard. Il intervient au plus tard quatorze jours après l’information de la rétractation ; AchatHub peut le différer jusqu’à la récupération du bien ou la réception d’une preuve d’expédition. Le client répond uniquement de la dépréciation résultant de manipulations dépassant celles nécessaires pour établir la nature, les caractéristiques et le bon fonctionnement du bien.</p>
        <p>Les exceptions légales s’appliquent notamment aux biens nettement personnalisés et aux produits descellés ne pouvant être renvoyés pour des raisons d’hygiène ou de protection de la santé. Ces exclusions sont signalées avant la commande lorsqu’elles concernent un produit.</p>
        <p>Notification et retours : <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a> — {{ $missing('returns_address') ? 'adresse de retour à renseigner par l’exploitant' : $settings->get('returns_address') }}.</p>
    </section>

    <section><h2>8. Garanties légales</h2><p>Le consommateur bénéficie de la garantie légale de conformité et de la garantie contre les vices cachés, indépendamment de toute garantie commerciale. L’action en garantie légale de conformité se prescrit par deux ans à compter de la délivrance du bien, neuf ou d’occasion. En cas de défaut, le consommateur peut demander la mise en conformité dans les conditions légales puis, lorsque les conditions sont réunies, une réduction du prix ou la résolution du contrat. La garantie des vices cachés peut être exercée dans les deux ans suivant la découverte du vice. Toute demande peut être adressée au support avec le numéro de commande et une description du défaut.</p></section>

    <section><h2>9. Service client et retours</h2><p>Le client peut contacter AchatHub depuis le <a href="{{ route('support.index') }}">support</a> ou par e-mail à <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>. Avant tout retour, il est recommandé d’indiquer le numéro de commande pour obtenir les instructions adaptées. Cette recommandation ne limite pas les droits légaux.</p></section>

    <section><h2>10. Compte et utilisation du site</h2><p>L’utilisateur fournit des informations exactes, garde ses identifiants confidentiels et signale toute utilisation suspecte. AchatHub peut suspendre un accès en cas de fraude, d’impayé, d’atteinte à la sécurité ou d’usage illicite, dans la mesure nécessaire et après vérification. L’indisponibilité temporaire du site n’affecte pas les droits déjà acquis au titre d’une commande.</p></section>

    <section><h2>11. Clients professionnels</h2><p>Les prix professionnels, minima de commande, TVA, livraison et conditions de paiement sont affichés avant validation. Les précommandes constituent une demande jusqu’à confirmation par AchatHub. Le droit légal de rétractation réservé aux consommateurs ne s’applique pas aux achats réalisés pour les besoins de l’activité professionnelle. Aucune clause ne prive toutefois le professionnel des droits impératifs qui lui sont applicables.</p></section>

    <section><h2>12. Données personnelles</h2><p>Les traitements nécessaires au compte, à la commande, à la livraison, au support et à la prévention de la fraude sont détaillés dans la <a href="{{ route('legal.privacy') }}">politique de confidentialité</a>.</p></section>

    <section>
        <h2>13. Réclamations et médiation de la consommation</h2>
        <p>En cas de difficulté, le consommateur adresse d’abord une réclamation écrite à AchatHub. Si aucune solution amiable n’est trouvée, il peut saisir gratuitement le médiateur de la consommation dont relève le vendeur :</p>
        <p><strong>{{ $missing('mediator_name') ? 'Médiateur à désigner et à renseigner par l’exploitant' : $settings->get('mediator_name') }}</strong><br>
            {{ $missing('mediator_address') ? 'Adresse à renseigner' : $settings->get('mediator_address') }}
            @if(!$missing('mediator_website'))<br><a href="{{ $settings->get('mediator_website') }}" rel="noopener noreferrer">{{ $settings->get('mediator_website') }}</a>@endif
        </p>
        <p>Le droit français s’applique sous réserve des règles impératives protectrices du consommateur. Le consommateur peut saisir la juridiction compétente selon les règles applicables.</p>
    </section>

    <section>
        <h2>Formulaire type de rétractation</h2>
        <p>À compléter et envoyer uniquement si vous souhaitez vous rétracter :</p>
        <blockquote class="border-start border-3 ps-3">
            <p>À l’attention d’AchatHub, {{ $missing('returns_address') ? '[adresse de retour à compléter]' : $settings->get('returns_address') }}, {{ $supportEmail }}.</p>
            <p>Je vous notifie ma rétractation du contrat portant sur la vente du bien suivant : __________<br>
                Commandé le / reçu le : __________<br>
                Numéro de commande : __________<br>
                Nom du consommateur : __________<br>
                Adresse du consommateur : __________<br>
                Date : __________<br>
                Signature (uniquement pour un envoi papier) : __________</p>
        </blockquote>
    </section>
</div>
@endsection
