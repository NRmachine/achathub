<?php

namespace App\Support;

final class OptimizedAsset
{
    public static function image(?string $path): ?string
    {
        return match ($path) {
            '/assets/achathub-mark.png' => '/assets/achathub-mark.webp',
            '/assets/presentoir-achathub.png' => '/assets/presentoir-achathub.webp',
            '/assets/category-accessoires.png' => '/assets/category-accessoires.webp',
            '/assets/category-chargeurs-cables.png' => '/assets/category-chargeurs-cables.webp',
            '/assets/category-pieces-detachees.png' => '/assets/category-pieces-detachees.webp',
            '/assets/achathub-hero-accessoires.png' => '/assets/achathub-hero-accessoires.webp',
            default => $path,
        };
    }
}
