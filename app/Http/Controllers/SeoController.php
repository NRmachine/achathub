<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $categories = Category::query()
            ->where('active', true)
            ->whereHas('products', fn ($query) => $query->where('active', true))
            ->orderBy('id')
            ->get(['slug']);

        $products = Product::query()
            ->where('active', true)
            ->orderBy('id')
            ->get(['slug', 'updated_at']);

        return response()
            ->view('seo.sitemap', compact('categories', 'products'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
