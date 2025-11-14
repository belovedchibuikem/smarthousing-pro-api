<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
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
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }
        .footer {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $subject }}</h1>
    </div>
    
    <div class="content">
        <p>Hello {{ $recipient['name'] }},</p>
        
        <div style="white-space: pre-line;">{!! nl2br(e($message)) !!}</div>
        
        <p>Best regards,<br>
        FRSC Housing Team</p>
    </div>
    
    <div class="footer">
        <p>This email was sent from the FRSC Housing platform.</p>
        <p>If you have any questions, please contact our support team.</p>
    </div>
</body>
</html>


