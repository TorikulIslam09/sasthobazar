<?php
declare(strict_types=1);

/**
 * HTML allow-list sanitizer for CMS content (pages, blog posts, FAQ answers).
 * Keeps basic formatting, links, images and tables; drops scripts, styles, event handlers, iframes and
 * javascript:/data: URLs. Links to other sites get rel="noopener nofollow" and open in a new tab.
 */
final class Html
{
    private const TAGS = [
        'p' => [], 'br' => [], 'hr' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'u' => [], 's' => [], 'small' => [], 'mark' => [], 'blockquote' => [], 'ul' => [], 'ol' => [], 'li' => [],
        'a' => ['href', 'title'], 'img' => ['src', 'alt', 'width', 'height'], 'figure' => [], 'figcaption' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'span' => [], 'div' => [], 'code' => [], 'pre' => [],
    ];
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'math', 'noscript', 'template'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('__root') ?? $doc->documentElement;
        if (!$root) {
            return '';
        }
        self::walk($root);
        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);
                continue;
            }
            self::walk($child);
            if (!isset(self::TAGS[$tag])) {
                // unknown tag: keep its children, drop the tag
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, self::TAGS[$tag], true)) {
                    $child->removeAttribute($attr->name);
                    continue;
                }
                if (in_array($name, ['href', 'src'], true) && !self::safeUrl($attr->value, $name === 'src')) {
                    $child->removeAttribute($attr->name);
                }
                if (in_array($name, ['width', 'height', 'colspan', 'rowspan'], true) && !ctype_digit($attr->value)) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a' && preg_match('#^https?://#i', $child->getAttribute('href'))) {
                $child->setAttribute('target', '_blank');
                $child->setAttribute('rel', 'noopener nofollow');
            }
            if ($tag === 'img') {
                if (!$child->hasAttribute('src')) {
                    $node->removeChild($child);
                    continue;
                }
                $child->setAttribute('loading', 'lazy');
            }
        }
    }

    private static function safeUrl(string $url, bool $image): bool
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || preg_match('/[\x00-\x1F]/', $url)) {
            return false;
        }
        if (preg_match('#^(https?:)?//#i', $url)) {
            return true;
        }
        if (!$image && preg_match('#^(mailto|tel):#i', $url)) {
            return true;
        }
        // relative links / site paths, never a scheme such as javascript: or data:
        return !preg_match('#^[a-z][a-z0-9+.-]*:#i', $url);
    }

    /** Plain-text excerpt of HTML. */
    public static function excerpt(?string $html, int $length = 160): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
    }
}
