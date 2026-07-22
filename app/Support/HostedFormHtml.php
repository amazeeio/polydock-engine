<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitizes admin-authored HTML before it is rendered unescaped on hosted
 * form pages. Only basic formatting tags and safe link attributes survive,
 * so a typo or pasted markup in the admin panel cannot break or script the
 * public page.
 */
class HostedFormHtml
{
    /** Tags an admin may use in description/notice/disclaimer fields. */
    public const ALLOWED_TAGS_HINT = '<a> <p> <br> <strong> <em> <b> <i> <u> <ul> <ol> <li>';

    private static ?HtmlSanitizer $sanitizer = null;

    public static function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        return self::sanitizer()->sanitize($html);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer === null) {
            $config = (new HtmlSanitizerConfig)
                ->allowElement('a', ['href', 'target', 'rel'])
                ->allowElement('p')
                ->allowElement('br')
                ->allowElement('strong')
                ->allowElement('em')
                ->allowElement('b')
                ->allowElement('i')
                ->allowElement('u')
                ->allowElement('ul')
                ->allowElement('ol')
                ->allowElement('li')
                ->allowLinkSchemes(['http', 'https', 'mailto']);

            self::$sanitizer = new HtmlSanitizer($config);
        }

        return self::$sanitizer;
    }
}
