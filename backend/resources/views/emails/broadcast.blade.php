<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $mailSubject }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.06); }
        .header { background: #4f46e5; padding: 28px 32px; }
        .header h1 { margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: -0.3px; }
        .header p { margin: 4px 0 0; color: #c7d2fe; font-size: 13px; }
        .body { padding: 32px; }
        .greeting { font-size: 16px; color: #111827; font-weight: 600; margin: 0 0 16px; }
        .content { font-size: 15px; color: #374151; line-height: 1.7; white-space: pre-wrap; word-break: break-word; }
        .divider { border: none; border-top: 1px solid #e5e7eb; margin: 28px 0; }
        .footer { padding: 0 32px 28px; }
        .footer p { margin: 0; font-size: 12px; color: #9ca3af; line-height: 1.6; }
        .footer a { color: #6366f1; text-decoration: none; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ config('app.name', 'BiasharaMax') }}</h1>
            <p>Business Management Platform</p>
        </div>

        <div class="body">
            <p class="greeting">Hello {{ $recipientName }},</p>
            <p class="content">{{ $body }}</p>
        </div>

        <hr class="divider">

        <div class="footer">
            <p>
                This message was sent to you by the {{ config('app.name', 'BiasharaMax') }} platform administration team.<br>
                If you have questions, contact us at <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
            </p>
        </div>
    </div>
</body>
</html>
