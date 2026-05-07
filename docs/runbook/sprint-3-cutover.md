# Cutover Sprint 3 — Producción

Plan de deploy específico para el cutover de Sprint 3 (Payroll + Comisiones + POS + FE/DGII + Wave 2 hardening). Reemplaza al runbook genérico `deploy.md` solo para este cutover.

## Contexto

- **32 migraciones nuevas** sobre el schema vivo en producción.
- **No hay data destructiva.** Las 5 migraciones que tocan tablas con data existente (`appointments`, `employees`, `users`, `businesses`) usan columnas `nullable` o `default`.
- **Sin colisiones de schema.** Pre-flight verificó 2FA Fortify, FK constraints, y orden de migraciones.

## Pilot ejecutado (evidencia)

Importamos el dump real de producción (`agenda_max.sql`) a una base aislada `agenda_max_prod` y corrimos `migrate` exitosamente:

| Verificación | Resultado |
|---|---|
| Migraciones aplicadas | 32 / 32 (525ms total) |
| Errores | 0 |
| Data preservada | 4 businesses, 63 users, 17 employees, 25 appointments, 430 services, 7 employee_schedules |
| Defaults aplicados | `businesses.pos_commissions_enabled=TRUE` en los 4 negocios automáticamente |
| FK nuevas | `appointments.ticket_id → pos_tickets.id` (nullOnDelete) creada sin issues |
| Rollback necesario | No |

El mismo `migrate` que corrió en el pilot correrá en producción. **Cero sorpresas esperadas.**

## Pre-requisitos antes del cutover

- [ ] Backup de la base de datos de producción **inmediatamente antes** del deploy. No usar el backup automático nocturno — uno manual fresco.
- [ ] Backup del directorio `storage/app/private/certificates` (certificados DGII si existen).
- [ ] Verificar que el branch a deployar pasa CI completa (1130+ tests).
- [ ] Comunicar ventana de mantenimiento a los usuarios activos (~5 min).
- [ ] Tener el secreto de mantenimiento listo: `php artisan down --secret=<TOKEN>`.

## Variables `.env` nuevas requeridas en producción

Antes del deploy, agregar al `.env` de producción (ver `.env.example`):

```
# Sanctum (Wave 2 — token expiration)
SANCTUM_TOKEN_EXPIRATION_MINUTES=43200    # 30 días

# Logging estructurado (Wave 2 — Neo B)
LOG_CHANNEL=structured

# Backup (Wave 2 — Neo B)
BACKUP_DISK=s3
BACKUP_NOTIFICATION_EMAIL=ops@agendamax.do
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=agendamax-backups

# FCM Push Notifications
FIREBASE_CREDENTIALS=/var/www/html/backend/storage/app/private/firebase-credentials.json

# DGII (configurable per business via UI, no global env)
```

## F1 — Discovery + Landing pública + Geo fields

Feature entregada en Sprint 3 junto con Payroll/POS/FE. Documenta los cambios de schema, endpoints y smoke tests específicos de la feature de Discovery público.

### Migraciones

| Orden | Archivo | Descripción |
|---|---|---|
| 1 | `2026_05_04_175447_add_location_fields_to_businesses` | Agrega `sector` (string, nullable), `province` (string, nullable), `country` (string, default 'DO'), `latitude` (decimal 10,7, nullable), `longitude` (decimal 10,7, nullable) a `businesses` |
| 2 | `2026_05_04_212307_add_lat_lng_index_to_businesses` | Crea índice compuesto `businesses_lat_lng_idx` sobre `(latitude, longitude)` para bounding box geo queries |

Ambas migraciones son no-destructivas (solo `ADD COLUMN` / `CREATE INDEX`) y están incluidas en el `php artisan migrate --force` del Paso 5.

### Endpoints API nuevos

| Método | URI | Descripción |
|---|---|---|
| `GET` | `/api/v1/businesses` | Discovery público paginado. Filtros: `q`, `search`, `sector`, `province`, `lat`, `lng`, `radius_km`, `service_id` |
| `GET` | `/api/v1/businesses/{id}` | Perfil público por ID numérico. Devuelve servicios activos, empleados activos, categorías. Requiere `status=active` |
| `GET` | `/api/v1/businesses/by-slug/{slug}` | Perfil público por slug. Mismo payload que el anterior |

