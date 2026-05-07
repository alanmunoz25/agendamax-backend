# Sprint 4 Cutover Runbook

## Variables .env Nuevas

Las siguientes variables de entorno deben configurarse en producción antes del deploy:

- `AGENDAMAX_USE_BUSINESS_CONTEXT=true` — Activa el middleware ResolveBusinessContext para contexto de negocio por request
- `AGENDAMAX_CLIENT_MULTI_BUSINESS=true` — Permite que un cliente pertenezca a múltiples negocios (default: true)

## FLIP MANUAL — Paso Crítico Pre-Deploy

**CONFIRMAR que `AGENDAMAX_USE_BUSINESS_CONTEXT=true` está en el .env de producción ANTES de ejecutar smoke tests post-deploy.**

Sin esta variable activa, las rutas de cliente multi-negocio (F2/F3/F4) no funcionarán correctamente.

## Smoke Tests Sprint 4

Ejecutar los siguientes 6 checks después del deploy:

1. `GET /api/v1/client/businesses` — Lista negocios del cliente autenticado → esperar 200 con array JSON
2. `POST /api/v1/client/businesses/{id}/enroll` — Enrolar cliente a un negocio → esperar 200 o 201
3. `GET /api/v1/client/appointments?scope=all` — Citas de todos los negocios → esperar 200 con array
4. `GET /api/v1/client/appointments?scope=business` sin header `X-Business-ID` → esperar 422 (validación)
5. `POST /api/v1/businesses/{id}/clients/{userId}/block` con body `{"reason":"test"}` → esperar 200
6. `GET /api/v1/businesses/buscar?q=test` — Búsqueda pública de negocios → esperar 200

## Rollback

Si algún smoke test falla:
1. Revertir `.env`: `AGENDAMAX_USE_BUSINESS_CONTEXT=false`
2. `php artisan config:clear && php artisan config:cache`
3. Contactar al equipo de backend
