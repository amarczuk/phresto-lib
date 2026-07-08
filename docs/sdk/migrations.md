# Database Migrations

Phresto supports ordered PHP migration scripts for changes that cannot be handled by the automatic model generator.

## Migration file

Create a file in `migration/` with a class named `Migration_<file_name>` implementing `Phresto\Interf\Migration`:

```php
<?php
class Migration_add_discounts implements Phresto\Interf\Migration {

    public function getTime() {
        return 1700000000;
    }

    public function getName() {
        return 'add_discounts';
    }

    public function run( $db ) {
        $db->query( "
            CREATE TABLE discount (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL,
                amount DECIMAL(10,2) NOT NULL
            ) ENGINE=InnoDB;
        " );
    }

    public function rollback( $db ) {
        $db->query( "DROP TABLE discount" );
    }
}
```

## Running migrations

```bash
php scripts/run_migrations.php
```

The runner:

1. Creates a `migrations` tracking table.
2. Scans `migration/*.php`.
3. Sorts unexecuted migrations by `getTime()`.
4. Runs each `run($db)` method.
5. Records the migration name in the `migrations` table.

## Re-running a migration

Delete the matching row from the `migrations` table, then run the runner again.

## Automatic schema sync

For day-to-day schema changes driven by models, use the model generator instead:

```bash
php scripts/create_models.php
```

Use migrations for data transformations, reference data seeding, or changes that the generator cannot safely perform.
