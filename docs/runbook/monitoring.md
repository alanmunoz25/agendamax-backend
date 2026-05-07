# Monitoring — Logs, Metricas y Dashboards

## Acceso rapido a logs en produccion

### Logs recientes (ultimos errores)

```bash
# Canal JSON estructurado (produccion con LOG_CHANNEL=structured)
tail -200 /var/www/html/backend/storage/logs/laravel-$(date +%Y-%m-%d).log \
  | grep '"level_name":"ERROR"' \
  | jq '{time: .datetime, msg: .message, request_id: .extra.request_id, user: .extra.user_id}'
```

### Buscar por request_id

Cada request tiene un `X-Request-Id` en el header de respuesta. Con ese ID puedes rastrear todos los logs de esa request:

```bash
grep '"request_id":"REQUEST-ID-AQUI"' \
  /var/www/html/backend/storage/logs/laravel-$(date +%Y-%m-%d).log | jq .
```

### Logs de hoy filtrados por nivel

```bash
# Solo errores
grep '"level_name":"ERROR"' /var/www/html/backend/storage/logs/laravel-$(date +%Y-%m-%d).log | jq .

# Solo warnings
grep '"level_name":"WARNING"' /var/www/html/backend/storage/logs/laravel-$(date +%Y-%m-%d).log | jq .

# Por usuario especifico
grep '"user_id":123' /var/www/html/backend/storage/logs/laravel-$(date +%Y-%m-%d).log | jq .
```

### Logs de dias anteriores

```bash
# Formato de archivo: laravel-YYYY-MM-DD.log
ls /var/www/html/backend/storage/logs/laravel-*.log
tail -100 /var/www/html/backend/storage/logs/laravel-2026-01-14.log | jq .
```

---

## Health checks

```bash
# Liveness: la app esta viva
curl https://app.crezer.app/health

# Readiness: todos los servicios estan listos
curl https://app.crezer.app/health/ready
```

Configurar alertas en tu load balancer o Kubernetes para que llame `/health/ready` cada 30 segundos. Si retorna 503, activar alerta.

---

## Queue workers

### Ver jobs en cola

```bash
# Tabla failed_jobs
php /var/www/html/backend/artisan queue:failed

# Ver jobs pendientes en DB (si usa database driver)
mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  -e "SELECT queue, payload, attempts, available_at FROM jobs ORDER BY created_at DESC LIMIT 20;"
```

### Ver jobs fallidos con detalle

```bash
php /var/www/html/backend/artisan queue:failed-table
# Luego:
mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  -e "SELECT id, connection, queue, payload, exception, failed_at FROM failed_jobs ORDER BY failed_at DESC LIMIT 10;"
```

---

## Backups

```bash
# Ver estado de backups
php /var/www/html/backend/artisan backup:list

# Verificar salud de los backups
php /var/www/html/backend/artisan backup:monitor
```

Un backup saludable:
- Existe un archivo con menos de 25 horas de antiguedad
- El tamano es mayor a 0 bytes
- El archivo es descomprimible (se verifica con `backup:monitor`)

---

## Base de datos

### Conexiones activas

```bash
mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "SHOW FULL PROCESSLIST;" | head -20
```

### Queries lentas (si slow query log esta habilitado)

```bash
tail -50 /var/log/mysql/slow.log
```

### Tamano de tablas criticas

```bash
mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" <<'SQL'
SELECT
  table_name,
  ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
  table_rows
FROM information_schema.tables
WHERE table_schema = DATABASE()
ORDER BY (data_length + index_length) DESC
LIMIT 20;
SQL
```

---

## Alertas recomendadas a configurar

| Metrica | Umbral de alerta | Accion |
|---------|-----------------|--------|
| `/health/ready` responde 503 | 2 checks consecutivos | Investigar DB/cache/queue |
| Tasa de errores 5xx > 1% | En 5 minutos | Ver logs de errores |
| Jobs fallidos > 10 nuevos en 1h | Acumulativo | Ver `queue:failed` |
| Espacio en disco > 85% | - | Limpiar logs o ampliar disco |
| Backup mas reciente > 25h | - | Ejecutar backup manual |
| Certificado DGII expira en < 30 dias | - | Renovar certificado |

---

## Estructura de campos en logs JSON (canal structured)

```json
{
  "message": "Descripcion del evento",
  "level_name": "INFO|WARNING|ERROR",
  "datetime": "2026-01-15T14:30:00.000000+00:00",
  "extra": {
    "request_id": "uuid-del-request",
    "user_id": 123,
    "business_id": 5,
    "route": "appointments.store",
    "method": "POST",
    "ip": "192.168.1.100"
  },
  "context": {
    "campos_adicionales": "del Log::error('msg', [...])"
  }
}
```

Usar `request_id` para correlacionar todos los logs de un mismo request HTTP.
