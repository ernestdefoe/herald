<?php

namespace ErnestDefoe\Herald\Mail;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Turns a post's rendered HTML into something an inbox can show.
 *
 * A post renders for a browser on the forum: relative links, embeds that are
 * iframes, scripts that decorate it after load. None of that survives an
 * email client, and most of it fails silently — a relative link opens
 * nothing, an iframe is simply not there.
 */
class EmailHtml
{
    private const DROP = ['script', 'style', 'noscript', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea'];

    private const LINKABLE = ['iframe', 'video', 'audio'];

    public function prepare(string $html, string $baseUrl): string
    {
        if (trim($html) === '') {
            return '';
        }

        $baseUrl = rtrim($baseUrl, '/');

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="herald-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);

        foreach (self::DROP as $tag) {
            foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // An embed becomes a plain link to what it was showing.
        foreach (self::LINKABLE as $tag) {
            foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $node) {
                /** @var DOMElement $node */
                $src = $node->getAttribute('src') ?: ($xpath->query('.//source/@src', $node)->item(0)?->nodeValue ?? '');
                $src = $this->absolute($src, $baseUrl);

                if ($src === '') {
                    $node->parentNode?->removeChild($node);
                    continue;
                }

                $link = $doc->createElement('a');
                $link->setAttribute('href', $src);
                $link->appendChild($doc->createTextNode($src));
                $p = $doc->createElement('p');
                $p->appendChild($link);
                $node->parentNode?->replaceChild($p, $node);
            }
        }

        foreach ($xpath->query('//*[@href] | //*[@src] | //*[@srcset]') as $node) {
            /** @var DOMElement $node */
            foreach (['href', 'src'] as $attr) {
                if ($node->hasAttribute($attr)) {
                    $node->setAttribute($attr, $this->absolute($node->getAttribute($attr), $baseUrl));
                }
            }

            // srcset is a list of relative URLs nobody can resolve from an
            // inbox, and src already says what to show.
            $node->removeAttribute('srcset');
            $node->removeAttribute('loading');
        }

        // Event handlers and data attributes mean nothing outside the forum.
        foreach ($xpath->query('//@*') as $attr) {
            $name = strtolower($attr->nodeName);

            if (str_starts_with($name, 'on') || str_starts_with($name, 'data-')) {
                $attr->ownerElement?->removeAttribute($attr->nodeName);
            }
        }

        $root = $doc->getElementById('herald-root');
        $out = '';

        foreach ($root?->childNodes ?? [] as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private function absolute(string $url, string $baseUrl): string
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '#')) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        if (str_starts_with($url, '/')) {
            return $baseUrl.$url;
        }

        return $url;
    }
}
