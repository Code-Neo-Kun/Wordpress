<?php

/**
 * Email Handler Utility
 */

namespace LicenseServer\Utilities;

class Email_Handler
{

    public static function send_license_issued($license_id, $user_email)
    {
        $license = new \LicenseServer\Models\License($license_id);

        $subject = 'Your New License Has Been Issued';
        $message = self::get_license_issued_template($license, $user_email);

        wp_mail($user_email, $subject, $message, self::get_headers());
    }

    public static function send_license_expiring_soon($license_id, $user_email)
    {
        $license = new \LicenseServer\Models\License($license_id);

        $subject = 'Your License is Expiring Soon';
        $message = self::get_license_expiring_template($license, $user_email);

        wp_mail($user_email, $subject, $message, self::get_headers());
    }

    public static function send_license_expired($license_id, $user_email)
    {
        $license = new \LicenseServer\Models\License($license_id);

        $subject = 'Your License Has Expired';
        $message = self::get_license_expired_template($license, $user_email);

        wp_mail($user_email, $subject, $message, self::get_headers());
    }

    private static function get_license_issued_template($license, $email)
    {
        $license_key = $license->get_license_key();
        $expires_at = $license->get_expires_at();
        $plugins = implode(', ', $license->get_plugins());

        return sprintf(
            'Hello,

Your license has been issued successfully!

License Key: %s
Plan Type: %s
Plugins: %s
Expires: %s

Dashboard: %s

Keep your license key safe and secure. Do not share it with others.

Best regards,
License Server Team',
            $license_key,
            ucfirst(str_replace('_', ' ', $license->get_plan_type())),
            $plugins ?: 'None assigned yet',
            $expires_at ? date_i18n('F j, Y', strtotime($expires_at)) : 'Lifetime',
            wp_login_url()
        );
    }

    private static function get_license_expiring_template($license, $email)
    {
        $expires_at = $license->get_expires_at();
        $days_left = ceil((strtotime($expires_at) - current_time('timestamp')) / DAY_IN_SECONDS);

        return sprintf(
            'Hello,

Your license will expire in %d days.

Expires: %s

Renew now to avoid any interruption to your service.

Dashboard: %s

Best regards,
License Server Team',
            $days_left,
            date_i18n('F j, Y', strtotime($expires_at)),
            wp_login_url()
        );
    }

    private static function get_license_expired_template($license, $email)
    {
        return sprintf(
            'Hello,

Your license has expired on %s.

To continue using licensed features, please renew your license.

Dashboard: %s

Best regards,
License Server Team',
            date_i18n('F j, Y', strtotime($license->get_expires_at())),
            wp_login_url()
        );
    }

    private static function get_headers()
    {
        return [
            'Content-Type: text/plain; charset=UTF-8',
        ];
    }
}
