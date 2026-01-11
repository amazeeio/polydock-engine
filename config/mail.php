<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    'mjml-config' => [
        /*
    |--------------------------------------------------------------------------
    | Default Email Theme
    |--------------------------------------------------------------------------
    |
    | This option controls the default theme used for emails when no specific
    | theme is specified by the mailable class. Available options: 'light', 'dark'
    |
    */

        'default_theme' => env('EMAIL_DEFAULT_THEME', 'dark'),

        /*
    |--------------------------------------------------------------------------
    | Email Themes
    |--------------------------------------------------------------------------
    |
    | Define the visual themes available for email templates. Each theme
    | specifies colors for various email components.
    |
    */

        'themes' => [
            'light' => [
                'name' => 'Light Lagoon',
                'colors' => [
                    'body_background' => '#f0f4f8',
                    'content_background' => '#ffffff',
                    'header_background' => '#ffffff',
                    'footer_background' => '#263238',
                    'text' => '#1a202c',
                    'text_muted' => '#718096',
                    'links' => '#0891b2',
                    'primary_button_background' => '#000000',
                    'primary_button_text' => '#ffffff',
                ],
                'typography' => [
                    'font_family' => 'sans-serif',
                    'heading_color' => '#1a202c',
                    'body_font_size' => '16px',
                    'heading_font_size' => '24px',
                ],
                'logo' => [
                    'url' => '/assets/emails/amazee-logo-light.svg',
                    'alt' => 'Amazee Logo',
                    'width' => '150',
                ],
            ],

            'dark' => [
                'name' => 'Dark Header',
                'colors' => [
                    'body_background' => '#e5e7eb',
                    'content_background' => '#ffffff',
                    'header_background' => '#1f2937',
                    'footer_background' => '#111827',
                    'text' => '#1f2937',
                    'text_muted' => '#6b7280',
                    'links' => '#2563eb',
                    'primary_button_background' => '#000000',
                    'primary_button_text' => '#ffffff',
                ],
                'typography' => [
                    'font_family' => 'sans-serif',
                    'heading_color' => '#111827',
                    'body_font_size' => '16px',
                    'heading_font_size' => '24px',
                ],
                'logo' => [
                    'url' => '/assets/emails/amazee-logo-dark.svg',
                    'alt' => 'Amazee Logo',
                    'width' => '150',
                ],
            ],
        ],

        /*
    |--------------------------------------------------------------------------
    | Logo Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the logo displayed in email headers. The URL can be overridden
    | via environment variables to allow easy customization per environment.
    |
    */

        'logo' => [
            'url' => env('EMAIL_LOGO_URL', '/assets/emails/logo.svg'),
            'alt' => env('EMAIL_LOGO_ALT', 'Company Logo'),
            'width' => env('EMAIL_LOGO_WIDTH', '150'),
            'height' => env('EMAIL_LOGO_HEIGHT', 'auto'),
        ],

        /*
    |--------------------------------------------------------------------------
    | Footer Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the footer content displayed in all emails. This includes
    | company information, links, and legal text.
    |
    */

        'footer' => [
            'company_name' => env('EMAIL_FOOTER_COMPANY_NAME', 'amazee.io'),

            'address' => env('EMAIL_FOOTER_ADDRESS', 'amazee.io, Hardturmstrasse 161, 8005 Zurich, Switzerland.'),

            'links' => [
                'unsubscribe' => [
                    'url' => env('EMAIL_UNSUBSCRIBE_URL', '/unsubscribe'),
                    'text' => 'Unsubscribe',
                ],
                'support' => [
                    'url' => env('EMAIL_SUPPORT_URL', 'https://www.amazee.io/product/support'),
                    'text' => 'Contact Support',
                    'enabled' => env('EMAIL_SUPPORT_LINK_ENABLED', true),
                ],
                'privacy' => [
                    'url' => env('EMAIL_PRIVACY_URL', 'https://www.amazee.io/privacy-policy'),
                    'text' => 'Privacy Policy',
                    'enabled' => env('EMAIL_PRIVACY_LINK_ENABLED', true),
                ],
                'terms' => [
                    'url' => env('EMAIL_TERMS_URL', ''),
                    'text' => 'Terms of Service',
                    'enabled' => env('EMAIL_TERMS_LINK_ENABLED', false),
                ],
            ],

            'copyright_text' => env('EMAIL_FOOTER_COPYRIGHT', sprintf('© %s amazee.io. All rights reserved.', date('Y'))),

            'disclaimer' => env('EMAIL_FOOTER_DISCLAIMER', 'This email was sent to you because you have an account with us. Please do not reply to this email.'),
        ],
    ],

];
