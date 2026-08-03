@php
    use App\Support\Seo;

    $seoTitle = trim($__env->yieldContent('title', 'AchatHub - Tout acheter, au meilleur prix'));
    $seoDescription = trim($__env->yieldContent('meta_description', 'AchatHub, boutique en ligne d’accessoires de téléphonie et de pièces détachées.'));
    $seoCanonical = trim($__env->yieldContent('canonical', Seo::canonical(request())));
    $seoRobots = trim($__env->yieldContent('robots', Seo::robots(request())));
    $seoType = trim($__env->yieldContent('og_type', 'website'));
    $seoImage = trim($__env->yieldContent('og_image', Seo::absoluteUrl('/assets/achathub-hero-accessoires.webp?v=20260802b')));
@endphp
<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
<meta name="robots" content="{{ $seoRobots }}">
<link rel="canonical" href="{{ $seoCanonical }}">
@hasSection('previous_url')<link rel="prev" href="@yield('previous_url')">@endif
@hasSection('next_url')<link rel="next" href="@yield('next_url')">@endif
<meta property="og:locale" content="fr_FR">
<meta property="og:site_name" content="AchatHub">
<meta property="og:type" content="{{ $seoType }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $seoCanonical }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImage }}">
@unless(str_contains($seoRobots, 'noindex'))
<script type="application/ld+json">{!! Seo::jsonLd(Seo::siteGraph()) !!}</script>
@endunless
@stack('structured-data')
