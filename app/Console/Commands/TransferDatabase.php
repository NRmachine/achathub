<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

#[Signature('achathub:transfer-database
    {--source=sqlite : Connexion locale AchatHub}
    {--target=pgsql : Connexion PostgreSQL de destination}
    {--truncate : Vide les tables AchatHub de destination avant la copie}')]
#[Description('Transfère les données locales AchatHub vers PostgreSQL sans accéder à Phone Life')]
class TransferDatabase extends Command
{
    /**
     * Les tables sont classées dans l'ordre de leurs dépendances.
     * Les sessions, caches et tâches en attente ne sont volontairement pas migrés.
     *
     * @var array<string, string>
     */
    private const TABLES = [
        'users' => 'id',
        'categories' => 'id',
        'products' => 'id',
        'support_messages' => 'id',
        'addresses' => 'id',
        'reseller_requests' => 'id',
        'wishlist_items' => 'id',
        'orders' => 'id',
        'order_items' => 'id',
        'order_status_events' => 'id',
        'return_requests' => 'id',
        'return_request_items' => 'id',
        'product_reviews' => 'id',
        'professional_displays' => 'id',
        'professional_products' => 'id',
        'professional_display_items' => 'id',
        'professional_orders' => 'id',
        'professional_order_items' => 'id',
        'professional_preorders' => 'id',
        'conversations' => 'id',
        'conversation_messages' => 'id',
        'site_settings' => 'id',
        'data_rights_requests' => 'id',
        'supplier_sync_runs' => 'id',
        'supplier_catalog_nodes' => 'id',
        'supplier_products' => 'id',
        'supplier_stock_changes' => 'id',
        'supplier_catalog_assignments' => 'id',
    ];

    public function handle(): int
    {
        $sourceName = (string) $this->option('source');
        $targetName = (string) $this->option('target');
        $source = DB::connection($sourceName);
        $target = DB::connection($targetName);

        try {
            $this->guardConnections($source, $target);
            $this->assertSchema($sourceName, $targetName);

            if ($this->option('truncate')) {
                $this->truncateTarget($targetName);
            } elseif ($this->targetContainsCommerceData($target)) {
                $this->components->error('La destination contient déjà des données. Relancez avec --truncate uniquement après vérification de la cible.');

                return self::FAILURE;
            }

            $totalRows = 0;
            foreach (self::TABLES as $table => $orderColumn) {
                $totalRows += $this->copyTable($source, $target, $targetName, $table, $orderColumn);
            }

            $this->components->info("Transfert terminé : {$totalRows} ligne(s) AchatHub copiée(s).");
            $this->components->warn('Les sessions et files de tâches n’ont pas été copiées : les utilisateurs devront se reconnecter.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function guardConnections(ConnectionInterface $source, ConnectionInterface $target): void
    {
        $sourceDatabase = mb_strtolower((string) $source->getDatabaseName());
        $targetDatabase = mb_strtolower((string) $target->getDatabaseName());

        if (str_contains($sourceDatabase, 'phone') && str_contains($sourceDatabase, 'life')) {
            throw new RuntimeException('Sécurité : la base Phone Life ne peut jamais être utilisée comme source.');
        }

        if (! in_array($source->getDriverName(), ['sqlite', 'mysql'], true) || $target->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Le transfert exige une source locale AchatHub SQLite ou MySQL et une destination PostgreSQL.');
        }

        if ($sourceDatabase === $targetDatabase && $source->getDriverName() === $target->getDriverName()) {
            throw new RuntimeException('La source et la destination doivent être différentes.');
        }
    }

    private function assertSchema(string $source, string $target): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            if (! Schema::connection($source)->hasTable($table)) {
                throw new RuntimeException("Table source absente : {$table}.");
            }
            if (! Schema::connection($target)->hasTable($table)) {
                throw new RuntimeException("Table cible absente : {$table}. Lancez d’abord php artisan migrate --force.");
            }
        }
    }

    private function targetContainsCommerceData(ConnectionInterface $target): bool
    {
        foreach (['users', 'products', 'orders', 'professional_orders', 'supplier_products'] as $table) {
            if ($target->table($table)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function truncateTarget(string $connection): void
    {
        $bar = $this->output->createProgressBar(count(self::TABLES));
        foreach (array_reverse(array_keys(self::TABLES)) as $table) {
            DB::connection($connection)->table($table)->delete();
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    private function copyTable(
        ConnectionInterface $source,
        ConnectionInterface $target,
        string $targetName,
        string $table,
        string $orderColumn,
    ): int {
        $count = 0;
        $booleanColumns = $this->booleanColumns($targetName, $table);

        $source->table($table)
            ->orderBy($orderColumn)
            ->chunk(200, function ($rows) use ($target, $table, $booleanColumns, &$count) {
                $payload = $rows->map(function (object $row) use ($booleanColumns) {
                    $values = (array) $row;
                    foreach ($booleanColumns as $column) {
                        if (array_key_exists($column, $values) && $values[$column] !== null) {
                            $values[$column] = (bool) $values[$column];
                        }
                    }

                    return $values;
                })->all();

                if ($payload !== []) {
                    $target->table($table)->insert($payload);
                    $count += count($payload);
                }
            });

        $this->resetSequence($target, $table, $orderColumn);
        $this->line(sprintf('%-34s %8d', $table, $count));

        return $count;
    }

    /**
     * @return list<string>
     */
    private function booleanColumns(string $connection, string $table): array
    {
        return collect(Schema::connection($connection)->getColumns($table))
            ->filter(function (array $column) {
                $type = mb_strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));

                return str_contains($type, 'bool');
            })
            ->pluck('name')
            ->values()
            ->all();
    }

    private function resetSequence(ConnectionInterface $target, string $table, string $orderColumn): void
    {
        if ($orderColumn !== 'id') {
            return;
        }

        $identifier = '"'.str_replace('"', '""', $table).'"';
        $target->statement(
            "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM {$identifier}",
        );
    }
}
