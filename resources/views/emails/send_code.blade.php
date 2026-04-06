<!DOCTYPE html>
<html>
<head>
    <title>Verification Code</title>
</head>
<body>
    <h2>{{ $details['title'] ?? 'Yekbun Verification' }}</h2>
    <p>Hello {{ $details['username'] ?? 'User' }},</p>
    <p>Your verification code is: <strong>{{ $details['code'] }}</strong></p>
    <p>Please use this code to verify your account.</p>
    <br>
    <p>Thank you,<br>Yekbun Team</p>
</body>
</html>
