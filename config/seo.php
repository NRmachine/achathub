<?php

return [
    /*
    | AchatHub uses one stable origin for canonical URLs and its sitemap.
    | This avoids exposing Vercel preview domains as competing URLs.
    */
    'canonical_url' => rtrim(env('SEO_CANONICAL_URL', 'https://www.achathub.com'), '/'),
];
