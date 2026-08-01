<?php

namespace App\Services\Suppliers;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LcdPhoneClient
{
    private CookieJar $cookies;

    private bool $authenticated = false;

    public function __construct()
    {
        $this->cookies = new CookieJar;
    }

    public function login(): void
    {
        $email = trim((string) config('suppliers.lcd_phone.email'));
        $password = (string) config('suppliers.lcd_phone.password');

        if ($email === '' || $password === '') {
            throw new RuntimeException('Les identifiants LCD Phone ne sont pas configurés sur le serveur.');
        }

        $loginUrl = (string) config('suppliers.lcd_phone.login_url');
        $this->assertSupplierUrl($loginUrl);
        $this->request()->get($loginUrl)->throw();
        $response = $this->request()->asForm()->post($loginUrl, [
            'email' => $email,
            'password' => $password,
            'submitLogin' => '1',
        ])->throw();

        $body = $response->body();
        if (! str_contains($body, 'mylogout') && ! str_contains($body, '/deconnexion')) {
            throw new RuntimeException('Connexion LCD Phone refusée. Vérifiez les identifiants du compte fournisseur.');
        }

        $this->authenticated = true;
    }

    public function discoverPage(string $categoryUrl, int $page = 1): array
    {
        $url = $this->withPage($categoryUrl, $page);

        return $this->parseDiscoverPageHtml($this->get($url), $url);
    }

    public function parseDiscoverPageHtml(string $html, string $url): array
    {
        $xpath = $this->xpath($html);
        $products = [];
        $productList = $this->first($xpath, "//*[@id='js-product-list']");
        $articleQuery = ".//article[contains(concat(' ', normalize-space(@class), ' '), ' product-container ') or contains(concat(' ', normalize-space(@class), ' '), ' product-miniature ')]";
        $articles = $productList ? $xpath->query($articleQuery, $productList) : null;

        // The supplier also renders promotional product-miniature blocks outside the
        // category list. Only use the global fallback for pages using the old theme.
        if (! $articles || $articles->length === 0) {
            $articles = $xpath->query("//article[contains(concat(' ', normalize-space(@class), ' '), ' product-miniature ')]");
        }

        foreach ($articles ?: [] as $article) {
            if (! $article instanceof DOMElement) {
                continue;
            }

            $link = $this->first($xpath, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' product-name ')]//a", $article)
                ?? $this->first($xpath, ".//a[contains(concat(' ', normalize-space(@class), ' '), ' product-thumbnail ')]", $article);
            if (! $link instanceof DOMElement || trim($link->getAttribute('href')) === '') {
                continue;
            }

            $image = $this->first($xpath, './/img', $article);
            $productId = trim($article->getAttribute('data-id-product'));
            if ($productId === '') {
                $productIdNode = $this->first($xpath, './/*[@data-id-product]', $article);
                $productId = $productIdNode instanceof DOMElement
                    ? trim($productIdNode->getAttribute('data-id-product'))
                    : '';
            }
            if ($productId === '') {
                $productIdInput = $this->first($xpath, ".//input[@name='id_product']", $article);
                $productId = $productIdInput instanceof DOMElement
                    ? trim($productIdInput->getAttribute('value'))
                    : '';
            }
            $productUrl = $this->absoluteUrl($link->getAttribute('href'));
            $products[$productId !== '' ? $productId : $productUrl] = [
                'supplier_product_id' => $productId !== '' ? $productId : $this->productIdFromUrl($productUrl),
                'name' => $this->clean($link->getAttribute('title') ?: $link->textContent),
                'url' => $this->withoutFragment($productUrl),
                'image' => $image instanceof DOMElement
                    ? $this->absoluteUrl($image->getAttribute('data-src') ?: $image->getAttribute('src'))
                    : null,
            ];
        }

        $maxPage = 1;
        foreach ($xpath->query("//*[@href and contains(@href, 'page=')]") ?: [] as $link) {
            if ($link instanceof DOMElement && preg_match('/(?:\?|&)page=(\d+)/', $link->getAttribute('href'), $match)) {
                $maxPage = max($maxPage, (int) $match[1]);
            }
        }

