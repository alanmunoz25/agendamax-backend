# Rotacion de Secretos y Credenciales

**IMPORTANTE:** Antes de rotar cualquier secreto en produccion, verificar que hay un backup reciente.
Ver [database.md — Verificar ultimo backup](database.md#verificar-ultimo-backup).

---

## APP_KEY (Laravel Application Key)

**Impacto de rotar:** Invalida todas las sessions activas y cookies encriptadas. Todos los usuarios son deslogueados.

```bash
# 1. Generar nueva clave
php /var/www/html/backend/artisan key:generate --show
# Copia el resultado: base64:XXXXXXXX==

# 2. Actualizar .env en produccion
nano /var/www/html/backend/.env
# Cambiar: APP_KEY=base64:NUEVA_CLAVE_AQUI

# 3. Limpiar cache de config
php /var/www/html/backend/artisan config:cache

# 4. Reiniciar queue workers
php /var/www/html/backend/artisan queue:restart
```

---

## DB_PASSWORD (Password de la base de datos)

```bash
# 1. Conectar a MariaDB como root
mysql -u root -p

# 2. Cambiar el password del usuario de la aplicacion
ALTER USER 'db_user'@'localhost' IDENTIFIED BY 'NUEVO_PASSWORD_AQUI';
FLUSH PRIVILEGES;
EXIT;

# 3. Actualizar .env
nano /var/www/html/backend/.env
# Cambiar: DB_PASSWORD=NUEVO_PASSWORD_AQUI

# 4. Verificar conexion
php /var/www/html/backend/artisan db:show

# 5. Limpiar cache
php /var/www/html/backend/artisan config:cache
```

---

## FCM — Firebase Cloud Messaging

Las credenciales FCM son un archivo JSON de cuenta de servicio de Google.

```bash
# 1. Descargar nuevas credenciales desde Firebase Console
#    -> Project Settings -> Service accounts -> Generate new private key

# 2. Subir el archivo al servidor
scp nuevo-credentials.json user@servidor:/var/www/html/backend/storage/app/firebase/credentials.json

# 3. Verificar permisos
chmod 600 /var/www/html/backend/storage/app/firebase/credentials.json
chown www-data:www-data /var/www/html/backend/storage/app/firebase/credentials.json

# 4. Verificar que .env apunta al archivo correcto
grep FIREBASE_CREDENTIALS /var/www/html/backend/.env
# Debe decir: FIREBASE_CREDENTIALS=storage/app/firebase/credentials.json

# 5. Limpiar cache de config
php /var/www/html/backend/artisan config:cache

# 6. Reiniciar queue workers (procesan push notifications)
php /var/www/html/backend/artisan queue:restart
```

---

## Certificados DGII (e-CF)

Los certificados son archivos `.p12` o `.pfx` emitidos por la DGII para firma digital de e-CF.

```bash
# 1. Recibir el nuevo certificado de la DGII o del contador del negocio

# 2. Subir al servidor en la carpeta de certificados privados
scp nuevo-cert.p12 user@servidor:/var/www/html/backend/storage/app/private/certificates/

# 3. Verificar permisos
chmod 600 /var/www/html/backend/storage/app/private/certificates/nuevo-cert.p12
chown www-data:www-data /var/www/html/backend/storage/app/private/certificates/nuevo-cert.p12

# 4. Actualizar la tabla business_fe_configs con la nueva ruta y fecha de expiracion
mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" <<'SQL'
UPDATE business_fe_configs
SET certificate_path = 'private/certificates/nuevo-cert.p12',
    certificate_password = 'PASSWORD_DEL_CERT',
    certificate_expires_at = '2027-01-01 00:00:00'
WHERE business_id = ID_DEL_NEGOCIO;
SQL

# 5. Verificar que la aplicacion puede leer el certificado
php /var/www/html/backend/artisan fe:verify-certificate --business-id=ID_DEL_NEGOCIO

# 6. Limpiar cache de config
php /var/www/html/backend/artisan config:cache
```

**Nota:** Los certificados DGII tienen vigencia de 1-3 anos. Configurar recordatorio 60 dias antes de la expiracion.

---

## Tokens Sanctum — Revocar tokens de usuario

Para revocar todos los tokens de un usuario (ej. cuenta comprometida):

```bash
php /var/www/html/backend/artisan tinker
```

```php
// Revocar todos los tokens de un usuario
$user = \App\Models\User::find(USER_ID_AQUI);
$user->tokens()->delete();
echo "Tokens revocados para: " . $user->email;
```

Para revocar TODOS los tokens de la aplicacion (breach de seguridad masivo):

```php
\Laravel\Sanctum\PersonalAccessToken::truncate();
echo "TODOS los tokens han sido revocados. Todos los usuarios deben re-autenticarse.";
```

---

## AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY (S3 para backups)

```bash
# 1. En AWS IAM Console: crear nuevas credenciales para el usuario de backup
#    -> IAM -> Users -> crezer-backup -> Security credentials -> Create access key

# 2. Actualizar .env
nano /var/www/html/backend/.env
# Cambiar:
# AWS_ACCESS_KEY_ID=NUEVA_KEY
# AWS_SECRET_ACCESS_KEY=NUEVO_SECRET

# 3. Verificar acceso al bucket
php /var/www/html/backend/artisan backup:list

# 4. Si funciona, revocar las credenciales anteriores en AWS IAM Console

# 5. Limpiar cache
php /var/www/html/backend/artisan config:cache
```

---

## Checklist post-rotacion de secretos

- [ ] El health check `/health/ready` retorna `"db":"ok"` (confirma que DB_PASSWORD funciona)
- [ ] Un usuario puede hacer login via API (confirma que APP_KEY no rompio sessions existentes)
- [ ] Un backup manual completa con exito: `php artisan backup:run --only-db`
- [ ] Si se rotaron credenciales FCM: enviar push notification de prueba desde Firebase Console
