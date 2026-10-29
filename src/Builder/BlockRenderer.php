<?php

namespace ErnestDefoe\Herald\Builder;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builder blocks to email: HTML an inbox can show, and the plain-text part.
 *
 * Email HTML is its own dialect: layout tables, inline styles, a 600px
 * column, buttons that are a link inside a coloured cell, images with a
 * width and alt text. Every colour is set on both the text and what is
 * behind it, so a client's dark mode inverts the pair and never leaves dark
 * text on a dark background.
 *
 * Quick tags pass through untouched — they are filled per recipient later.
 */
class BlockRenderer
{
    private const TEXT = '#333333';
    private const HEADING = '#111111';
    private const MUTED = '#6b7280';
    private const FONT = '-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif';

    public function __construct(
        private Markup $markup,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param array<string, list<array<string, mixed>>> $lists discussions per block id
     */
    public function html(array $blocks, array $lists, string $accent, string $base, bool $preview = false): string
    {
        $rows = '';

        foreach ($blocks as $block) {
            $inner = $this->block($block, $lists[$block['id']] ?? [], $accent, $base, $preview);

            if ($inner === '') {
                continue;
            }

            $style = $block['style'];
            $bg = $style['background'] ?: '#ffffff';
            $class = $style['hideOnMobile'] ? ' class="herald-hide-mobile"' : '';

            $rows .= '<tr'.$class.'><td bgcolor="'.$bg.'" style="padding:'.(int) $style['padding'].'px 24px;background-color:'.$bg.';">'.$inner.'</td></tr>';
        }

        // Media queries cannot be inlined; clients that ignore them show the
        // desktop layout, which still fits because every width is fluid.
        return '<style>@media only screen and (max-width:620px){.herald-hide-mobile{display:none!important;}.herald-stack{display:block!important;width:100%!important;padding:0 0 12px 0!important;}}</style>'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="max-width:600px;margin:0 auto;background-color:#ffffff;font-family:'.self::FONT.';color:'.self::TEXT.';">'
            .$rows.'</table>';
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param array<string, list<array<string, mixed>>> $lists
     */
    public function text(array $blocks, array $lists, string $base): string
    {
        $parts = [];

        foreach ($blocks as $block) {
            $d = $block['data'];

            $part = match ($block['type']) {
                'hero' => $this->lines([$d['title'] ? mb_strtoupper($d['title']) : '', $this->markup->text($d['text'], $base), $this->linkText($d['label'], $d['url'], $base)]),
                'text', 'signoff' => $this->lines([$this->markup->text($d['text'], $base), $d['name'] ?? '']),
                'image' => $d['alt'] !== '' && $d['link'] !== '' ? $d['alt'].' ('.$this->markup->absolute($d['link'], $base).')' : '',
                'imageText' => $this->lines([$d['title'], $this->markup->text($d['text'], $base), $d['link'] !== '' ? $this->markup->absolute($d['link'], $base) : '']),
                'button' => $this->linkText($d['label'], $d['url'], $base),
                'buttons' => $this->lines(array_map(fn ($i) => $this->linkText($i['label'], $i['url'], $base), $d['items'])),
                'divider' => '----------',
                'social' => $this->lines(array_map(fn ($i) => $this->network($i['network']).': '.$this->markup->absolute($i['url'], $base), array_filter($d['items'], fn ($i) => $i['url'] !== ''))),
                'discussions' => $this->discussionsText($d, $lists[$block['id']] ?? []),
                default => '',
            };

            if (trim($part) !== '') {
                $parts[] = trim($part);
            }
        }

        return implode("\n\n", $parts)."\n";
    }

    private function block(array $block, array $list, string $accent, string $base, bool $preview): string
    {
        $d = $block['data'];
        $p = 'margin:0 0 12px;font-size:16px;line-height:1.6;color:'.self::TEXT.';';

        return match ($block['type']) {
            'hero' => $this->hero($d, $accent, $base, $p),
            'text' => $this->markup->html($d['text'], $p.'text-align:'.$d['align'].';', $accent, $base),
            'image' => $this->image($d, $base, (int) round(552 * $d['width'] / 100), 'center'),
            'imageText' => $this->imageText($d, $accent, $base, $p),
            'button' => $this->buttons([['label' => $d['label'], 'url' => $d['url']]], $d['align'], $d['color'] ?: $accent, $base),
            'buttons' => $this->buttons($d['items'], $d['align'], $d['color'] ?: $accent, $base),
            'divider' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-top:'.(int) $d['thickness'].'px solid '.($d['color'] ?: '#e5e7eb').';font-size:0;line-height:0;">&nbsp;</td></tr></table>',
            'spacer' => '<div style="height:'.(int) $d['height'].'px;line-height:'.(int) $d['height'].'px;font-size:0;">&nbsp;</div>',
            'social' => $this->social($d, $accent, $base),
            'discussions' => $this->discussions($d, $list, $accent, $preview),
            'signoff' => $this->markup->html($d['text'], $p, $accent, $base)
                .($d['name'] !== '' ? '<p style="margin:0;font-size:16px;font-weight:600;color:'.self::HEADING.';">'.$this->e($d['name']).'</p>' : ''),
            default => '',
        };
    }

    private function hero(array $d, string $accent, string $base, string $p): string
    {
        $align = $d['align'];
        $html = $d['image'] !== '' ? $this->image(['src' => $d['image'], 'alt' => $d['alt'], 'link' => ''], $base, 552, $align).'<div style="height:16px;line-height:16px;font-size:0;">&nbsp;</div>' : '';

        if ($d['title'] !== '') {
            $html .= '<h1 style="margin:0 0 12px;font-size:28px;line-height:1.25;font-weight:700;color:'.self::HEADING.';text-align:'.$align.';">'.$this->e($d['title']).'</h1>';
        }

        $html .= $this->markup->html($d['text'], $p.'font-size:17px;text-align:'.$align.';', $accent, $base);

        return $html.$this->buttons([['label' => $d['label'], 'url' => $d['url']]], $align, $d['color'] ?: $accent, $base);
    }

    private function image(array $d, string $base, int $width, string $align): string
    {
        if ($d['src'] === '') {
            return '';
        }

        $img = '<img src="'.$this->e($d['src']).'" alt="'.$this->e($d['alt']).'" width="'.$width.'" style="display:block;width:100%;max-width:'.$width.'px;height:auto;border:0;outline:none;text-decoration:none;'.($align === 'center' ? 'margin:0 auto;' : '').'">';

        return ($d['link'] ?? '') !== '' ? '<a href="'.$this->e($this->markup->absolute($d['link'], $base)).'" style="text-decoration:none;">'.$img.'</a>' : $img;
    }

    private function imageText(array $d, string $accent, string $base, string $p): string
    {
        $image = '<td class="herald-stack" width="40%" valign="top" style="width:40%;padding:0 '.($d['side'] === 'left' ? '16px 0 0' : '0 0 16px').';">'.$this->image($d, $base, 210, 'left').'</td>';
        $text = '<td class="herald-stack" valign="top">'
            .($d['title'] !== '' ? '<h3 style="margin:0 0 8px;font-size:19px;line-height:1.3;color:'.self::HEADING.';">'.$this->e($d['title']).'</h3>' : '')
            .$this->markup->html($d['text'], $p, $accent, $base).'</td>';

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
            .($d['src'] === '' ? $text : ($d['side'] === 'left' ? $image.$text : $text.$image)).'</tr></table>';
    }

    /**
     * Bulletproof buttons: the link sits in a coloured cell, so the colour
     * survives clients that strip a link's own background.
     */
    private function buttons(array $items, string $align, string $colour, string $base): string
    {
        $cells = '';

        foreach ($items as $i => $item) {
            if ($item['label'] === '' || $item['url'] === '') {
                continue;
            }

            $primary = $i === 0;
            $href = $this->e($this->markup->absolute($item['url'], $base));
            $cells .= '<td style="padding:4px;"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
                .'<td bgcolor="'.($primary ? $colour : '#ffffff').'" style="border-radius:6px;background-color:'.($primary ? $colour : '#ffffff').';border:2px solid '.$colour.';">'
                .'<a href="'.$href.'" style="display:inline-block;padding:11px 22px;font-size:16px;font-weight:600;line-height:1.2;text-decoration:none;border-radius:6px;color:'.($primary ? '#ffffff' : $colour).';">'.$this->e($item['label']).'</a>'
                .'</td></tr></table></td>';
        }

        return $cells === '' ? '' : '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="'.$align.'" style="margin:4px '.($align === 'center' ? 'auto' : '0').';"><tr>'.$cells.'</tr></table>';
    }

    private function social(array $d, string $accent, string $base): string
    {
        $links = [];

        foreach ($d['items'] as $item) {
            if ($item['url'] !== '') {
                $links[] = '<a href="'.$this->e($this->markup->absolute($item['url'], $base)).'" style="color:'.$accent.';font-weight:600;text-decoration:none;">'.$this->e($this->network($item['network'])).'</a>';
            }
        }

        return $links ? '<p style="margin:0;font-size:15px;line-height:1.8;color:'.self::MUTED.';text-align:'.$d['align'].';">'.implode(' &nbsp;&middot;&nbsp; ', $links).'</p>' : '';
    }

    private function discussions(array $d, array $list, string $accent, bool $preview): string
    {
        $heading = $d['heading'] !== '' ? '<h2 style="margin:0 0 12px;font-size:21px;line-height:1.3;color:'.self::HEADING.';">'.$this->e($d['heading']).'</h2>' : '';

        if (! $list) {
            // Nothing to show in the email; the preview says why the block is blank.
            return $preview ? $heading.'<p style="margin:0;font-size:14px;color:'.self::MUTED.';font-style:italic;">'.$this->e($this->trans('discussions_empty')).'</p>' : '';
        }

        return $heading.$this->discussionList($list, $d['layout'] ?? 'compact', $accent);
    }

    /**
     * The rows themselves — shared with the {latest_discussions} quick tag.
     *
     * @param list<array<string, mixed>> $list
     */
    public function discussionList(array $list, string $layout, string $accent): string
    {
        $rows = '';

        foreach ($list as $i => $row) {
            $meta = $this->meta($row);
            $border = $i === 0 ? '' : 'border-top:1px solid #e5e7eb;';

            $rows .= '<tr><td style="padding:10px 0;'.$border.'">'
                .'<a href="'.$this->e($row['url']).'" style="font-size:16px;font-weight:600;line-height:1.35;color:'.$accent.';text-decoration:none;">'.$this->e($row['title']).'</a>'
                .($layout === 'excerpt' && $row['excerpt'] !== '' ? '<p style="margin:4px 0 0;font-size:14px;line-height:1.5;color:'.self::TEXT.';">'.$this->e($row['excerpt']).'</p>' : '')
                .'<p style="margin:4px 0 0;font-size:13px;line-height:1.4;color:'.self::MUTED.';">'.$this->e($meta).'</p>'
                .'</td></tr>';
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'.$rows.'</table>';
    }

    /**
     * @param list<array<string, mixed>> $list
     */
    public function discussionListText(array $list): string
    {
        return $this->lines(array_map(fn ($row) => '- '.$row['title']."\n  ".$row['url']."\n  ".$this->meta($row), $list));
    }

    private function discussionsText(array $d, array $list): string
    {
        return $list ? $this->lines([$d['heading'] !== '' ? mb_strtoupper($d['heading']) : '', $this->discussionListText($list)]) : '';
    }

    private function meta(array $row): string
    {
        $replies = $this->trans('discussions_replies', ['count' => $row['replies']]);

        return $row['author'] !== '' ? $replies.' · '.$this->trans('discussions_by', ['name' => $row['author']]) : $replies;
    }

    private function linkText(string $label, string $url, string $base): string
    {
        return $label !== '' && $url !== '' ? $label.': '.$this->markup->absolute($url, $base) : '';
    }

    private function network(string $network): string
    {
        return $this->translator->trans('ernestdefoe-herald.forum.builder.networks.'.$network);
    }

    private function lines(array $lines): string
    {
        return implode("\n", array_filter($lines, fn ($l) => trim((string) $l) !== ''));
    }

    private function trans(string $key, array $params = []): string
    {
        return $this->translator->trans('ernestdefoe-herald.email.'.$key, $params);
    }

    private function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
