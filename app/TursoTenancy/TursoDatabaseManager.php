<?php

declare(strict_types=1);

namespace App\TursoTenancy;

use Stancl\Tenancy\Contracts\TenantDatabaseManager;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class TursoDatabaseManager implements TenantDatabaseManager
{
    protected string $db_path;
    protected string|bool $connection_mode;
    protected string $databaseName;
    protected string $organizationName;

    public function __construct()
    {
        $config = config('database.connections.libsql');
        $config['url'] = strpos($config['url'], ':memory:') !== false ? str_replace('file:', '', $config['url']) : $config['url'];
        $this->setConnectionMode($config['url'], $config['syncUrl'], $config['authToken'], $config['remoteOnly']);

        $url = str_replace('file:', '', $config['url']);
        $this->db_path = $this->checkPathOrFilename($url) === 'filename' ? database_path() : dirname($url);

        if ($this->connection_mode === 'remote_replica') {
            throw new \Exception("Embedded Replica Connection is not supported");
        }

        if ($this->connection_mode === 'memory') {
            throw new \Exception("In Memory Connection is not supported for Tenancy");
        }

        if ($this->connection_mode === 'remote') {
            $this->databaseName = env('TURSO_DB_PRIMARY_NAME');
            $this->organizationName = env('TURSO_DB_PRIMARY_ORG');
        }
    }

    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        try {
            if ($this->connection_mode === 'remote') {
                $dbName = $this->slugify(pathinfo($tenant->database()->getName(), PATHINFO_FILENAME));
                $createDb = $this->tursoCreateDatabase($this->organizationName, $dbName, env('TURSO_API_TOKEN'));
                if ($createDb['success']) {
                    $createDbToken = $this->tursoCreateDatabaseToken($this->organizationName, $dbName, env('TURSO_API_TOKEN'));
                    if ($createDbToken['success']) {
                        $createKey = $this->createTenantKey("{$this->db_path}" . DIRECTORY_SEPARATOR . "{$dbName}.bin", json_encode([
                            'token' => $createDbToken['token'],
                            'url' => "libsql://{$createDb['data']['Hostname']}"
                        ]));
                        return $createKey;
                    }

                    return false;
                }
                return false;
            }
            return file_put_contents($this->databaseLocation($tenant->database()->getName()), '');
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        try {
            if ($this->connection_mode === 'remote') {
                $dbName = $this->slugify(pathinfo($tenant->database()->getName(), PATHINFO_FILENAME));
                $deleteDb = $this->tursoDeleteDatabase($this->organizationName, $dbName, env('TURSO_API_TOKEN'));
                unlink("{$this->db_path}" . DIRECTORY_SEPARATOR . "{$dbName}.bin");
                return $deleteDb['status'];
            }
            return unlink($this->databaseLocation($tenant->database()->getName()));
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function databaseExists(string $name): bool
    {
        if ($this->connection_mode === 'remote') {
            $dbName = pathinfo($name, PATHINFO_FILENAME);
            $detail = $this->tursoDatabaseInfo($this->organizationName, $dbName, env('TURSO_API_TOKEN'));
            return $detail['success'];
        } else {
            return file_exists($this->databaseLocation($name));
        }
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

    private function tursoDatabaseInfo($organizationName, $databaseName, $token)
    {
        // Initialize a cURL session
        $ch = curl_init();

        // Set the URL, including the organization and database names
        $url = "https://api.turso.tech/v1/organizations/{$organizationName}/databases/{$databaseName}";

        // Set the necessary cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}"
        ]);

        // Execute the cURL request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            return [
                'success' => false,
                'error' => $error_msg
            ];
        }

        // Get the HTTP status code of the response
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Decode the JSON response
        $data = json_decode($response, true);

        // Check for a successful response
        if ($http_code === 200) {
            return [
                'success' => true,
                'data' => $data['database']
            ];
        }

        // Handle error response
        return [
            'success' => false,
            'error' => $data['error'] ?? 'Unknown error occurred'
        ];
    }

    private function tursoCreateDatabase($organizationName, $databaseName, $token, $group = 'default')
    {
        // Initialize a cURL session
        $ch = curl_init();

        // Set the URL, including the organization name
        $url = "https://api.turso.tech/v1/organizations/{$organizationName}/databases";

        // Prepare the POST data
        $postData = json_encode([
            'name' => $databaseName,
            'group' => $group
        ]);

        // Set the necessary cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        // Execute the cURL request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            return [
                'success' => false,
                'error' => $error_msg
            ];
        }

        // Get the HTTP status code of the response
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Decode the JSON response
        $data = json_decode($response, true);

        // Check for a successful response
        if ($http_code === 200) {
            return [
                'success' => true,
                'data' => $data['database']
            ];
        }

        // Handle error responses
        return [
            'success' => false,
            'error' => $data['error'] ?? 'Unknown error occurred',
            'http_code' => $http_code
        ];
    }

    private function tursoCreateDatabaseToken($organizationName, $databaseName, $token, $expiration = 'never', $authorization = 'full-access')
    {
        // Initialize a cURL session
        $ch = curl_init();

        // Set the URL, including the organization and database names, and query parameters
        $url = "https://api.turso.tech/v1/organizations/{$organizationName}/databases/{$databaseName}/auth/tokens?expiration={$expiration}&authorization={$authorization}";

        // Set the necessary cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);

        // Execute the cURL request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            return [
                'success' => false,
                'error' => $error_msg
            ];
        }

        // Get the HTTP status code of the response
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Decode the JSON response
        $data = json_decode($response, true);

        // Check for a successful response
        if ($http_code === 200) {
            return [
                'success' => true,
                'token' => $data['jwt']
            ];
        }

        // Handle error response
        return [
            'success' => false,
            'error' => $data['error'] ?? 'Unknown error occurred',
            'http_code' => $http_code
        ];
    }

    private function tursoDeleteDatabase($organizationName, $databaseName, $token)
    {
        // Initialize a cURL session
        $ch = curl_init();

        // Set the URL, including the organization and database names
        $url = "https://api.turso.tech/v1/organizations/{$organizationName}/databases/{$databaseName}";

        // Set the necessary cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}"
        ]);

        // Execute the cURL request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            return [
                'success' => false,
                'error' => $error_msg
            ];
        }

        // Get the HTTP status code of the response
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Decode the JSON response
        $data = json_decode($response, true);

        // Check for a successful response
        if ($http_code === 200) {
            return [
                'success' => true,
                'database' => $data['database']
            ];
        }

        // Handle error responses
        return [
            'success' => false,
            'error' => $data['error'] ?? 'Unknown error occurred',
            'http_code' => $http_code
        ];
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
