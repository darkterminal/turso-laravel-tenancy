<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Commands;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Concerns\HasATenantsOption;
use Stancl\Tenancy\Events\DatabaseSeeded;
use Stancl\Tenancy\Events\SeedingDatabase;

class Seed extends SeedCommand
{
    use HasATenantsOption;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed tenant database(s).';

    protected $name = 'tenants:seed';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(ConnectionResolverInterface $resolver)
    {
        parent::__construct($resolver);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (env('TURSO_MULTIDB_SCHEMA', false)) {
            $this->error('This command is not support for Multi-DB Schemas');
            exit;
        }

        foreach (config('tenancy.seeder_parameters') as $parameter => $value) {
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

            event(new SeedingDatabase($tenant));

            // Seed
            parent::handle();

            event(new DatabaseSeeded($tenant));
        });
    }
}
