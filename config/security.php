<?php

return [
    /*
    | Only these public hosts may be used to build absolute application URLs.
    | Vercel preview URLs are constrained to deployments prefixed "achathub".
    */
    'trusted_hosts' => [
        '^achathub\.com$',
        '^www\.achathub\.com$',
        '^achathub[a-z0-9-]*\.vercel\.app$',
        '^achathub\.test$',
        '^localhost$',
        '^127\.0\.0\.1$',
    ],
];