        return [
            'url' => $url,
            'breadcrumbs' => $this->breadcrumbs($xpath),
            'products' => array_values($products),
            'max_page' => $maxPage,
        ];
    }

    public function catalogTree(?string $url = null): array
    {
        $url ??= rtrim((string) config('suppliers.lcd_phone.base_url'), '/').'/fr/49-accessoires';

        return $this->parseCatalogTreeHtml($this->get($url));
    }

    public function parseCatalogTreeHtml(string $html): array
    {
        $xpath = $this->xpath($html);
        $tree = $this->first(
            $xpath,
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' category-tree ')][contains(concat(' ', normalize-space(@class), ' '), ' js-category-tree ')]",
        );
        if (! $tree) {
            throw new RuntimeException('Arborescence des catégories LCD Phone introuvable.');
        }

        $list = $this->first($xpath, './ul', $tree);

        return $list ? $this->parseCategoryList($xpath, $list) : [];
    }

    public function product(string $url): array
    {
        return $this->parseProductPageHtml($this->get($url), $url);
    }

    public function parseProductPageHtml(string $html, string $url): array
    {
        $xpath = $this->xpath($html);
        $pageText = $this->clean($xpath->document->textContent);
        $nameNode = $this->first($xpath, '//main//h1') ?? $this->first($xpath, '//h1');
        $name = $this->clean($nameNode?->textContent ?: 'Produit LCD Phone');
        $productId = $this->hiddenValue($xpath, 'id_product') ?: $this->productIdFromUrl($url);
        $pageEan = preg_match('/EAN-13\s*:?\s*(\d{8,14})/iu', $pageText, $eanMatch) ? $eanMatch[1] : null;
        $brandNode = $this->first($xpath, "//*[contains(concat(' ', normalize-space(@class), ' '), ' product-manufacturer ')]//span");
        $descriptionMeta = $this->first($xpath, "//meta[translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='description']");
        $brand = $brandNode ? $this->clean($brandNode->textContent) : null;
        $description = $descriptionMeta instanceof DOMElement ? $this->clean($descriptionMeta->getAttribute('content')) : null;
        $sourceCategory = $this->sourceCategory($url);
        $gallery = $this->galleryImages($xpath);
        $structuredProducts = $this->structuredProducts($xpath);
        $variants = [];

        // Browsers insert a <tbody> automatically, while the supplier's raw HTML does not.
        $rows = $xpath->query("//table[contains(concat(' ', normalize-space(@class), ' '), ' table_d_c ')]//tr");
        if ($rows && $rows->length > 0) {
            foreach ($rows as $row) {
                if (! $row instanceof DOMElement) {
                    continue;
                }

                $cells = [];
                foreach ($xpath->query('./td', $row) ?: [] as $cell) {
                    $cells[] = $cell;
                }
                if (count($cells) < 5) {
                    continue;
                }

                $stockText = $this->clean($cells[3]->textContent);
                $reference = $this->reference($this->clean($cells[2]->textContent));
                $variantId = $this->hiddenValue($xpath, 'id_product_attribute', $row);
                if ($variantId === null || $variantId === '') {
                    continue;
                }
                $rowProductId = $this->hiddenValue($xpath, 'id_product', $row) ?: $productId;
                $image = $this->first($xpath, './/img[@data-zoom-image or @src]', $row);
                $stock = $this->stock($stockText);
                $structured = $reference ? ($structuredProducts[$reference] ?? null) : null;
                $minimumOrderQuantity = $this->minimumOrderQuantity($xpath, $row);
                $variantImage = $image instanceof DOMElement
                    ? $this->absoluteUrl($image->getAttribute('data-zoom-image') ?: $image->getAttribute('src'))
                    : ($structured['image'] ?? null);
                $images = array_values(array_unique(array_filter([
                    $variantImage,
                    ...($structured['images'] ?? []),
                    ...$gallery,
                ])));

                $variants[] = [
                    'supplier_product_id' => (string) $rowProductId,
                    'supplier_variant_id' => (string) $variantId,
                    'supplier_reference' => $reference,
                    'ean' => $structured['ean'] ?? ($rows->length === 1 ? $pageEan : null),
                    'brand' => $brand,
                    'source_category' => $sourceCategory,
                    'name' => $name,
                    'variant_name' => $this->clean($cells[1]->textContent),
                    'description' => $description,
                    'supplier_url' => $this->withoutFragment($url),
                    'image' => $variantImage,
                    'images' => $images,
                    'purchase_price' => $this->money($this->clean($cells[4]->textContent)),
                    'minimum_order_quantity' => $minimumOrderQuantity,
                    'supplier_stock' => $stock,
                    'available' => $stock > 0 && ! str_contains(mb_strtolower($stockText), 'rupture'),
                ];
            }
        }

        if ($variants === []) {
            $referenceNode = $this->first($xpath, "//*[contains(concat(' ', normalize-space(@class), ' '), ' product-reference ')]//span");
            $reference = $referenceNode ? $this->clean($referenceNode->textContent) : null;
            $stockNode = $this->first($xpath, "//*[contains(concat(' ', normalize-space(@class), ' '), ' product-quantities ')]//*[@data-stock]");
            $stock = $stockNode instanceof DOMElement ? max(0, (int) $stockNode->getAttribute('data-stock')) : $this->stock($pageText);
            $priceMeta = $this->first($xpath, "//meta[@property='product:price:amount']");
            $imageMeta = $this->first($xpath, "//meta[@property='og:image']");
            $structured = $reference ? ($structuredProducts[$reference] ?? null) : collect($structuredProducts)->first();
            $variantImage = $imageMeta instanceof DOMElement ? $this->absoluteUrl($imageMeta->getAttribute('content')) : ($structured['image'] ?? null);
            $images = array_values(array_unique(array_filter([
                $variantImage,
                ...($structured['images'] ?? []),
                ...$gallery,
            ])));

            $variants[] = [
                'supplier_product_id' => (string) $productId,
                'supplier_variant_id' => (string) ($this->hiddenValue($xpath, 'id_product_attribute') ?: '0'),
                'supplier_reference' => $reference,
                'ean' => $structured['ean'] ?? $pageEan,
                'brand' => $brand,
                'source_category' => $sourceCategory,
                'name' => $name,
                'variant_name' => null,
                'description' => $description,
                'supplier_url' => $this->withoutFragment($url),
                'image' => $variantImage,
                'images' => $images,
                'purchase_price' => $priceMeta instanceof DOMElement ? $this->money($priceMeta->getAttribute('content')) : null,
                'minimum_order_quantity' => $this->minimumOrderQuantity($xpath),
                'supplier_stock' => $stock,
                'available' => $stock > 0 && ! str_contains(mb_strtolower($pageText), 'rupture de stock'),
            ];
        }

        return [
            'supplier_product_id' => (string) $productId,
            'name' => $name,
            'url' => $this->withoutFragment($url),
            'breadcrumbs' => $this->breadcrumbs($xpath),
            'variants' => $variants,
        ];
    }

    private function get(string $url): string
    {
        $this->assertSupplierUrl($url);
        if (! $this->authenticated) {
            $this->login();
        }

        $response = $this->request()->get($url)->throw();
        if (str_contains($response->body(), 'id="login-form"')) {
            $this->authenticated = false;
            $this->login();
            $response = $this->request()->get($url)->throw();
        }

        return $response->body();
    }

    private function request(): PendingRequest
    {
        return Http::withOptions(['cookies' => $this->cookies, 'allow_redirects' => true])
            ->timeout((int) config('suppliers.lcd_phone.timeout', 30))
            ->retry(2, 750)
            ->withHeaders([
                'Accept-Language' => 'fr-FR,fr;q=0.9',
                'User-Agent' => 'AchatHub Stock Sync/1.0 (+https://achathub.fr)',
            ]);
    }

    private function xpath(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function structuredProducts(DOMXPath $xpath): array
    {
        $products = [];
        foreach ($xpath->query("//script[@type='application/ld+json']") ?: [] as $script) {
            $data = json_decode(trim($script->textContent), true);
            if (! is_array($data) || ($data['@type'] ?? null) !== 'Product' || blank($data['sku'] ?? null)) {
                continue;
            }
            $offer = $data['offers'] ?? [];
            if (array_is_list($offer)) {
                $offer = $offer[0] ?? [];
            }
            $images = $data['image'] ?? [];
            if (! is_array($images)) {
                $images = [$images];
            }
            $products[trim((string) $data['sku'])] = [
                'ean' => $offer['gtin13'] ?? $data['gtin13'] ?? null,
                'image' => filled($images[0] ?? null) ? $this->absoluteUrl($images[0]) : null,
                'images' => array_values(array_filter(array_map(fn ($image) => $this->absoluteUrl((string) $image), $images))),
            ];
        }

        return $products;
    }

    private function parseCategoryList(DOMXPath $xpath, DOMNode $list, array $parentPath = []): array
    {
        $nodes = [];
        foreach ($xpath->query('./li', $list) ?: [] as $item) {
            if (! $item instanceof DOMElement) {
                continue;
            }

            $link = $this->first($xpath, './a[@data-category-id]', $item);
            if (! $link instanceof DOMElement) {
                continue;
            }

            $name = $this->clean($link->getAttribute('title') ?: $link->textContent);
            $categoryId = trim($link->getAttribute('data-category-id'));
            $sourceUrl = $this->absoluteUrl($link->getAttribute('href'));
            if ($name === '' || $categoryId === '' || ! $sourceUrl) {
                continue;
            }

            $path = [...$parentPath, $name];
            $submenu = $this->first(
                $xpath,
                "./div[contains(concat(' ', normalize-space(@class), ' '), ' category-sub-menu ')]/ul",
                $item,
            );
            $children = $submenu ? $this->parseCategoryList($xpath, $submenu, $path) : [];
            $nodes[] = [
                'supplier_category_id' => $categoryId,
                'name' => $name,
                'url' => $this->withoutFragment($sourceUrl),
                'depth' => max(0, (int) $item->getAttribute('data-depth')),
                'path' => $path,
                'is_leaf' => $children === [],
                'children' => $children,
            ];
        }

        return $nodes;
    }

    private function breadcrumbs(DOMXPath $xpath): array
    {
        $items = [];
        foreach ($xpath->query("//nav[contains(concat(' ', normalize-space(@class), ' '), ' breadcrumb ')]//li") ?: [] as $item) {
            if (! $item instanceof DOMElement) {
                continue;
            }

            $link = $this->first($xpath, ".//a[contains(concat(' ', normalize-space(@class), ' '), ' item-name ')]", $item);
            if (! $link instanceof DOMElement) {
                continue;
            }

            $name = $this->clean($link->textContent);
            $url = $this->absoluteUrl($link->getAttribute('href'));
            $categoryId = $url ? $this->categoryIdFromUrl($url) : null;
            if ($name === '' || mb_strtolower($name) === 'accueil' || ! $url || ! $categoryId) {
                continue;
            }

            $items[] = [
                'supplier_category_id' => $categoryId,
                'name' => $name,
                'url' => $this->withoutFragment($url),
            ];
        }

        return $items;
    }

    private function first(DOMXPath $xpath, string $query, ?DOMNode $context = null): ?DOMNode
    {
        $nodes = $xpath->query($query, $context);

        return $nodes && $nodes->length > 0 ? $nodes->item(0) : null;
    }

    private function hiddenValue(DOMXPath $xpath, string $name, ?DOMNode $context = null): ?string
    {
        $node = $this->first($xpath, ".//input[@name='{$name}']", $context ?? $xpath->document);

        return $node instanceof DOMElement ? trim($node->getAttribute('value')) : null;
    }

    private function minimumOrderQuantity(DOMXPath $xpath, ?DOMNode $context = null): int
    {
        $input = $this->first($xpath, ".//input[@name='qty']", $context ?? $xpath->document);
        if (! $input instanceof DOMElement) {
            return 1;
        }

        $minimum = (int) ($input->getAttribute('min') ?: $input->getAttribute('value'));

        return max(1, $minimum);
    }

    private function galleryImages(DOMXPath $xpath): array
    {
        $images = [];
        foreach ($xpath->query("//*[@id='js_mfp_gallery']//*[@data-mfp-src]") ?: [] as $node) {
            if ($node instanceof DOMElement && filled($node->getAttribute('data-mfp-src'))) {
                $images[] = $this->absoluteUrl($node->getAttribute('data-mfp-src'));
            }
        }

        return array_values(array_unique(array_filter($images)));
    }

    private function sourceCategory(string $url): ?string
    {
        $segments = array_values(array_filter(explode('/', trim((string) parse_url($url, PHP_URL_PATH), '/'))));
        $fileIndex = collect($segments)->search(fn (string $segment) => str_ends_with($segment, '.html'));
        if ($fileIndex === false || $fileIndex < 1) {
            return null;
        }

        $category = preg_replace('/^\d+-/', '', $segments[$fileIndex - 1]);

        return ucwords(str_replace('-', ' ', $category));
    }

    private function stock(string $text): int
    {
        if (str_contains(mb_strtolower($text), 'rupture')) {
            return 0;
        }
        if (preg_match('/(?:Stock\s*:\s*|En stock\s+)([\d\s]+)/iu', $text, $match)) {
            return max(0, (int) preg_replace('/\D/', '', $match[1]));
        }

        return 0;
    }

    private function money(string $value): ?float
    {
        $value = preg_replace('/[^\d,.-]/u', '', str_replace(["\u{00A0}", ' '], '', $value));
        if ($value === '' || $value === null) {
            return null;
        }
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function reference(string $value): ?string
    {
        $value = preg_replace('/^.*?Référence\s*:?\s*/iu', '', $value);
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return $value !== '' ? mb_substr($value, 0, 255) : null;
    }

    private function clean(?string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function absoluteUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return rtrim((string) config('suppliers.lcd_phone.base_url'), '/').'/'.ltrim($url, '/');
    }

    private function withoutFragment(string $url): string
    {
        return explode('#', $url, 2)[0];
    }

    private function withPage(string $url, int $page): string
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        if ($page > 1) {
            $query['page'] = $page;
        } else {
            unset($query['page']);
        }
        $base = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? 'lcd-phone.com').($parts['path'] ?? '');

        return $base.($query ? '?'.http_build_query($query) : '');
    }

    private function productIdFromUrl(string $url): string
    {
        return preg_match('#/(\d+)(?:-\d+)?-[^/]+\.html#', $url, $match) ? $match[1] : sha1($url);
    }

    private function categoryIdFromUrl(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return preg_match('#/(\d+)-[^/]+/?$#', $path, $match) ? $match[1] : null;
    }

    private function assertSupplierUrl(string $url): void
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host !== 'lcd-phone.com' && ! str_ends_with($host, '.lcd-phone.com')) {
            throw new RuntimeException('Seules les URL officielles lcd-phone.com sont autorisées.');
        }
    }
}
