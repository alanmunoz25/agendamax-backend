<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Recibido</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #059669;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .payment-details {
            background-color: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        .detail-row {
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: bold;
            color: #6b7280;
            font-size: 14px;
        }
        .detail-value {
            color: #111827;
            font-size: 16px;
            margin-top: 5px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Pago Recibido</h1>
    </div>

    <div class="content">
        <p>Hola {{ $data['customer_name'] }},</p>

        <p>Hemos recibido tu pago exitosamente. A continuacion los detalles:</p>

        <div class="payment-details">
            <div class="detail-row">
                <div class="detail-label">Curso</div>
                <div class="detail-value">{{ $data['course_title'] }}</div>
            </div>

            <div class="detail-row">
                <div class="detail-label">Monto Pagado</div>
                <div class="detail-value">{{ $data['currency'] }} {{ number_format((float) $data['amount_paid'], 2) }}</div>
            </div>

            @if(!empty($data['payment_reference']))
            <div class="detail-row">
                <div class="detail-label">Referencia de Pago</div>
                <div class="detail-value">{{ $data['payment_reference'] }}</div>
            </div>
            @endif

            @if(!empty($data['start_date']))
            <div class="detail-row">
                <div class="detail-label">Fecha de Inicio del Curso</div>
                <div class="detail-value">{{ $data['start_date'] }}</div>
            </div>
            @endif
        </div>

        <p style="margin-top: 30px;">
            <strong>{{ $data['business_name'] }}</strong><br>
            @if(!empty($data['business_address'])){{ $data['business_address'] }}<br>@endif
            @if(!empty($data['business_phone'])){{ $data['business_phone'] }}@endif
        </p>

        <p style="color: #6b7280; font-size: 14px; margin-top: 30px;">
            Si tienes alguna pregunta, contactanos a {{ $data['business_email'] }}@if(!empty($data['business_phone'])) o al {{ $data['business_phone'] }}@endif.
        </p>
    </div>

    <div class="footer">
        <p>Gracias por tu pago! {{ $data['business_name'] }}</p>
        <p style="font-size: 12px; color: #9ca3af;">
            Este es un mensaje automatico. Por favor no respondas a este correo.
        </p>
    </div>
</body>
</html>
