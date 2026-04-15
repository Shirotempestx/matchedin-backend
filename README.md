
## MatchendIN - Database setup (new device)

1. Install prerequisites:
	- PHP 8.4+
	- Composer
	- PostgreSQL (server running on port 5432)

2. Enable PostgreSQL driver in PHP:
	- Open your `php.ini`
	- Ensure this line is enabled: `extension=pdo_pgsql`
	- Restart terminal after saving

3. In `BackEnd`, install dependencies:
	- `composer install`

4. Create `.env` from `.env.example` and set PostgreSQL values:
	- `DB_CONNECTION=pgsql`
	- `DB_HOST=127.0.0.1`
	- `DB_PORT=5432`
	- `DB_DATABASE=MatchedIn`
	- `DB_USERNAME=postgres`
	- `DB_PASSWORD=your_password`

5. Clear config cache and run migrations:
	- `php artisan config:clear`
	- `php artisan migrate --force`

6. Optional (direct SQL instead of Laravel migration):
	- `psql -U postgres -d MatchedIn -f database\matchendin_schema.sql`

## MatchendIN - Incremental DB update (2 new tables) 31/03/2026

If teammates already created the previous tables, they only need this migration:
- `database/migrations/2026_04_01_120000_create_suivis_and_etudiants_sauvegardes_tables.php`

From `BackEnd`, run:

```powershell
php artisan config:clear
php artisan migrate --path=database/migrations/2026_04_01_120000_create_suivis_and_etudiants_sauvegardes_tables.php --force
```

If they prefer running all pending migrations instead:

```powershell
php artisan migrate --force
```

