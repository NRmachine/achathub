<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_exposes_canonical_metadata_and_site_identity(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<title>Accessoires téléphonie et pièces détachées | AchatHub</title>', false)
            ->assertSee('<link rel="canonical" href="https://www.achathub.com/">', false)
            ->assertSee('index,follow,max-image-preview:large', false)
            ->assertSee('property="og:site_name" content="AchatHub"', false)
            ->assertSee('rel="icon" type="image/png"', false)
            ->assertSee('achathub-logo.png?v=20260803b', false)
            ->assertSee('"@type":"Organization"', false)
            ->assertSee('"@type":"WebSite"', false);
    }

    public function test_internal_search_and_facets_are_not_indexed_or_canonicalized_as_duplicates(): void
    {
        $this->get(route('home', ['q' => 'chargeur', 'brand' => 'AchatHub', 'sort' => 'price_asc']))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('<link rel="canonical" href="https://www.achathub.com/">', false)
            ->assertDontSee('"@type":"WebSite"', false);
    }

    public function test_product_page_uses_only_real_product_values_in_structured_data(): void
    {
        $category = Category::create(['name' => 'Chargeurs', 'slug' => 'chargeurs']);
        $product = Product::create([
            'category_id' => $category->id,
            'sku' => 'SEO-USB-1',
            'name' => 'Chargeur USB-C 30 W',
            'slug' => 'chargeur-usb-c-30-w',
            'brand' => 'AchatHub',
            'price' => 24.90,
            'stock' => 8,
            'image' => '/assets/category-chargeurs-cables.png',
            'description' => 'Chargeur USB-C de 30 W.',
            'rating' => 4.8,
            'reviews_count' => 99,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<link rel="canonical" href="https://www.achathub.com/produits/chargeur-usb-c-30-w">', false)
            ->assertSee('"@type":"Product"', false)
            ->assertSee('"sku":"SEO-USB-1"', false)
            ->assertSee('"price":"24.90"', false)
            ->assertSee('"availability":"https://schema.org/InStock"', false)
            ->assertSee('category-chargeurs-cables.webp', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertDontSee('aggregateRating', false);
    }

    public function test_sitemap_lists_only_active_catalog_content(): void
    {
        $category = Category::create(['name' => 'Câbles', 'slug' => 'cables', 'active' => true]);
        Product::create(['category_id' => $category->id, 'sku' => 'SEO-ACTIVE', 'name' => 'Câble actif', 'slug' => 'cable-actif', 'price' => 9.90, 'stock' => 2, 'active' => true]);
        Product::create(['category_id' => $category->id, 'sku' => 'SEO-HIDDEN', 'name' => 'Câble masqué', 'slug' => 'cable-masque', 'price' => 9.90, 'stock' => 2, 'active' => false]);

        $response = $this->get(route('seo.sitemap'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('https://www.achathub.com/?category=cables', false)
            ->assertSee('https://www.achathub.com/produits/cable-actif', false)
            ->assertDontSee('cable-masque', false);

        $this->assertNotFalse(simplexml_load_string($response->getContent()));
    }

    public function test_private_and_transactional_pages_are_not_indexable(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_catalog_pagination_links_use_the_canonical_origin(): void
    {
        $category = Category::create(['name' => 'Accessoires', 'slug' => 'accessoires']);
        foreach (range(1, 25) as $index) {
            Product::create([
                'category_id' => $category->id,
                'sku' => 'SEO-PAGE-'.$index,
                'name' => 'Produit '.$index,
                'slug' => 'produit-'.$index,
                'price' => 10,
                'stock' => 1,
            ]);
        }

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<link rel="next" href="https://www.achathub.com/?page=2">', false);

        $this->get(route('home', ['page' => 2]))
            ->assertOk()
            ->assertSee('<link rel="prev" href="https://www.achathub.com/">', false)
            ->assertSee('<link rel="canonical" href="https://www.achathub.com/?page=2">', false);
    }
}
