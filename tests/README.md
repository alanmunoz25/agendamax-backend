# Testing Guide

## Suites disponibles

### SQLite (rápido, para desarrollo local)

```bash
ddev exec --dir /var/www/html/backend php artisan test
```

- Driver: SQLite `:memory:`
- Cache: `array` (por proceso)
- Velocidad: rápido (~segundos)
- Cubre: lógica de negocio, policies, rate limiting, validaciones
- No cubre: CHECK constraints de MariaDB, locks atómicos cross-process

### MariaDB (paridad con producción, para pre-merge)

```bash
# Crear la base de datos de test (solo primera vez):
ddev exec mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS crezer_test;"

# Correr la suite:
ddev exec --dir /var/www/html/backend bash -c "
  DB_CONNECTION=mariadb DB_HOST=db DB_DATABASE=crezer_test \
  DB_USERNAME=root DB_PASSWORD=root \
  ./vendor/bin/phpunit --configuration=phpunit.mariadb.xml
"
```

- Driver: MariaDB 11 (igual que producción)
- Cache: `database` (`cache_locks` table — atómica)
- Cubre adicionalmente: CHECK constraint `chk_commission_amount_nonneg` (TD-017)
- Tests de concurrencia con fork: pendiente (TD-018)

## Cuándo correr cuál

| Momento | Suite |
|---------|-------|
| Commit local / desarrollo rápido | SQLite |
| Pre-merge / pull request | MariaDB (CI automático) |
| Debugging de constraint o lock | MariaDB local |

## CI

El workflow `.github/workflows/tests.yml` corre ambas suites en paralelo:

- `tests-sqlite`: SQLite `:memory:`, cobertura con xdebug
- `tests-mariadb`: MariaDB 11, verifica CHECK constraint (TD-017), sin xdebug

## Tests skipped y por qué

| Test | Razón del skip |
|------|---------------|
| `test_concurrent_generate_only_one_succeeds_with_real_processes` | TD-018: requiere pcntl_fork/Symfony\Process. Skip condicional: solo en SQLite o cache array. |
| `test_create_period_concurrent_does_not_produce_overlap` | TD-018: mismo motivo. |
