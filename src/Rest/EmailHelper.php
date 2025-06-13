<?php

namespace EAD\Helpers;

class EmailHelper {

    public static function send_email($to, $subject, $message, $attachments = [], $dynamic_tags = []) {
        $provider = \EAD\Admin\SettingsPage::get_setting( 'email_default_provider', 'wp_mail' );

        // Replace dynamic tags
        foreach ($dynamic_tags as $tag => $value) {
            $message = str_replace("{{{$tag}}}", esc_html($value), $message);
        }

        if ($provider === 'sendgrid') {
            return self::send_with_sendgrid($to, $subject, $message, $attachments);
        } elseif ($provider === 'mailgun') {
            return self::send_with_mailgun($to, $subject, $message, $attachments);
        } else {
            return self::send_with_wp_mail($to, $subject, $message, $attachments);
        }
    }

    private static function send_with_wp_mail($to, $subject, $message, $attachments = []) {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        return wp_mail($to, $subject, $message, $headers, $attachments);
    }

    private static function send_with_sendgrid($to, $subject, $message, $attachments = []) {
        // Placeholder logic — integrate SendGrid SDK here
        // Log sending attempt for demonstration
        error_log(\"[SendGrid] Sending email to $to with subject '$subject'\");
        return true;
    }

    private static function send_with_mailgun($to, $subject, $message, $attachments = []) {
        // Placeholder logic — integrate Mailgun SDK here
        // Log sending attempt for demonstration
        error_log(\"[Mailgun] Sending email to $to with subject '$subject'\");
        return true;
    }
}
