# Fallas de DGII — Facturacion Electronica (e-CF)

## Contexto

Crezer emite Comprobantes Fiscales Electronicos (e-CF) hacia la DGII de la Republica Dominicana. Si la DGII rechaza un comprobante, el POS marca el ticket con estado `rejected` y genera el error `BLOCK-002` internamente.

**Tabla relevante:** `pos_tickets` — columna `fe_status` (`pending`, `sent`, `accepted`, `rejected`, `void`)
**Configuracion por negocio:** `business_fe_configs` — RNC emisor, ambiente (TestECF / CertificadoECF), certificado

---

## Diagnostico: por que falla

### 1. Revisar el log del ticket rechazado

```bash
# Buscar errores de facturacion en logs
grep '"exception":".*Invoice\|.*Dgii\|.*FE\|.*ecf"' \
  /var/www/html/backend/storage/logs/laravel-$(date +%Y-%m-%d).log | jq .
```

### 2. Ver el mensaje de rechazo de DGII

En la base de datos:

```sql
SELECT id, fe_status, fe_response, fe_error, created_at
FROM pos_tickets
WHERE fe_status = 'rejected'
ORDER BY created_at DESC
LIMIT 10;
```

### 3. Codigos de error comunes de DGII

| Codigo | Causa | Accion |
|--------|-------|--------|
| `CERT_EXP` | Certificado digital vencido | Renovar certificado — ver [rotate-secrets.md](rotate-secrets.md#certificados-dgii) |
| `RNC_INVALID` | RNC emisor invalido o no autorizado | Verificar `business_fe_configs.rnc_emisor` |
| `SEQ_DUPLICATE` | Numero de secuencia repetido (e-CF ya enviado) | Ver seccion "Comprobante duplicado" abajo |
| `TIMEOUT` | DGII no responde en tiempo | Reintentar — ver seccion "Reintentar envio" |
| `FORMAT_ERROR` | XML malformado | Revisar version de la libreria e-CF y schema |
| `AMBIENTE_MISMATCH` | Ambiente incorrecto (Test vs Produccion) | Verificar `FE_DEFAULT_AMBIENTE` en `.env` |

---

## Acciones por tipo de falla

### Reintentar envio (timeout o falla temporal)

```bash
# Via Artisan — reenviar tickets en estado pending o rejected
php /var/www/html/backend/artisan fe:retry-pending

# Si el comando no existe aun, ejecutar directamente en la DB:
UPDATE pos_tickets
SET fe_status = 'pending', fe_error = NULL
WHERE fe_status = 'rejected'
  AND id IN (LISTA_DE_IDS_AQUI);
```

Luego esperar a que el queue worker procese los jobs pendientes.

### Comprobante duplicado (SEQ_DUPLICATE)

La DGII ya tiene el comprobante registrado. Pasos:

1. Verificar en el portal de DGII si el comprobante existe y esta aceptado
2. Si DGII lo acepto: actualizar el estado en la DB manualmente:
   ```sql
   UPDATE pos_tickets
   SET fe_status = 'accepted', fe_response = '{"manual":"true","reason":"verified_in_dgii_portal"}'
   WHERE id = ID_DEL_TICKET;
   ```
3. Si DGII no lo tiene: hay una inconsistencia — escalar a Lead Engineering

### BLOCK-002 — Void manual (workaround)

Cuando un ticket fue emitido pero necesita anularse y DGII no responde o rechaza el void:

1. Registrar el void localmente en la DB:
   ```sql
   UPDATE pos_tickets
   SET fe_status = 'void',
       fe_void_reason = 'BLOCK-002: manual void due to DGII unavailability',
       fe_voided_at = NOW(),
       fe_voided_by = USER_ID_DEL_ADMIN
   WHERE id = ID_DEL_TICKET AND fe_status IN ('accepted', 'rejected');
   ```
2. Guardar evidencia: tomar screenshot del portal DGII mostrando el estado
3. Reportar a contabilidad para registrar la anulacion manual en los libros
4. Cuando DGII vuelva a estar disponible, enviar la nota de credito correspondiente

### Certificado vencido

Ver [rotate-secrets.md — Certificados DGII](rotate-secrets.md#certificados-dgii).

---

## Verificar configuracion del negocio

```sql
SELECT
  b.id,
  b.name,
  bfc.rnc_emisor,
  bfc.ambiente,
  bfc.certificate_path,
  bfc.certificate_expires_at
FROM business_fe_configs bfc
JOIN businesses b ON b.id = bfc.business_id
WHERE bfc.active = 1;
```

Si `certificate_expires_at` es en el pasado o en los proximos 30 dias, renovar el certificado.

---

## Contacto DGII para soporte tecnico

- Telefono: +1-809-689-3444 (opcion soporte TI)
- Email: soporte.ecf@dgii.gov.do
- Portal de estado de servicios: https://dgii.gov.do/servicios/
- Horario de soporte: Lunes a Viernes 8:00 AM - 5:00 PM (hora RD)
