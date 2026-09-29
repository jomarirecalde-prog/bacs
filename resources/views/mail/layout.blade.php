<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('email_title', $companyName)</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td { font-family: Arial, Helvetica, sans-serif !important; }
    </style>
    <![endif]-->
    <style type="text/css">
        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; }
            .email-padding { padding-left: 20px !important; padding-right: 20px !important; }
            .btn-primary { display: block !important; width: 100% !important; box-sizing: border-box !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f4f7f6;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f4f7f6;margin:0;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" class="email-container" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;box-shadow:0 4px 24px rgba(7,32,25,0.08);">
                <tr>
                    <td style="background-color:#072019;background-image:linear-gradient(135deg,#072019 0%,#065f46 100%);padding:28px 32px;" class="email-padding">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td width="72" valign="middle" style="padding-right:16px;">
                                    <img src="{{ $logoUrl }}" alt="BACS Construction and Development Corporation" width="64" height="64" style="display:block;border:0;outline:none;text-decoration:none;max-width:64px;height:auto;">
                                </td>
                                <td valign="middle">
                                    <p style="margin:0;font-size:11px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#a7f3d0;">{{ $systemName }}</p>
                                    <h1 style="margin:6px 0 0;font-size:18px;line-height:1.35;font-weight:700;color:#ffffff;">{{ $companyName }}</h1>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px 32px 8px;" class="email-padding">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 32px 32px;" class="email-padding">
                        @hasSection('cta')
                            @yield('cta')
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="background-color:#f9fafb;border-top:1px solid #e5e7eb;padding:24px 32px;" class="email-padding">
                        <p style="margin:0 0 8px;font-size:13px;line-height:1.5;color:#374151;font-weight:600;">{{ $companyName }}</p>
                        <p style="margin:0 0 8px;font-size:12px;line-height:1.6;color:#6b7280;">This is an automated notification from the BACS Management System. Please do not reply to this message.</p>
                        <p style="margin:0 0 8px;font-size:12px;line-height:1.6;color:#6b7280;">This email may contain confidential information intended only for the recipient. If you received this in error, please delete it and notify your administrator.</p>
                        <p style="margin:0;font-size:11px;line-height:1.5;color:#9ca3af;">&copy; {{ $year }} {{ $companyName }}. All rights reserved.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