El endpoint `/api/v1/businesses` acepta `?q=` (web/admin) y `?search=` (mobile) como alias para el mismo término de búsqueda. `?q=` toma precedencia cuando ambos están presentes.

La búsqueda de texto usa `MATCH...AGAINST` en drivers `mysql` y `mariadb`. En SQLite (tests) cae en `LIKE` automáticamente.

### Web: Landing pública sin autenticación

| Método | URI | Descripción |
|---|---|---|
| `GET` | `/negocio/{slug}` | Landing pública del negocio. No requiere sesión ni Sanctum token |

Esta ruta está fuera del grupo `auth` y del middleware `business`. Cualquier visitante puede acceder a `agendamax.do/negocio/{slug}`.

### Smoke tests post-deploy F1

Ejecutar estos checks adicionales en el Paso 8 del procedimiento:

```bash
# F1.1 Discovery por provincia
curl -s "https://agendamax.do/api/v1/businesses?province=Distrito+Nacional" \
  | python3 -m json.tool | grep -E '"name"|"total"'

# F1.2 Perfil público por slug
curl -s "https://agendamax.do/api/v1/businesses/by-slug/paomakeup-beauty-salon" \
  | python3 -m json.tool | grep -E '"name"|"slug"|"status"'

# F1.3 Landing web pública (sin token)
curl -si "https://agendamax.do/negocio/paomakeup-beauty-salon" | head -5
# Esperar: HTTP/2 200

# F1.4 Discovery geo (bounding box + distancia)
curl -s "https://agendamax.do/api/v1/businesses?lat=18.4861&lng=-69.9312&radius_km=10" \
  | python3 -m json.tool | grep -E '"name"|"distance_km"'

# F1.5 Verificar índice geo activo en producción
php artisan tinker --execute="
\$explain = DB::select('EXPLAIN SELECT * FROM businesses WHERE latitude BETWEEN 18.0 AND 19.0 AND longitude BETWEEN -70.0 AND -69.0');
echo \$explain[0]->key ?? 'NO INDEX';
"
# Debe imprimir: businesses_lat_lng_idx
```

## Procedimiento

### 1. Backup pre-deploy

```bash
ssh produccion
cd /var/www/html/backend
php artisan backup:run --only-db
# verificar éxito
php artisan backup:list | head -5
# además, dump manual como red de seguridad
mysqldump -u <user> -p --single-transaction --no-tablespaces agenda_max > /tmp/pre-cutover-$(date +%Y%m%d-%H%M).sql
ls -lh /tmp/pre-cutover-*.sql
```

### 2. Modo mantenimiento

```bash
php artisan down --secret="cutover-sprint3-$(date +%s)" --render="errors::503"
# anotar el TOKEN — sirve para que tú pruebes mientras los demás ven el 503
```

### 3. Pull de código y dependencias

```bash
cd /var/www/html/backend
git fetch origin
git checkout main
git pull origin main
composer install --no-dev --optimize-autoloader
```

### 4. Instalar dependencias frontend y build

```bash
npm ci
npm run build
```

### 5. Migraciones (el paso clave)

```bash
php artisan migrate --force
```

**Salida esperada** (basada en el pilot):
- 32 migraciones, todas terminan en `DONE`
- Tiempo total < 1 segundo en hardware similar
- Sin warnings de FK constraints

Si alguna falla, **NO ejecutar `migrate:rollback`** sin antes:
1. Capturar la salida completa.
2. Verificar qué migraciones sí corrieron (`php artisan migrate:status`).
3. Decidir si rollback automático es seguro o si hay que restaurar del dump.

### 6. Cache y autoload

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize
```

### 7. Reiniciar servicios

```bash
# Restart queue workers (procesan jobs nuevos como EmitEcfJob)
php artisan queue:restart

# Restart Reverb (broadcasting)
sudo systemctl restart reverb

# Restart PHP-FPM (limpia opcache)
sudo systemctl restart php8.4-fpm

