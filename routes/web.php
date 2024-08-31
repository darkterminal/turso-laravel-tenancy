<?php

use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', function () {
            dump(\App\Models\User::all());
        });

        Route::get('/create-new-tenant/{subdomain}', function (string $subdomain) {
            $domain = "$subdomain.localhost";
            $tenant = App\Models\Tenant::create(['id' => $subdomain]);
            $tenant->domains()->create(['domain' => $domain]);
            echo "$domain created!";
        });
    });
}
