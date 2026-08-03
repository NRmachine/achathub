@if(!request()->cookie('cookie_consent'))
<aside class="cookie-banner" aria-labelledby="cookie-banner-title" aria-describedby="cookie-banner-description">
    <div>
        <strong id="cookie-banner-title">Votre choix, simplement</strong>
        <p id="cookie-banner-description">AchatHub utilise les cookies indispensables au panier, à la connexion et à la sécurité. Aucun traceur optionnel n’est activé avant votre accord.</p>
        <a href="{{ route('legal.cookies') }}">Comprendre et modifier mes préférences</a>
    </div>
    <div class="cookie-actions">
        <form method="post" action="{{ route('cookies.consent') }}">@csrf<input type="hidden" name="choice" value="refused"><button class="btn btn-light">Tout refuser</button></form>
        <form method="post" action="{{ route('cookies.consent') }}">@csrf<input type="hidden" name="choice" value="accepted"><button class="btn btn-light">Tout accepter</button></form>
    </div>
</aside>
@endif
