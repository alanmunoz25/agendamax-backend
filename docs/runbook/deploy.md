# Deploy a Produccion

## Pre-requisitos

- Acceso SSH al servidor de produccion
- Acceso al repositorio Git
- Credenciales AWS S3 disponibles (para verificar backups pre-deploy)
- Las migraciones han sido revisadas y son reversibles

---

## Procedimiento de deploy

### 1. Verificar backup reciente antes de deployar

```bash
# En el servidor de produccion
php /var/www/html/backend/artisan backup:list
```

Si el ultimo backup tiene mas de 24 horas, ejecutar uno manual antes de continuar:

```bash
php /var/www/html/backend/artisan backup:run --only-db
```

### 2. Poner la aplicacion en modo mantenimiento

```bash
php /var/www/html/backend/artisan down --secret="TOKEN_SECRETO_DEPLOY"
```

Esto permite acceder a la app con `?secret=TOKEN_SECRETO_DEPLOY` durante el deploy.

### 3. Hacer pull del codigo

```bash
cd /var/www/html/backend
git pull origin main
```

### 4. Instalar dependencias PHP

```bash
composer install --no-dev --optimize-autoloader
```

### 5. Ejecutar migraciones

```bash
php artisan migrate --force
```

Si la migracion falla: ver [database.md — Migrations rollback](database.md#rollback-de-migraciones).

### 6. Limpiar y recargar cache

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 7. Reiniciar queue workers

```bash
php artisan queue:restart
```

Los workers se reinician solos cuando terminan el job actual. Esperar 30-60 segundos.

### 8. Reiniciar Laravel Reverb (websockets)

```bash
# Dependiendo del supervisor configurado
supervisorctl restart reverb
# o
php artisan reverb:restart
```

### 9. Verificar certificados DGII

```bash
ls -la /var/www/html/backend/storage/app/private/certificates/
```

- El archivo `.p12` o `.pfx` del RNC emisor debe existir y ser legible por el proceso PHP
- La fecha de expiracion del certificado se verifica en la tabla `business_fe_configs`

### 10. Verificar credenciales FCM

```bash
ls -la /var/www/html/backend/storage/app/firebase/credentials.json
```

Si el archivo no existe o esta vacio: ver [rotate-secrets.md — FCM](rotate-secrets.md#fcm-firebase-cloud-messaging).

### 11. Compilar assets frontend

```bash
cd /var/www/html/backend
npm ci
npm run build
```

### 12. Levantar la aplicacion

```bash
php artisan up
```

### 13. Verificar health checks

```bash
curl https://app.crezer.app/health
# Esperado: {"status":"ok","version":"...","uptime":...}

curl https://app.crezer.app/health/ready
# Esperado: {"db":"ok","cache":"ok","queue":"ok"}
```

Si alguno retorna `"fail"`, NO dar por exitoso el deploy. Ver [incident-response.md](incident-response.md).

---

## Rollback de deploy

Si el deploy falla despues de las migraciones:

```bash
# Revertir ultima migracion
php artisan migrate:rollback

# Volver al commit anterior
git checkout HEAD~1

# Reinstalar dependencias del commit anterior
composer install --no-dev --optimize-autoloader

# Limpiar cache
php artisan config:cache
php artisan route:cache

# Levantar
php artisan up
```

Si hay multiples migraciones a revertir, ver [database.md — Rollback](database.md#rollback-de-migraciones).

---

## Checklist post-deploy

- [ ] `/health` retorna 200 con `"status":"ok"`
- [ ] `/health/ready` retorna 200 con todos en `"ok"`
- [ ] Login en la app web funciona
- [ ] API `/api/v1/auth/login` responde correctamente
- [ ] Queue workers procesando jobs (verificar logs)
- [ ] Sin errores 500 en logs durante los primeros 5 minutos
