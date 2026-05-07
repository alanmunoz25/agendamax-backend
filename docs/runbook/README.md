# Crezer — Operations Runbook

**Audiencia:** Ingeniero on-call sin conocimiento previo del proyecto.
**Stack:** Laravel 12 · MariaDB · DDEV (local) · S3 (backup) · Laravel Reverb (websockets) · FCM (push) · DGII e-CF (facturacion electronica)

---

## Como usar este runbook en un incidente

1. Identifica el tipo de falla en la tabla de abajo
2. Ve al documento especifico
3. Ejecuta los pasos en orden — no improvises sin leer el doc completo

| Falla | Documento |
|-------|-----------|
| Deploy o rollback | [deploy.md](deploy.md) |
| Incidente de produccion (caida, error masivo) | [incident-response.md](incident-response.md) |
| DGII rechaza facturas / e-CF | [dgii-failures.md](dgii-failures.md) |
| Rotacion de credenciales o secretos | [rotate-secrets.md](rotate-secrets.md) |
| Backup / restore de base de datos | [database.md](database.md) |
| Logs y metricas | [monitoring.md](monitoring.md) |

---

## Informacion critica de acceso

| Recurso | Donde encontrarlo |
|---------|-------------------|
| Variables de entorno produccion | `/var/www/html/backend/.env` en servidor |
| Logs JSON (produccion) | `storage/logs/laravel-YYYY-MM-DD.log` |
| Certificados DGII | `storage/app/private/certificates/` |
| Backups S3 | Bucket definido en `BACKUP_S3_BUCKET` env var |

---

## Contactos

| Rol | Responsabilidad |
|-----|----------------|
| Lead Engineering | Decisiones de arquitectura, rollback de emergencia |
| DevOps | Infraestructura, DNS, S3, servidores |
| DGII soporte | +1-809-689-3444 (RD) — para problemas de autorizacion de e-CF |
