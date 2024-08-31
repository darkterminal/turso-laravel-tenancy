<?php

declare(strict_types=1);

namespace App\TursoTenancy;

use Darkterminal\TursoPlatformAPI\Client;
use Stancl\Tenancy\Contracts\TenantDatabaseManager;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class TursoDatabaseManager implements TenantDatabaseManager
{
    protected string $db_path;
    protected string|bool $connection_mode;
    protected string $databaseName;
    protected string $organizationName;
    protected Client $turso;

    public function __construct()
    {
        $config = config('database.connections.libsql');
        $config['url'] = strpos($config['url'], ':memory:') !== false ? str_replace('file:', '', $config['url']) : $config['url'];

        $this->setConnectionMode($config['url'], $config['syncUrl'], $config['authToken'], $config['remoteOnly']);

        $url = str_replace('file:', '', $config['url']);
        $this->db_path = $this->checkPathOrFilename($url) === 'filename' ? database_path() : dirname($url);

        if ($this->connection_mode === 'remote') {
            $this->databaseName = env('TURSO_DB_PRIMARY_NAME');
            $this->organizationName = env('TURSO_DB_PRIMARY_ORG');
        }

        $this->turso = new Client($this->organizationName, env('TURSO_API_TOKEN'));

        if ($this->connection_mode === 'remote_replica') {
            throw new \Exception("Embedded Replica Connection is not supported");
        }

        if ($this->connection_mode === 'memory') {
            throw new \Exception("In Memory Connection is not supported for Tenancy");
        }
    }

    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        if ($this->connection_mode === 'remote') {
            $dbName = $this->slugify(pathinfo($tenant->database()->getName(), PATHINFO_FILENAME));
            return $this->createDb($dbName);
        }
        return file_put_contents($this->databaseLocation($tenant->database()->getName()), '');
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        if ($this->connection_mode === 'remote') {
            $dbName = $this->slugify(pathinfo($tenant->database()->getName(), PATHINFO_FILENAME));
            $deleteDb = $this->turso->databases()->delete($dbName)->get();
            unlink("{$this->db_path}" . DIRECTORY_SEPARATOR . "{$dbName}.bin");
            return $deleteDb['code'] === 200;
        }
        return unlink($this->databaseLocation($tenant->database()->getName()));
    }

    public function databaseExists(string $name): bool
    {
        if ($this->connection_mode === 'remote') {
            $dbName = $this->slugify(pathinfo($name, PATHINFO_FILENAME));
            $detail = $this->turso->databases()->getDatabase($dbName)->get();
            return $detail['code'] === 200;
        }
        return file_exists($this->databaseLocation($name));
    }

    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        if ($this->connection_mode === 'remote') {
            $dbName = $this->slugify(pathinfo($databaseName, PATHINFO_FILENAME));
            $dbBin = $this->readTenantKeyValue("{$this->db_path}" . DIRECTORY_SEPARATOR . "{$dbName}.bin");
            $dbData = json_decode($dbBin, true);
            $baseConfig['authToken'] = $dbData['token'];
            $baseConfig['syncUrl'] = $dbData['url'];
        } else {
            $url = str_replace('file:', '', $baseConfig['url']);
            $database = $this->checkPathOrFilename($url) === 'filename' ? database_path($databaseName) : $url;
            $baseConfig['url'] = "file:$database";
        }

        return $baseConfig;
    }

    public function setConnection(string $connection): void
    {
        // 
    }

    private function createDb(string $dbName)
    {
        $createDb = $this->turso->databases()->create(databaseName: $dbName, group: env('TURSO_DB_DEFAULT_GROUP', 'default'))->get();
        if ($createDb['code']) {
            $createDbToken = $this->turso->databases()->createToken($dbName)->get();
            if ($createDbToken['code']) {
                $createKey = $this->createTenantKey("{$this->db_path}" . DIRECTORY_SEPARATOR . "{$dbName}.bin", json_encode([
                    'token' => $createDbToken['data'],
                    'url' => "libsql://{$createDb['data']['Hostname']}"
                ]));
                return $createKey;
            }

            return false;
        }
        return false;
    }

    private function checkPathOrFilename(string $string): string
    {
        if (strpos($string, DIRECTORY_SEPARATOR) !== false || strpos($string, '/') !== false || strpos($string, '\\') !== false) {
            return 'path';
        } else {
            return 'filename';
        }
    }

    private function databaseLocation(string $db_name): string
    {
        return $this->db_path . DIRECTORY_SEPARATOR . $db_name;
    }

    private function setConnectionMode(string $path, string $url = '', string $token = '', bool $remoteOnly = false): void
    {
        if ((str_starts_with($path, 'file:') !== false || $path !== 'file:') && !empty($url) && !empty($token) && $remoteOnly === false) {
            $this->connection_mode = 'remote_replica';
        } elseif (strpos($path, 'file:') !== false && !empty($url) && !empty($token) && $remoteOnly === true) {
            $this->connection_mode = 'remote';
        } elseif (strpos($path, 'file:') !== false) {
            $this->connection_mode = 'local';
        } elseif ($path === ':memory:') {
            $this->connection_mode = 'memory';
        } else {
            $this->connection_mode = false;
        }
    }

    private function createTenantKey(string $filePath, string $key): bool
    {
        $fileHandle = fopen($filePath, 'wb');
        if ($fileHandle === false) {
            return false;
        }

        $secretLength = strlen($key);
        fwrite($fileHandle, pack('L', $secretLength));

        fwrite($fileHandle, $key);

        fclose($fileHandle);
        return true;
    }

    private function readTenantKeyValue(string $filePath)
    {
        if (!file_exists($filePath)) {
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
