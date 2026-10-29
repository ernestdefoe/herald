<?php

namespace ErnestDefoe\Herald\Builder;

use Flarum\Foundation\ValidationException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The allow-list every builder mailing is saved through.
 *
 * Only these block types, only these fields, each coerced to its kind:
 * lengths cut, numbers clamped, colours checked, addresses through Url. Text
 * is Markup, never HTML. Whatever else the browser sends is dropped, so what
 * is stored is something the renderer can trust.
 */
class Blocks
{
    public const MAX_BLOCKS = 60;

    public const NETWORKS = ['website', 'facebook', 'x', 'instagram', 'youtube', 'tiktok', 'discord', 'mastodon', 'bluesky', 'threads', 'linkedin', 'github', 'twitch', 'reddit'];

    /**
     * text:N   plain text, at most N characters
     * markup:N Markup text, at most N characters
     * int:min:max:default
     * enum:a,b first is the default
     */
    public const FIELDS = [
        'hero' => ['title' => 'text:200', 'text' => 'markup:800', 'image' => 'image', 'alt' => 'text:200', 'label' => 'text:60', 'url' => 'url', 'align' => 'enum:center,left', 'color' => 'color'],
        'text' => ['text' => 'markup:8000', 'align' => 'enum:left,center'],
        'image' => ['src' => 'image', 'alt' => 'text:200', 'link' => 'url', 'width' => 'int:10:100:100'],
        'imageText' => ['src' => 'image', 'alt' => 'text:200', 'link' => 'url', 'title' => 'text:200', 'text' => 'markup:3000', 'side' => 'enum:left,right'],
        'button' => ['label' => 'text:60', 'url' => 'url', 'align' => 'enum:center,left,right', 'color' => 'color'],
        'buttons' => ['items' => 'links:4', 'align' => 'enum:center,left,right', 'color' => 'color'],
        'divider' => ['color' => 'color', 'thickness' => 'int:1:4:1'],
        'spacer' => ['height' => 'int:8:96:24'],
        'social' => ['items' => 'social:14', 'align' => 'enum:center,left,right'],
        'discussions' => ['heading' => 'text:120', 'tags' => 'ids:30', 'children' => 'bool', 'sort' => 'enum:newest,active,trending', 'limit' => 'int:1:10:5', 'layout' => 'enum:compact,excerpt', 'skipEarlier' => 'bool'],
        'signoff' => ['text' => 'markup:1000', 'name' => 'text:120'],
    ];

    public const STYLE = ['padding' => 'int:0:64:16', 'background' => 'color', 'hideOnMobile' => 'bool'];

    public function __construct(
        private Url $url,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    public function normalise(mixed $blocks): array
    {
        if (! is_array($blocks) || ! array_is_list($blocks) || count($blocks) > self::MAX_BLOCKS) {
            throw $this->invalid();
        }

        $out = [];
        $ids = [];

        foreach ($blocks as $block) {
            $type = is_array($block) ? ($block['type'] ?? null) : null;

            if (! is_string($type) || ! isset(self::FIELDS[$type])) {
                throw $this->invalid();
            }

            $id = is_string($block['id'] ?? null) && preg_match('/^[a-z0-9-]{1,24}$/', $block['id']) ? $block['id'] : '';

            // Ids key the frozen discussion lists; each must be unique.
            if ($id === '' || isset($ids[$id])) {
                $id = 'b'.bin2hex(random_bytes(4));
            }

            $ids[$id] = true;
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $style = is_array($block['style'] ?? null) ? $block['style'] : [];

            $out[] = [
                'id' => $id,
                'type' => $type,
                'data' => $this->fields(self::FIELDS[$type], $data),
                'style' => $this->fields(self::STYLE, $style),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, string> $spec
     */
    private function fields(array $spec, array $data): array
    {
        $out = [];

        foreach ($spec as $key => $kind) {
            $out[$key] = $this->coerce($kind, $data[$key] ?? null);
        }

        return $out;
    }

    private function coerce(string $kind, mixed $value): mixed
    {
        $parts = explode(':', $kind);

        return match ($parts[0]) {
            'text' => $this->text($value, (int) $parts[1], false),
            'markup' => $this->text($value, (int) $parts[1], true),
            'int' => is_numeric($value) ? max((int) $parts[1], min((int) $parts[2], (int) $value)) : (int) $parts[3],
            'enum' => in_array($value, $options = explode(',', $parts[1]), true) ? $value : $options[0],
            'bool' => (bool) $value,
            'color' => is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : '',
            'url' => $this->url->link($value),
            'image' => $this->url->image($value),
            'ids' => array_slice(array_values(array_unique(array_filter(array_map('intval', is_array($value) ? $value : [])))), 0, (int) $parts[1]),
            'links' => $this->items($value, (int) $parts[1], fn ($item) => [
                'label' => $this->text($item['label'] ?? '', 60, false),
                'url' => $this->url->link($item['url'] ?? ''),
            ]),
            'social' => $this->items($value, (int) $parts[1], fn ($item) => in_array($item['network'] ?? null, self::NETWORKS, true)
                ? ['network' => $item['network'], 'url' => $this->url->link($item['url'] ?? '')]
                : null),
            default => null,
        };
    }

    private function items(mixed $value, int $max, callable $map): array
    {
        $items = [];

        foreach (is_array($value) ? array_slice(array_values($value), 0, $max) : [] as $item) {
            if (is_array($item) && ($mapped = $map($item)) !== null) {
                $items[] = $mapped;
            }
        }

        return $items;
    }

    private function text(mixed $value, int $max, bool $multiline): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        // Control characters out; a multi-line field keeps its line breaks.
        $value = (string) preg_replace($multiline ? '/[\x00-\x09\x0B-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', $multiline ? '' : ' ', (string) $value);

        return mb_substr(trim($value), 0, $max);
    }

    private function invalid(): ValidationException
    {
        return new ValidationException(['blocks' => $this->translator->trans('ernestdefoe-herald.forum.errors.blocks_invalid')]);
    }
}
