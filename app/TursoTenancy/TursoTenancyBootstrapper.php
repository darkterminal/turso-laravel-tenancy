<?php

declare(strict_types=1);

namespace App\TursoTenancy;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

class TursoTenancyBootstrapper implements TenancyBootstrapper
{
    protected string $db_path;

    protected string|bool $connection_mode;

    protected string $databaseName;

    protected string $organizationName;

    protected bool $isSchema;

    public function __construct()
    {
        $config = config('database.connections.libsql');
        $config['url'] = strpos($config['url'], ':memory:') !== false ? str_replace('file:', '', $config['url']) : $config['url'];
        $this->setConnectionMode($config['url'], $config['syncUrl'], $config['authToken'], $config['remoteOnly']);

        $this->isSchema = env('TURSO_MULTIDB_SCHEMA', false);

        $url = str_replace('file:', '', $config['url']);
        $this->db_path = $this->checkPathOrFilename($url) === 'filename' ? database_path() : dirname($url);

        if ($this->isSchema === true && $this->connection_mode === 'local') {
            throw new \Exception('You\'re using Multi-DB Schema and it\'s only support with Remote Connection');
        }

        if ($this->connection_mode === 'remote_replica') {
            throw new \Exception('Embedded Replica Connection is not supported');
        }

        if ($this->connection_mode === 'memory') {
            throw new \Exception('In Memory Connection is not supported for Tenancy');
        }

        if ($this->connection_mode === 'remote') {
            $this->databaseName = env('TURSO_DB_PRIMARY_NAME');
            $this->organizationName = env('TURSO_DB_PRIMARY_ORG');
        }
    }

    public function bootstrap(Tenant $tenant)
    {
        $tenantId = $tenant->getTenantKey();

        if ($this->connection_mode === 'remote') {
            $tenantDatabasePath = $this->getTenantDatabasePath($tenantId, true);
            $dbData = $this->getRemoteDatabaseConfig($tenantDatabasePath);
            config([
                'database.connections.libsql.authToken' => $dbData['token'],
                'database.connections.libsql.syncUrl' => $dbData['url'],
                'database.connections.libsql.remoteOnly' => true,
            ]);
        } else {
            $tenantDatabasePath = $this->getTenantDatabasePath($tenantId);
            if ($this->isRunningMigrations()) {
                config([
                    'database.connections.libsql.url' => "file:$tenantDatabasePath",
                ]);
            } else {
                config([
                    'database.connections.libsql.database' => $tenantDatabasePath,
                ]);
            }
        }

        DB::purge('libsql');
        DB::reconnect('libsql');

        DB::setDefaultConnection('libsql');
    }

    public function revert()
    {
        if ($this->connection_mode === 'remote') {
            config([
                'database.connections.libsql.authToken' => env('DB_AUTH_TOKEN'),
                'database.connections.libsql.syncUrl' => env('DB_SYNC_URL'),
                'database.connections.libsql.remoteOnly' => true,
            ]);
        } else {
            config([
                'database.connections.libsql.database' => config('database.connections.central.database'),
            ]);
        }

        DB::purge('libsql');
        DB::reconnect('libsql');

        DB::setDefaultConnection(config('database.default'));
    }

    private function checkPathOrFilename(string $string): string
    {
        if (strpos($string, DIRECTORY_SEPARATOR) !== false || strpos($string, '/') !== false || strpos($string, '\\') !== false) {
            return 'path';
        } else {
            return 'filename';
        }
    }

    private function isRunningMigrations()
    {
        $commands = [
            'tenants:migrate',
            'tenants:migrate-fresh',
        ];

        return App::runningInConsole() && in_array($_SERVER['argv'][1], $commands);
    }

    private function setConnectionMode(string $path, string $url = '', string $token = '', bool $remoteOnly = false): void
    {
        if ((str_starts_with($path, 'file:') !== false || $path !== 'file:') && ! empty($url) && ! empty($token) && $remoteOnly === false) {
            $this->connection_mode = 'remote_replica';
        } elseif (strpos($path, 'file:') !== false && ! empty($url) && ! empty($token) && $remoteOnly === true) {
            $this->connection_mode = 'remote';
        } elseif (strpos($path, 'file:') !== false) {
            $this->connection_mode = 'local';
        } elseif ($path === ':memory:') {
            $this->connection_mode = 'memory';
        } else {
            $this->connection_mode = false;
        }
    }

    private function readTenantKeyValue(string $filePath)
    {
        if (! file_exists($filePath)) {
            return false;
        }

        $fileHandle = fopen($filePath, 'rb');
        if ($fileHandle === false) {
            return false;
        }

        $lengthData = fread($fileHandle, 4);
        $secretLength = unpack('L', $lengthData)[1];

        $secret = fread($fileHandle, $secretLength);

        fclose($fileHandle);

        return $secret;
    }

    private function getTenantDatabasePath($tenantId, $remote = false)
    {
        $db_prefix = config('tenancy.database.prefix');
        $db_suffix = config('tenancy.database.suffix');
        $db = "{$db_prefix}{$tenantId}{$db_suffix}";

        if ($remote) {
            $dbName = $this->slugify(pathinfo(str_replace($db_suffix, '', $db), PATHINFO_FILENAME));

            return "{$this->db_path}".DIRECTORY_SEPARATOR."{$dbName}.bin";
        } else {
            return $this->db_path.DIRECTORY_SEPARATOR.$db;
        }
    }

    private function getRemoteDatabaseConfig($configFile)
    {
        $dbBin = $this->readTenantKeyValue($configFile);
        $dbData = json_decode($dbBin, true);

        return $dbData;
    }

    private function slugify($string)
    {
        // Convert to lowercase
        $string = strtolower($string);

        // Replace non-letter or digits with hyphens
        $string = preg_replace('/[^a-z0-9]+/i', '-', $string);

        // Trim hyphens from the beginning and end
        $string = trim($string, '-');

        // Remove duplicate hyphens
        $string = preg_replace('/-+/', '-', $string);

        return $string;
    }
}