# Restart Nginx (si hay cambios de config)
sudo systemctl reload nginx
```

### 8. Smoke tests post-deploy

Manteniendo el modo mantenimiento, accede vía `?secret=<TOKEN>`:

```bash
# 8.1 Health checks
curl -i https://agendamax.do/health
curl -i https://agendamax.do/health/ready

# 8.2 Verificar que migraciones quedaron registradas
php artisan migrate:status | tail -35

# 8.3 Login admin (PM Beauty Studio)
# desde el navegador: https://agendamax.do/login?secret=<TOKEN>
# credenciales: paomakeup@gmail.com

# 8.4 Verificar que las tablas nuevas existen y son accesibles
php artisan tinker --execute="
echo App\Models\PayrollPeriod::count(); echo PHP_EOL;
echo App\Models\Pos\PosTicket::count(); echo PHP_EOL;
echo App\Models\Ecf::count(); echo PHP_EOL;
"

# 8.5 Verificar que los 7 EmployeeSchedules de PM Beauty siguen
php artisan tinker --execute="
\$count = App\Models\EmployeeSchedule::whereHas('employee', fn(\$q) => \$q->where('business_id', 4))->count();
echo \"PM Beauty schedules: {\$count}\n\";
"
# debe imprimir: PM Beauty schedules: 7
```

### 9. Salir de mantenimiento

```bash
php artisan up
```

### 10. Monitoreo post-deploy (primeras 2 horas)

- [ ] Revisar `storage/logs/laravel-*.log` cada 15 min. Buscar `ERROR` o `CRITICAL`.
- [ ] Verificar que la cola está procesando: `php artisan queue:monitor default`.
- [ ] Verificar Reverb en logs: ningún disconnect masivo.
- [ ] Probar booking real desde mobile app.
- [ ] Verificar que el cliente PM Beauty puede ver sus appointments y schedules.

## Rollback (solo si algo sale mal)

### Escenario A: Falla en `migrate`
```bash
php artisan migrate:rollback --step=32
# verificar
php artisan migrate:status | grep Pending | wc -l   # debe ser 32
```
La data original está intacta porque las migraciones agregan, no modifican existentes.

### Escenario B: Aplicación inestable post-deploy
```bash
# 1. Volver a mantenimiento
php artisan down

# 2. Restore desde el dump pre-cutover
mysql -u <user> -p agenda_max < /tmp/pre-cutover-YYYYMMDD-HHMM.sql

# 3. Checkout al commit anterior
git log --oneline -5
git checkout <commit-pre-deploy>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize:clear && php artisan optimize

# 4. Reiniciar servicios
php artisan queue:restart
sudo systemctl restart php8.4-fpm reverb

# 5. Salir de mantenimiento
php artisan up
```

## Variables que el cliente debe configurar manualmente post-deploy

Estas no van en el código ni en `.env`, son setup en la UI:

1. **Certificado DGII** (Settings → Facturación Electrónica): subir `.p12` y poner password. Encryption automático en BD.
2. **Configuración FE por negocio**: RNC, razón social fiscal, ambiente (TestECF/CertECF/ECF).
3. **Rangos NCF**: solicitar a DGII y registrar en `/settings/electronic-invoice`.
4. **Reglas de comisión por empleado**: `/payroll/commission-rules`.
5. **Salario base por empleado**: `/payroll/employees/{id}/edit`.
6. **2FA admin**: en el primer login post-deploy, el middleware `ensure-2fa` redirige a setup automáticamente.

## Validación cruzada con el pilot local

Si necesitas re-correr el pilot antes del deploy real (recomendado si pasaron días desde el primero):

```bash
# desde local, fuera del servidor
ddev mysql -uroot -proot -e "DROP DATABASE IF EXISTS agenda_max_prod; CREATE DATABASE agenda_max_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
ddev import-db --database=agenda_max_prod --file=~/Downloads/agenda_max.sql
# editar .env: DB_DATABASE=agenda_max_prod
ddev exec --dir /var/www/html/backend php artisan config:clear
ddev exec --dir /var/www/html/backend php artisan migrate --force
# verificar 32 DONE, 0 errores
# si todo OK, cutover real es seguro
```
