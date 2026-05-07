# Protocolo de Respuesta a Incidentes

## Clasificacion de severidad

| Nivel | Descripcion | Tiempo de respuesta |
|-------|-------------|---------------------|
| P1 — Critico | App completamente caida, perdida de datos, breach de seguridad | Inmediato (< 15 min) |
| P2 — Alto | Feature critico no funciona (pagos, facturacion), degradacion masiva | < 1 hora |
| P3 — Medio | Feature secundario degradado, lentitud | < 4 horas |
| P4 — Bajo | Bug cosmético, impacto minimo | Proximo sprint |

---

## Fase 1: Deteccion

### Verificar health checks primero

```bash
curl https://app.crezer.app/health
curl https://app.crezer.app/health/ready
```

### Revisar logs recientes (ultimos 100 errores)

```bash
# En produccion con canal structured (JSON)
tail -100 /var/www/html/backend/storage/logs/laravel-$(date +%Y-%m-%d).log | jq 'select(.level_name == "ERROR")'

# Si no hay jq instalado
tail -100 /var/www/html/backend/storage/logs/laravel-$(date +%Y-%m-%d).log | grep '"level_name":"ERROR"'
```

### Verificar cola de jobs

```bash
php /var/www/html/backend/artisan queue:failed
php /var/www/html/backend/artisan queue:monitor
```

### Verificar uso de recursos

```bash
# CPU y memoria
top -bn1 | head -20

# Espacio en disco
df -h

# Conexiones de base de datos
mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "SHOW PROCESSLIST;"
```

---

## Fase 2: Triage

### App completamente caida (502/503)

1. Verificar que PHP-FPM esta corriendo: `systemctl status php8.4-fpm`
2. Verificar nginx/apache: `systemctl status nginx`
3. Revisar logs de nginx: `tail -50 /var/log/nginx/error.log`
4. Verificar que `.env` existe y tiene `APP_KEY` seteada

### Error 500 masivo en API

1. Identificar el `request_id` del error en logs
2. Buscar por `request_id` para ver el stack:
   ```bash
   grep "REQUEST_ID_AQUI" /var/www/html/backend/storage/logs/laravel-$(date +%Y-%m-%d).log
   ```
3. Revisar si hay una migracion reciente que rompio el schema
4. Verificar si el error es en un job de la cola (puede ser timeout o falta de memoria)

### Base de datos no responde

1. Verificar que MariaDB esta corriendo: `systemctl status mariadb`
2. Verificar conexion: `mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "SELECT 1;"`
3. Revisar espacio en disco (MariaDB puede bloquearse si el disco esta lleno)
4. Revisar logs de MariaDB: `tail -50 /var/log/mysql/error.log`

### Queue workers caidos

```bash
# Ver status de supervisord
supervisorctl status

# Reiniciar workers
supervisorctl restart laravel-worker:*

# Verificar jobs fallidos
php /var/www/html/backend/artisan queue:failed
```

---

## Fase 3: Mitigacion

### Activar modo mantenimiento (P1/P2)

```bash
php /var/www/html/backend/artisan down --retry=60 --secret="SECRETO_EMERGENCIA"
```

Esto retorna 503 a usuarios pero permite acceso con `?secret=SECRETO_EMERGENCIA`.

### Limpiar cache corrupto

```bash
php /var/www/html/backend/artisan cache:clear
php /var/www/html/backend/artisan config:clear
php /var/www/html/backend/artisan route:clear
php /var/www/html/backend/artisan view:clear
```

### Reintentar jobs fallidos

```bash
# Reintentar todos los fallidos
php /var/www/html/backend/artisan queue:retry all

# Reintentar un job especifico por ID
php /var/www/html/backend/artisan queue:retry JOB_ID_AQUI
```

### Rollback de deploy de emergencia

Ver [deploy.md — Rollback](deploy.md#rollback-de-deploy).

### Levantar la app

```bash
php /var/www/html/backend/artisan up
```

---

## Fase 4: Postmortem

Despues de resolver el incidente P1 o P2, documentar en el ticket:

1. **Timeline:** Cuando se detecto, cuando se mitig, cuando se resolvi
2. **Causa raiz:** Que lo causo exactamente
3. **Impacto:** Cuantos usuarios afectados, cuanto tiempo
4. **Acciones inmediatas:** Que se hizo para resolverlo
5. **Acciones preventivas:** Como evitar que vuelva a pasar

Plantilla: crear ticket en el issue tracker con tag `postmortem`.
