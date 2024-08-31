# Tenancy for Laravel with Turso/libSQL Database

## Requirement

-   [Turso Client PHP](https://github.com/tursodatabase/turso-client-php) or install using [Turso PHP Installer](https://github.com/darkterminal/turso-php-installer)

## Use Case

-   libSQL Multi Tenant Database - Local File Connection ✅
-   libSQL Multi Tenant Database - Remote Connection ✅
-   libSQL Multi Tenant Database - Multi-DB Schemas ⏳️ (WIP)

## Local Connection Usage

```env
DB_CONNECTION=libsql
DB_DATABASE=database.sqlite
```

## Remote Connection Usage

```env
DB_CONNECTION=libsql
DB_AUTH_TOKEN=REPLACE_THIS_WITH_YOUR_DATABASE_AUTH_TOKEN_OR_GROUP_AUTH_TOKEN
DB_SYNC_URL=REPLACE_THIS_WITH_YOUR_DATABASE_URL_IN_THIS_CASE_IS_YOUR_PRIMARY_DATABASE
DB_REMOTE_ONLY=true

TURSO_API_TOKEN=REPLACE_THIS_WITH_YOUR_TURSO_PLATFORM_API_KEY_TOKEN
TURSO_DB_PRIMARY_NAME=REPLACE_THIS_WITH_YOUR_PRIMARY_DATABASE_NAME
TURSO_DB_PRIMARY_ORG=REPLACE_THIS_WITH_YOUR_ORGANIZATION_NAME
TURSO_DB_DEFAULT_GROUP=REPLACE_THIS_WITH_YOUR_DEFAULT_GROUP_NAME
```
