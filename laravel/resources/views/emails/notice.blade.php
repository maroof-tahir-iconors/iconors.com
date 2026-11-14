{{--
    The one HTML email layout. Every value is escaped — nothing a visitor
    typed is ever rendered as markup. Inline styles only: mail clients
    strip <style> blocks and ignore external stylesheets.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f5f7;font-family:Inter,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1c2430;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f5f7;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e3e8ee;">
                <tr>
                    <td style="background:#0d1117;padding:18px 28px;">
                        <span style="font-size:18px;font-weight:700;color:#3CF7A2;letter-spacing:.2px;">{{ $siteName }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        <h1 style="margin:0 0 14px;font-size:20px;line-height:1.3;color:#0d1117;">{{ $heading }}</h1>

                        @foreach ($intro as $line)
                            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#3b4654;">{{ $line }}</p>
                        @endforeach

                        @if (! empty($fields))
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border-collapse:collapse;">
                                @foreach ($fields as $label => $value)
                                    <tr>
                                        <td style="padding:8px 12px 8px 0;font-size:13px;color:#6b7785;white-space:nowrap;vertical-align:top;border-bottom:1px solid #eef1f4;width:1%;">{{ $label }}</td>
                                        <td style="padding:8px 0;font-size:14px;color:#1c2430;vertical-align:top;border-bottom:1px solid #eef1f4;word-break:break-word;">{{ $value }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif

                        @if ($bodyText !== '')
                            @if ($bodyLabel !== '')
                                <p style="margin:18px 0 6px;font-size:13px;color:#6b7785;">{{ $bodyLabel }}</p>
                            @endif
                            <div style="padding:14px 16px;background:#f6f8fa;border-left:3px solid #3CF7A2;border-radius:6px;font-size:15px;line-height:1.6;color:#1c2430;word-break:break-word;">{!! nl2br(e($bodyText)) !!}</div>
                        @endif

                        @if (is_array($action) && ! empty($action['url']) && ! empty($action['label']))
                            <p style="margin:22px 0 4px;">
                                <a href="{{ $action['url'] }}" style="display:inline-block;padding:10px 18px;background:#0d1117;color:#3CF7A2;font-size:14px;font-weight:600;text-decoration:none;border-radius:8px;">{{ $action['label'] }}</a>
                            </p>
                        @endif

                        @foreach ($outro as $line)
                            <p style="margin:14px 0 0;font-size:14px;line-height:1.6;color:#3b4654;">{{ $line }}</p>
                        @endforeach
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 28px;background:#fafbfc;border-top:1px solid #e3e8ee;font-size:12px;color:#8a94a0;">
                        Sent by {{ $siteName }} · <a href="{{ $siteUrl }}" style="color:#8a94a0;">{{ preg_replace('~^https?://~', '', $siteUrl) }}</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
