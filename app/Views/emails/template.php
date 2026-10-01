<?php if (!defined('ABSPATH')) { exit; } ?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin: 0; padding: 0; background-color: #f0f3f5;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0f3f5;">
        <tr>
            <td align="center" style="padding: 24px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border: 1px solid #d3d8de; border-radius: 8px;">
                    <tr>
                        <td style="padding: 32px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.6; color: #19283a;">
                            <?php echo wp_kses_post($email_body); ?>
                        </td>
                    </tr>
                    <?php if (!empty($email_footer)) : ?>
                        <tr>
                            <td style="padding: 16px 32px; border-top: 1px solid #e5e9ec; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 1.5; color: #525866;">
                                <?php echo wp_kses_post($email_footer); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
