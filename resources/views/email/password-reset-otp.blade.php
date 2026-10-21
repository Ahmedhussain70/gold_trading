<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Reset OTP</title>
</head>
<body>
    <p>Hello {{ $name }},</p>

    <p>Your password reset code is:</p>

    <h2>{{ $code }}</h2>

    <p>This code will expire in 10 minutes.</p>

    <p>If you did not request this, please ignore this email.</p>
</body>
</html>