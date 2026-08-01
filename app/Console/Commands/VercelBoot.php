<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        $defaultConnection = DB::connection();
        $usesPostgres = $defaultConnection->getDriverName() === 'pgsql';
        $migrationConnectionName = $usesPostgres ? 'pgsql_migration' : (string) config('database.default');
        $migrationConnection = DB::connection($migrationConnectionName);

        try {
            if ($usesPostgres) {
                // Le verrou doit utiliser la connexion directe Neon : un verrou de session
                // n'est pas fiable derrière PgBouncer en mode transaction.
                $migrationConnection->statement('SELECT pg_advisory_lock('.self::MIGRATION_LOCK.')');
            }

            $migrationOptions = ['--force' => true];
            if ($usesPostgres) {
                $migrationOptions['--database'] = $migrationConnectionName;
            }
            $exitCode = Artisan::call('migrate', $migrationOptions);
            $this->output->write(Artisan::output());
            if ($exitCode !== self::SUCCESS) {
                return self::FAILURE;
            }

            $catalogExitCode = Artisan::call('achathub:bootstrap-catalog');
            $this->output->write(Artisan::output());
            if ($catalogExitCode !== self::SUCCESS) {
                return self::FAILURE;
            }

            $this->ensureAdministrator();

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error('Migration impossible : '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($usesPostgres) {
                try {
                    $migrationConnection->statement('SELECT pg_advisory_unlock('.self::MIGRATION_LOCK.')');
                } catch (Throwable) {
                    // La connexion peut déjà être fermée après une erreur réseau.
                }
            }
        }
    }

    private function ensureAdministrator(): void
    {
        $email = mb_strtolower(trim((string) env('ACHATHUB_ADMIN_EMAIL')));
        $password = (string) env('ACHATHUB_ADMIN_PASSWORD');

        if ($email === '' || $password === '') {
            $this->components->warn('Compte administrateur non initialisé : variables ACHATHUB_ADMIN_* absentes.');

            return;
        }

        $administrator = User::query()->firstOrNew(['email' => $email]);
        if (! $administrator->exists) {
            $administrator->name = 'Administration AchatHub';
            $administrator->password = Hash::make($password);
            $administrator->provider = 'email';
            $administrator->email_verified_at = now();
        }
        $administrator->role = 'admin';
        $administrator->blocked = false;
        $administrator->save();

        $this->components->info('Compte administrateur AchatHub prêt.');
    }
}
