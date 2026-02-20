<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Reminder</title>
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
            background-color: #F59E0B;
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
        .appointment-details {
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
        .highlight {
            background-color: #FEF3C7;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #F59E0B;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⏰ Appointment Reminder</h1>
    </div>

    <div class="content">
        <p>Hi {{ $data['client_name'] }},</p>

        <div class="highlight">
            <p style="margin: 0; font-size: 16px; font-weight: bold;">
                This is a friendly reminder about your upcoming appointment!
            </p>
        </div>

        <div class="appointment-details">
            <div class="detail-row">
                <div class="detail-label">Service</div>
                <div class="detail-value">{{ $data['service_name'] }}</div>
            </div>

            <div class="detail-row">
                <div class="detail-label">Provider</div>
                <div class="detail-value">{{ $data['employee_name'] }}</div>
            </div>

            <div class="detail-row">
                <div class="detail-label">Date & Time</div>
                <div class="detail-value">{{ $data['scheduled_at'] }}</div>
            </div>

            <div class="detail-row">
                <div class="detail-label">Duration</div>
                <div class="detail-value">{{ $data['service_duration'] }} minutes</div>
            </div>
        </div>

        <p style="margin-top: 30px;">
            <strong>Location:</strong><br>
            {{ $data['business_name'] }}<br>
            {{ $data['business_address'] }}<br>
            {{ $data['business_phone'] }}
        </p>

        <p style="color: #6b7280; font-size: 14px; margin-top: 30px;">
            We're looking forward to seeing you! If you need to reschedule or cancel, please contact us as soon as possible at {{ $data['business_email'] }} or {{ $data['business_phone'] }}.
        </p>
    </div>

    <div class="footer">
        <p>See you soon at {{ $data['business_name'] }}!</p>
        <p style="font-size: 12px; color: #9ca3af;">
            This is an automated message. Please do not reply to this email.
        </p>
    </div>
</body>
</html>
