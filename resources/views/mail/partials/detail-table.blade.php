<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:20px 0 0;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
    @foreach ($rows as $row)
        <tr>
            <td style="padding:12px 16px;background-color:#f9fafb;border-bottom:1px solid #e5e7eb;width:38%;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;vertical-align:top;">
                {{ $row['label'] }}
            </td>
            <td style="padding:12px 16px;border-bottom:1px solid #e5e7eb;font-size:14px;line-height:1.5;color:#111827;vertical-align:top;">
                {{ $row['value'] }}
            </td>
        </tr>
    @endforeach
</table>
