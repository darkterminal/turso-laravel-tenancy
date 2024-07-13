<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', function () {
        echo "This is your multi-tenant application. The id of the current tenant is: <br/>";
        echo "TENANT ID: <strong>". tenant('id') ."</strong><br/>";
        echo "TENANT DOMAIN: <strong>". tenant('id') .".localhost</strong><br/>";
        echo "TENANT Connection:";
        dump(config('database.connections.libsql'));
        $user = new \App\Models\User();
        dump($user->all());
    });
});
