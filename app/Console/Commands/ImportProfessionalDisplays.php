<?php

namespace App\Console\Commands;

use App\Models\ProfessionalDisplay;
use App\Models\ProfessionalProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportProfessionalDisplays extends Command
{
    protected $signature = 'achathub:import-displays {file=storage/app/imports/presentoirs-dpower.csv}';

    protected $description = 'Importe les trois présentoirs professionnels et leur contenu depuis le CSV D-Power';

    public function handle(): int
    {
        $path = base_path($this->argument('file'));
        if (! is_file($path)) {
            $this->error("Fichier introuvable : {$path}");

            return self::FAILURE;
        }

        $blocks = $this->parse($path);
        if (count($blocks) !== 3) {
            $this->error('Le fichier doit contenir exactement trois présentoirs.');

            return self::FAILURE;
        }

        $definitions = [
            ['name' => 'Petit présentoir comptoir', 'slug' => 'petit-presentoir', 'description' => 'Une sélection compacte de câbles, chargeurs, écouteurs et adaptateurs, idéale près d’une caisse ou dans un petit commerce.'],
            ['name' => 'Présentoir moyen', 'slug' => 'presentoir-moyen', 'description' => 'Une offre équilibrée d’accessoires essentiels et de produits à forte rotation pour les commerces de proximité.'],
            ['name' => 'Grand présentoir', 'slug' => 'grand-presentoir', 'description' => 'La sélection la plus complète de câbles, chargeurs, audio et batteries externes pour un point de vente à fort passage.'],
        ];

        DB::transaction(function () use ($blocks, $definitions) {
            foreach ($blocks as $index => $block) {
                $definition = $definitions[$index];
                $display = ProfessionalDisplay::updateOrCreate(
                    ['slug' => $definition['slug']],
                    $definition + [
                        'wholesale_price_ht' => $block['total'],
                        'vat_rate' => 20,
                        'image' => '/assets/presentoir-achathub.png',
                        'active' => true,
                        'sort_order' => $index + 1,
                    ]
                );

                $items = [];
                foreach ($block['products'] as $row) {
                    $product = ProfessionalProduct::firstOrNew(['sku' => $row['sku']]);
                    $product->fill([
                        'name' => $row['name'],
                        'category' => $this->category($row['name']),
                        'wholesale_price_ht' => $row['price'],
                        'minimum_order_quantity' => max(3, $row['quantity']),
                        'image' => $row['image'],
                        'description' => 'Produit D-Power destiné à la revente en boutique. Tarif professionnel affiché hors taxes.',
                        'active' => true,
                    ]);
                    if (! $product->exists) {
                        $product->stock = 100;
                    }
                    $product->save();
                    $items[$product->id] = ['quantity' => $row['quantity'], 'unit_price_ht' => $row['price']];
                }
                $display->products()->sync($items);
                $this->line("{$display->name} : ".count($items)." références, {$block['total']} € HT");
            }
        });

        $this->info('Catalogue professionnel importé avec succès.');

        return self::SUCCESS;
    }

    private function parse(string $path): array
    {
        $handle = fopen($path, 'r');
        $blocks = [];
        $current = null;

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $first = trim((string) ($row[0] ?? ''));
            if (preg_match('/^nom (de|du) produit/i', $first)) {
                if ($current !== null && $current['products'] !== []) {
                    $blocks[] = $current;
                }
                $current = ['products' => [], 'total' => null];

                continue;
            }

            if ($current === null) {
                continue;
            }

            $totalCell = collect($row)->first(fn ($cell) => str_contains(mb_strtolower((string) $cell), 'prix total de presentoir'));
            if ($totalCell !== null) {
                preg_match('/([0-9]+(?:[,.][0-9]+)?)\s*€/u', (string) $totalCell, $matches);
                $current['total'] = $this->number($matches[1] ?? '0');

                continue;
            }

            $quantity = $this->number($row[1] ?? null);
            $price = $this->number($row[2] ?? null);
            $sku = trim((string) ($row[3] ?? ''));
            if ($first !== '' && $quantity > 0 && $price > 0 && $sku !== '') {
                $current['products'][] = [
                    'name' => trim((string) preg_replace('/\s+/', ' ', $first)),
                    'quantity' => (int) $quantity,
                    'price' => $price,
                    'sku' => $sku,
                    'image' => trim((string) ($row[4] ?? '')) ?: null,
                ];
            }
        }
        fclose($handle);

        if ($current !== null && $current['products'] !== []) {
            $blocks[] = $current;
        }

        return $blocks;
    }

    private function number(mixed $value): float
    {
        return (float) str_replace(',', '.', trim((string) $value));
    }

    private function category(string $name): string
    {
        $name = mb_strtolower($name);

        return match (true) {
            str_contains($name, 'cable') => 'Câbles',
            str_contains($name, 'headphone') => 'Audio',
            str_contains($name, 'power bank'), str_contains($name, 'powerbank') => 'Batteries externes',
            str_contains($name, 'car charger') => 'Chargeurs voiture',
            str_contains($name, 'charger') => 'Chargeurs secteur',
            str_contains($name, 'adaptor') => 'Adaptateurs',
            default => 'Accessoires',
        };
    }
}
