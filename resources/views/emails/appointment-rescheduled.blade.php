<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Rescheduled</title>
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
        .old-time {
            text-decoration: line-through;
            color: #9ca3af;
        }
        .new-time {
            color: #059669;
            font-weight: bold;
        }
        .time-change {
            background-color: #D1FAE5;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #059669;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Appointment Rescheduled</h1>
    </div>

    <div class="content">
        <p>Hi {{ $data['client_name'] }},</p>

        <p>Your appointment has been rescheduled to a new date and time.</p>

        <div class="time-change">
            <p style="margin: 0;">
                <span class="old-time">Previous: {{ $data['old_scheduled_at'] }}</span><br>
                <span class="new-time">New: {{ $data['scheduled_at'] }}</span>
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
                <div class="detail-label">New Date & Time</div>
                <div class="detail-value">{{ $data['scheduled_at'] }}</div>
            </div>

            <div class="detail-row">
                <div class="detail-label">Duration</div>
                <div class="detail-value">{{ $data['service_duration'] }} minutes</div>
            </div>

            <div class="detail-row">
                <div class="detail-label">Price</div>
                <div class="detail-value">${{ number_format($data['service_price'], 2) }}</div>
            </div>
        </div>

        <p style="margin-top: 30px;">
            <strong>Location:</strong><br>
            {{ $data['business_name'] }}<br>
            {{ $data['business_address'] }}<br>
            {{ $data['business_phone'] }}
        </p>

        <p style="color: #6b7280; font-size: 14px; margin-top: 30px;">
            We look forward to seeing you at the new time! If you need to make any changes, please contact us at {{ $data['business_email'] }} or {{ $data['business_phone'] }}.
        </p>
    </div>

    <div class="footer">
        <p>Thank you for choosing {{ $data['business_name'] }}!</p>
        <p style="font-size: 12px; color: #9ca3af;">
            This is an automated message. Please do not reply to this email.
        </p>
    </div>
</body>
</html>
