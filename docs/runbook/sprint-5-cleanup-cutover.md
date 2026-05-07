# Sprint 5 (F5) Cleanup Cutover Runbook

## Ventana de Mantenimiento

**Duración estimada: 30 minutos**

El rename de columna `business_id → primary_business_id` en la tabla `users` requiere un bloqueo de tabla. En producción con usuarios activos, activar maintenance mode antes de migrar.

## Pre-Deploy

### 1. Backup explícito (no confiar solo en el automático)
```bash
# Backup de tabla crítica
mysqldump -h [host] -u [user] -p [database] users > users_pre_f5_$(date +%Y%m%d_%H%M%S).sql
# Backup completo
mysqldump -h [host] -u [user] -p [database] > full_pre_f5_$(date +%Y%m%d_%H%M%S).sql
```

### 2. Git tag pre-deploy
```bash
git tag agendamax-f5-pre
git push origin agendamax-f5-pre
```

## Pasos de Deploy

1. **Maintenance ON:** `php artisan down --retry=60`
2. **Backup** (ver Pre-Deploy paso 1)
3. `git pull origin [branch]`
4. `composer install --no-dev --optimize-autoloader`
5. `php artisan migrate --force`
6. `php artisan config:clear && php artisan config:cache`
7. `php artisan route:clear && php artisan route:cache`
8. `php artisan view:clear && php artisan optimize`
9. **Smoke tests** (ver abajo)
10. **Maintenance OFF:** `php artisan up`

## Smoke Tests Post-Deploy

- [ ] GET /login → 200 (formulario de login visible)
- [ ] GET /negocio/{slug} → 200 (página de negocio)
- [ ] GET /paomakeup-beauty-salon/courses → 301 redirect a /negocio/paomakeup-beauty-salon/courses
- [ ] POST /api/v1/auth/login → 200 (auth funciona, primary_business_id presente)
- [ ] GET /dashboard → 200 o redirect a login (no crash)

## Git Tag Post-Deploy
```bash
git tag agendamax-f5-post
git push origin agendamax-f5-post
```

## Rollback (RTO 30 min)

Si algún smoke test falla:
1. `php artisan down`
2. Restaurar DB: `mysql -h [host] -u [user] -p [database] < full_pre_f5_*.sql`
3. `git checkout agendamax-f5-pre`
4. `composer install --no-dev`
5. `php artisan config:clear && php artisan up`
