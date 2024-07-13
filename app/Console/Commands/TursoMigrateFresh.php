<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Stancl\Tenancy\Concerns\DealsWithMigrations;
use Stancl\Tenancy\Concerns\HasATenantsOption;
use Symfony\Component\Console\Input\InputOption;

final class MigrateFresh extends Command
{
    use HasATenantsOption, DealsWithMigrations;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop all tables and re-run all migrations for tenant(s)';

    public function __construct()
    {
        parent::__construct();

        $this->addOption('--drop-views', null, InputOption::VALUE_NONE, 'Drop views along with tenant tables.', null);
        $this->addOption('--step', null, InputOption::VALUE_NONE, 'Force the migrations to be run so they can be rolled back individually.');

        $this->setName('tenants:migrate-fresh');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        tenancy()->runForMultiple($this->option('tenants'), function ($tenant) {
            $tenantId = $tenant->getTenantKey();
            $this->info("Tenant: {$tenantId}");
            $this->line('Dropping tables.');
            $this->call('db:wipe');

            $this->line('Migrating.');
            try {
                $phpExecutable = $this->getPhpExecutable();
                $command = escapeshellcmd($phpExecutable . ' artisan tenants:migrate --database=libsql --path=' . escapeshellarg(database_path('migrations/tenant')));
                $output = shell_exec($command);
                echo $output . PHP_EOL;
            } catch (RuntimeException $e) {
                echo 'Error: ' . $e->getMessage();
            }
        });

        $this->info('Done.');
    }

    protected function getPhpExecutable()
    {
        if (php_sapi_name() == 'cli') {
            return PHP_BINARY;
        }

        $possiblePaths = [];

        $os = strtoupper(PHP_OS);

        $possiblePaths = strpos($os, 'WIN') === 0 ? [
            'C:\\Program Files\\PHP\\php.exe',
            'C:\\Program Files (x86)\\PHP\\php.exe',
            'php.exe'
        ] : [
            '/usr/bin/php',
            '/usr/local/bin/php',
            'php'
        ];

        foreach ($possiblePaths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        throw new RuntimeException('PHP executable not found.');
    }
}
