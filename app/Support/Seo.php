<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class Seo
{
    /** @var list<string> */
    private const FACET_PARAMETERS = [
        'q',
        'family',
        'subcategory',
        'brand',
        'min_price',
        'max_price',
        'in_stock',
        'sort',
    ];

    public static function root(): string
    {
        return rtrim((string) config('seo.canonical_url'), '/');
    }

    public static function absoluteUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return self::root().'/'.ltrim($url, '/');
    }

    public static function internalUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        if ($query) {
            parse_str($query, $parameters);
            if (($parameters['page'] ?? null) === '1' || ($parameters['page'] ?? null) === 1) {
                unset($parameters['page']);
            }
            $query = http_build_query($parameters);
        }

        return self::root().$path.($query ? '?'.$query : '');
    }

    public static function canonical(Request $request): string
    {
        $routeName = $request->route()?->getName();

        if ($routeName === 'products.show') {
            /** @var Product|null $product */
            $product = $request->route('product');

            return self::root().route('products.show', $product, false);
        }

        if ($routeName === 'home') {
            $query = array_filter([
                'category' => $request->string('category')->toString() ?: null,
                'page' => $request->integer('page', 1) > 1 ? $request->integer('page') : null,
            ]);

            return self::root().($query === [] ? '/' : '/?'.http_build_query($query));
        }

        return self::root().'/'.ltrim($request->path(), '/');
    }

    public static function robots(Request $request): string
    {
        $routeName = $request->route()?->getName();
        $publicRoutes = ['home', 'products.show', 'reseller.index', 'support.index'];
        $isPublicLegalPage = is_string($routeName) && Str::startsWith($routeName, 'legal.');

        if (! in_array($routeName, $publicRoutes, true) && ! $isPublicLegalPage) {
            return 'noindex,follow';
        }

        if ($routeName === 'home' && $request->hasAny(self::FACET_PARAMETERS)) {
            return 'noindex,follow';
        }

        return 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1';
    }

    /**
     * @return array{title:string,description:string,canonical:string,robots:string,previous:?string,next:?string}
     */
    public static function catalog(
        Request $request,
        ?Category $category,
        LengthAwarePaginator $products,
    ): array {
        $page = $products->currentPage();
        $pageSuffix = $page > 1 ? ' — page '.$page : '';
        $search = trim($request->string('q')->toString());
        $hasFacet = $request->hasAny(self::FACET_PARAMETERS);
        $invalidCategory = $request->filled('category') && ! $category;

        if ($search !== '') {
            $title = 'Recherche « '.Str::limit($search, 55, '').' »'.$pageSuffix.' | AchatHub';
            $description = 'Résultats du catalogue AchatHub pour « '.Str::limit($search, 100, '').' ».';
        } elseif ($category) {
            $title = $category->name.$pageSuffix.' | Catalogue AchatHub';
            $description = $category->description
                ?: 'Découvrez '.number_format($products->total(), 0, ',', ' ').' produits dans la catégorie '.$category->name.' sur AchatHub.';
        } else {
            $title = 'Accessoires téléphonie et pièces détachées'.$pageSuffix.' | AchatHub';
            $description = 'Découvrez le catalogue AchatHub : accessoires de téléphonie, chargeurs, câbles et pièces détachées avec prix et disponibilité affichés.';
        }

        $canonicalQuery = array_filter([
            'category' => $category?->slug,
            'page' => $page > 1 ? $page : null,
        ]);

        return [
            'title' => $title,
            'description' => Str::limit(trim($description), 160, ''),
            'canonical' => self::root().($canonicalQuery === [] ? '/' : '/?'.http_build_query($canonicalQuery)),
            'robots' => $hasFacet || $invalidCategory || ($products->isEmpty() && $page > 1)
                ? 'noindex,follow'
                : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1',
            'previous' => self::internalUrl($products->previousPageUrl()),
            'next' => self::internalUrl($products->nextPageUrl()),
        ];
    }

    /** @return array<string, mixed> */
    public static function siteGraph(): array
    {
        $root = self::root();

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $root.'/#organization',
                    'name' => 'AchatHub',
                    'url' => $root.'/',
                    'logo' => self::absoluteUrl('/assets/achathub-mark.webp?v=20260802b'),
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $root.'/#website',
                    'url' => $root.'/',
                    'name' => 'AchatHub',
                    'inLanguage' => 'fr-FR',
                    'publisher' => ['@id' => $root.'/#organization'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function product(Product $product): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => self::root().route('products.show', $product, false).'#product',
            'name' => $product->name,
            'url' => self::root().route('products.show', $product, false),
            'sku' => $product->sku,
            'image' => collect([$product->image, ...($product->images ?? [])])
                ->filter()
                ->unique()
                ->map(fn (string $image): ?string => OptimizedAsset::image($image))
                ->filter()
                ->map(fn (string $image): string => self::absoluteUrl($image))
                ->values()
                ->all(),
            'offers' => [
                '@type' => 'Offer',
                'url' => self::root().route('products.show', $product, false),
                'priceCurrency' => 'EUR',
                'price' => number_format((float) $product->price, 2, '.', ''),
                'availability' => $product->stock > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'itemCondition' => self::condition($product->condition),
                'seller' => ['@id' => self::root().'/#organization'],
            ],
        ];

        if ($product->description) {
            $data['description'] = trim($product->description);
        }

        if ($product->brand) {
            $data['brand'] = ['@type' => 'Brand', 'name' => $product->brand];
        }

        if ($data['image'] === []) {
            unset($data['image']);
        }

        return $data;
    }

    /**
     * @param  list<array{name:string,url?:string|null}>  $items
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(function (array $item, int $index): array {
                $entry = [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                ];

                if (! empty($item['url'])) {
                    $entry['item'] = self::absoluteUrl($item['url']);
                }

                return $entry;
            })->all(),
        ];
    }

    public static function jsonLd(array $data): string
    {
        return (string) json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        );
    }

    private static function condition(?string $condition): string
    {
        return match (Str::lower(trim((string) $condition))) {
            'occasion', 'reconditionné', 'reconditionne' => 'https://schema.org/RefurbishedCondition',
            'endommagé', 'endommage' => 'https://schema.org/DamagedCondition',
            'usagé', 'usage', 'utilisé', 'utilise' => 'https://schema.org/UsedCondition',
            default => 'https://schema.org/NewCondition',
        };
    }
}
