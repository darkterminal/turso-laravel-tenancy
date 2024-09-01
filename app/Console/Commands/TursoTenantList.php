<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TenantList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List tenants.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Listing all tenants.');
        $tenants = DB::table('tenants')->join('domains', 'domains.tenant_id', '=', 'tenants.id')->get()->toArray();
        $tenantLists = array_map(fn ($tenant) => [
            'id' => $tenant['tenant_id'],
            'database' => "tenant-{$tenant['tenant_id']}",
            'domain' => $tenant['domain'],
        ], $tenants);

        $this->table(['Tenant ID', 'Database', 'Domain'], $tenantLists);
    }
}
