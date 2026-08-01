<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('achathub:vercel-boot')]
#[Description('Prépare la base AchatHub au démarrage du conteneur Vercel')]
class VercelBoot extends Command
{
    private const MIGRATION_LOCK = 118006512;

    public function handle(): int
    {
        if (! filter_var(env('VERCEL_RUN_MIGRATIONS', false), FILTER_VALIDATE_BOOL)) {
            $this->components->info('Migrations automatiques désactivées.');

            return self::SUCCESS;
        }

        $connection = DB::connection();
        $usesPostgres = $connection->getDriverName() === 'pgsql';

        try {
            if ($usesPostgres) {
                $connection->statement('SELECT pg_advisory_lock('.self::MIGRATION_LOCK.')');
            }

            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $this->output->write(Artisan::output());

            return $exitCode === self::SUCCESS ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error('Migration impossible : '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($usesPostgres) {
                try {
                    $connection->statement('SELECT pg_advisory_unlock('.self::MIGRATION_LOCK.')');
                } catch (Throwable) {
                    // La connexion peut déjà être fermée après une erreur réseau.
                }
            }
        }
    }
}
