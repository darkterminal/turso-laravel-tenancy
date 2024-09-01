# Tenancy for Laravel with Turso/libSQL Database

## Requirement

-   [Turso Client PHP](https://github.com/tursodatabase/turso-client-php) or install using [Turso PHP Installer](https://github.com/darkterminal/turso-php-installer)

## Use Case

-   libSQL Multi Tenant Database - Local File Connection ✅
-   libSQL Multi Tenant Database - Remote Connection ✅
-   libSQL Multi Tenant Database - Multi-DB Schemas (Only work with Remote Connection) ✅

## Tech Stacks

-   [Laravel Framework](https://laravel.com)
-   [Tenancy for Laravel — stancl/tenancy](https://tenancyforlaravel.com)
-   [Turso Platform API PHP](https://github.com/darkterminal/turso-api-client-php)

## Supported Tenant Commands

| Commands              | Local | Remote | Multi-DB Schema |
| --------------------- | ----- | ------ | --------------- |
| tenants:list          | ✅    | ✅     | ✅              |
| tenants:migrate       | ✅    | ✅     | ❌              |
| tenants:migrate-fresh | ✅    | ✅     | ❌              |
| tenants:rollback      | ✅    | ✅     | ❌              |
| tenants:run           | ✅    | ✅     | ❌              |
| tenants:seed          | ✅    | ✅     | ❌              |

## Local Connection Usage

```env
DB_CONNECTION=libsql
DB_DATABASE=database.sqlite
```

## Remote Connection Usage

1. Create new database

```sh
turso db create <your-db-name>
```

2. Get database URL and add to `DB_SYNC_URL`

```sh
turso db show <your-db-name>

Name:           <your-db-name>
URL:            libsql://<your-db-name>-<your-organization-name>.turso.io
ID:             *******-****-****-****-************
Group:          <your-group-name>
Version:        *.**.**
Locations:      ***
Size:           *** kB
Archived:       No
Bytes Synced:   0 B
Is Schema:      No

Database Instances:
NAME     TYPE        LOCATION
***      primary     ***
```

3. Create database auth token and add to `DB_AUTH_TOKEN`

```sh
turso db tokens create <your-db-name>
```

4. Create Platform API Token and add to `TURSO_API_TOKEN`

```sh
turso auth api-tokens mint <your-app-name> # named whatever you want
```

Setting up your Environment Variables

```env
DB_CONNECTION=libsql
DB_AUTH_TOKEN=<your-database-auth-token>
DB_SYNC_URL=<your-database-url>
DB_REMOTE_ONLY=true

TURSO_API_TOKEN=<your-platform-api-token>
TURSO_DB_PRIMARY_NAME=<your-db-name>
TURSO_DB_PRIMARY_ORG=<your-organization-name>
TURSO_DB_DEFAULT_GROUP=<your-group-name>
```

## Multi-DB Schema in Remote Connection Usage

1. Create new database

```sh
turso db create <your-db-name> --type schema
```

2. Get database URL and add to `DB_SYNC_URL`

```sh
turso db show <your-db-name>

Name:           <your-db-name>
URL:            libsql://<your-db-name>-<your-organization-name>.turso.io
ID:             *******-****-****-****-************
Group:          <your-group-name>
Version:        *.**.**
Locations:      ***
Size:           *** kB
Archived:       No
Bytes Synced:   0 B
Is Schema:      Yes

Database Instances:
NAME     TYPE        LOCATION
***      primary     ***
```

3. Create group auth token and add to `DB_AUTH_TOKEN`

```sh
turso db group tokens create <your-group-name>
```

4. Create Platform API Token and add to `TURSO_API_TOKEN`

```sh
turso auth api-tokens mint <your-app-name> # named whatever you want
```

Setting up your Environment Variables

```env
DB_CONNECTION=libsql
DB_AUTH_TOKEN=<your-group-auth-token>
DB_SYNC_URL=<your-database-url>
DB_REMOTE_ONLY=true

TURSO_API_TOKEN=<your-platform-api-token>
TURSO_DB_PRIMARY_NAME=<your-db-name>
TURSO_DB_PRIMARY_ORG=<your-organization-name>
TURSO_DB_DEFAULT_GROUP=<your-group-name>
TURSO_MULTIDB_SCHEMA=true
```

## Support Me is Not Illegal

-   [GitHub Sponsors](https://github.com/sponsors/darkterminal)
-   [Saweria](https://saweria.co/darkterminal)
-   [PayPal](https://paypal.me/lazarusalhambra)
