<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ \App\Support\Seo::root() }}/</loc></url>
    <url><loc>{{ \App\Support\Seo::root() }}/devenir-revendeur</loc></url>
    <url><loc>{{ \App\Support\Seo::root() }}/support</loc></url>
@foreach($categories as $category)
    <url><loc>{{ \App\Support\Seo::root() }}/?category={{ urlencode($category->slug) }}</loc></url>
@endforeach
@foreach($products as $product)
    <url>
        <loc>{{ \App\Support\Seo::root() }}{{ route('products.show', $product, false) }}</loc>
        <lastmod>{{ $product->updated_at->toAtomString() }}</lastmod>
    </url>
@endforeach
</urlset>
