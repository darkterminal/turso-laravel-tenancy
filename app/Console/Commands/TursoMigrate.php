<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Commands;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Console\Migrations\MigrateCommand;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Stancl\Tenancy\Concerns\DealsWithMigrations;
use Stancl\Tenancy\Concerns\ExtendsLaravelCommand;
use Stancl\Tenancy\Concerns\HasATenantsOption;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\MigratingDatabase;

final class Migrate extends MigrateCommand
{
    use DealsWithMigrations, ExtendsLaravelCommand, HasATenantsOption;

    protected $description = 'Run migrations for tenant(s)';

    protected static function getTenantCommandName(): string
    {
        return 'tenants:migrate';
    }

    public function __construct(Migrator $migrator, Dispatcher $dispatcher)
    {
        parent::__construct($migrator, $dispatcher);

        $this->specifyParameters();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        foreach (config('tenancy.migration_parameters') as $parameter => $value) {
            if (! $this->input->hasParameterOption($parameter)) {
                $this->input->setOption(ltrim($parameter, '-'), $value);
            }
        }

        if (! $this->confirmToProceed()) {
            return;
        }

        $tenants = $this->option('tenants') ?: DB::table('tenants')->pluck('id');

        tenancy()->runForMultiple($tenants, function ($tenant) {
            $this->line("Tenant: {$tenant->getTenantKey()}");

            event(new MigratingDatabase($tenant));

            // Migrate
            parent::handle();

            event(new DatabaseMigrated($tenant));
        });
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
            'php.exe',
        ] : [
            '/usr/bin/php',
            '/usr/local/bin/php',
            'php',
        ];

        foreach ($possiblePaths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        throw new RuntimeException('PHP executable not found.');
    }
}
