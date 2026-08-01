<?php

namespace App\Support;

final class OptimizedAsset
{
    public static function image(?string $path): ?string
    {
        return match ($path) {
            '/assets/achathub-mark.png', '/assets/achathub-mark.webp' => '/assets/achathub-mark.webp?v=20260802b',
            '/assets/presentoir-achathub.png', '/assets/presentoir-achathub.webp' => '/assets/presentoir-achathub.webp?v=20260802b',
            '/assets/category-accessoires.png', '/assets/category-accessoires.webp' => '/assets/category-accessoires.webp?v=20260802b',
            '/assets/category-chargeurs-cables.png', '/assets/category-chargeurs-cables.webp' => '/assets/category-chargeurs-cables.webp?v=20260802b',
            '/assets/category-pieces-detachees.png', '/assets/category-pieces-detachees.webp' => '/assets/category-pieces-detachees.webp?v=20260802b',
            '/assets/achathub-hero-accessoires.png', '/assets/achathub-hero-accessoires.webp' => '/assets/achathub-hero-accessoires.webp?v=20260802b',
            default => $path,
        };
    }
}
