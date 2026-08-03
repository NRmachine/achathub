<link rel="icon" type="image/png" href="{{ asset('assets/achathub-logo.png') }}?v=20260803b">
<link rel="apple-touch-icon" href="{{ asset('assets/achathub-logo.png') }}?v=20260803b">
<link rel="preload" href="{{ asset('assets/achathub-logo.webp') }}?v=20260803b" as="image" type="image/webp">
<style>
    .ah-page-loader{position:fixed;z-index:2147483647;inset:0;display:grid;place-items:center;background:#fff;opacity:1;visibility:visible;transition:opacity .18s ease,visibility .18s ease}
    .ah-page-loader.is-hidden{opacity:0;visibility:hidden;pointer-events:none}
    .ah-page-loader img{width:82px;height:auto;filter:drop-shadow(0 10px 18px rgba(7,17,38,.14));animation:ah-logo-spin 1s linear infinite;will-change:transform}
    .ah-page-loader .ah-loader-label{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
    @keyframes ah-logo-spin{to{transform:rotate(360deg)}}
    @media(prefers-reduced-motion:reduce){.ah-page-loader{transition:none}.ah-page-loader img{animation:ah-logo-pulse 1s ease-in-out infinite alternate}@keyframes ah-logo-pulse{to{opacity:.55}}}
    @media print{.ah-page-loader{display:none!important}}
</style>
