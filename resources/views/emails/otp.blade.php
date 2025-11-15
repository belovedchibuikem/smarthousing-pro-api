<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Verification Code</title>
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
            background-color: #FDB11E;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .otp-box {
            background-color: #276254;
            color: white;
            font-size: 32px;
            font-weight: bold;
            text-align: center;
            padding: 20px;
            border-radius: 8px;
            letter-spacing: 8px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>FRSC Housing Management System</h1>
    </div>
    
    <div class="content">
        <h2>Hello {{ $user->first_name }}!</h2>
        
        @if($type === 'registration')
            <p>Thank you for registering with FRSC Housing Management System. To complete your registration, please use the verification code below:</p>
        @elseif($type === 'password_reset')
            <p>You have requested to reset your password. Use the verification code below to proceed:</p>
        @else
            <p>Please use the verification code below to verify your email address:</p>
        @endif
        
        <div class="otp-box">
            {{ $otp }}
        </div>
        
        <div class="warning">
            <strong>Important:</strong> This code will expire in 10 minutes. Do not share this code with anyone.
        </div>
        
        <p>If you didn't request this code, please ignore this email or contact support if you have concerns.</p>
    </div>
    
    <div class="footer">
        <p>&copy; {{ date('Y') }} FRSC Housing Management System. All rights reserved.</p>
        <p>This is an automated message, please do not reply.</p>
    </div>
</body>
</html>

