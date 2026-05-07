# Base de Datos — Backup, Restore y Migraciones

## Verificar ultimo backup

```bash
# Ver lista de backups disponibles
php /var/www/html/backend/artisan backup:list

# El output muestra: nombre, disco, tamano, fecha de creacion
```

En S3 (produccion):

```bash
aws s3 ls s3://$BACKUP_S3_BUCKET/crezer/ --recursive | sort | tail -10
```

Un backup saludable tiene menos de 24 horas de antiguedad.

---

## Crear backup manual

```bash
# Solo base de datos (rapido, recomendado antes de deploy)
php /var/www/html/backend/artisan backup:run --only-db

# Base de datos + archivos (certificados)
php /var/www/html/backend/artisan backup:run

# Verificar que el backup se creo
php /var/www/html/backend/artisan backup:list
```

---

## Restaurar desde backup

### Paso 1: Descargar el backup

Desde S3:

```bash
aws s3 cp s3://$BACKUP_S3_BUCKET/crezer/2026-01-15-02-00-00.zip /tmp/restore-backup.zip
```

Desde disco local:

```bash
cp /var/www/html/backend/storage/app/crezer/2026-01-15-02-00-00.zip /tmp/restore-backup.zip
```

### Paso 2: Extraer el dump SQL

```bash
unzip /tmp/restore-backup.zip -d /tmp/restore-backup/

# El dump de la DB esta en la subcarpeta db-dumps/
ls /tmp/restore-backup/db-dumps/
# Ejemplo: mariadb-db.sql
```

Si el backup esta encriptado (BACKUP_ARCHIVE_PASSWORD configurado):

```bash
# Usar 7zip para desencriptar
7z x /tmp/restore-backup.zip -p"$BACKUP_ARCHIVE_PASSWORD" -o/tmp/restore-backup/
```

### Paso 3: Poner la app en modo mantenimiento

```bash
php /var/www/html/backend/artisan down
```

### Paso 4: Restaurar la base de datos

```bash
# ADVERTENCIA: Esto sobreescribe TODA la base de datos actual

mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < /tmp/restore-backup/db-dumps/mariadb-db.sql
```

### Paso 5: Limpiar cache y levantar

```bash
php /var/www/html/backend/artisan config:clear
php /var/www/html/backend/artisan cache:clear
php /var/www/html/backend/artisan up
```

### Paso 6: Verificar

```bash
curl https://app.crezer.app/health/ready
# Esperado: {"db":"ok","cache":"ok","queue":"ok"}
```

---

## Rollback de migraciones

### Ver historial de migraciones

```bash
php /var/www/html/backend/artisan migrate:status
```

### Revertir la ultima migracion (batch mas reciente)

```bash
php /var/www/html/backend/artisan migrate:rollback
```

### Revertir N migraciones

```bash
php /var/www/html/backend/artisan migrate:rollback --step=3
```

### Revertir TODAS las migraciones (PELIGROSO — perdida de datos)

```bash
# USAR SOLO EN ENTORNO DE DESARROLLO
php /var/www/html/backend/artisan migrate:reset
```

**En produccion**, si necesitas revertir multiples migraciones:
1. Identifica el commit del estado anterior: `git log --oneline -20`
2. Restaura desde backup (ver seccion Restaurar arriba)
3. Despliega el codigo del commit anterior
4. Ejecuta las migraciones del commit anterior

---

## Ejecutar seeders

Los seeders son solo para entornos de desarrollo y staging. **Nunca en produccion** a menos que sea un seeder especifico de datos criticos.

```bash
# Seeder de demo data (SOLO desarrollo/staging)
php /var/www/html/backend/artisan db:seed --class=DemoDataSeeder

# Importar servicios de un CSV de precios (produccion permitida)
php /var/www/html/backend/artisan services:seed --business-id=N
```

---

## Limpieza de tablas de sistema

```bash
# Limpiar jobs fallidos (con cuidado — revisar primero)
php /var/www/html/backend/artisan queue:flush

# Limpiar tokens expirados de Sanctum
php /var/www/html/backend/artisan sanctum:prune-expired --hours=24

# Limpiar sessions expiradas
php /var/www/html/backend/artisan session:gc
```
