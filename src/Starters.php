<?php

namespace ErnestDefoe\Herald;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The two builder templates every forum starts with: "Weekly digest" and
 * "Announcement".
 *
 * Created the first time anyone opens Templates, not in a migration, so they
 * are written in the forum's own language with the forum's own name. Once,
 * ever: an admin who deletes them does not get them back.
 */
class Starters
{
    public function __construct(
        private SettingsRepositoryInterface $settings,
        private TranslatorInterface $translator,
    ) {
    }

    public function ensure(?User $actor = null): void
    {
        if ($this->settings->get(Settings::STARTERS)) {
            return;
        }

        $this->settings->set(Settings::STARTERS, '1');

        foreach ($this->templates() as $template) {
            $mailing = new Mailing();
            $mailing->subject = $template['subject'];
            $mailing->preheader = $template['preheader'];
            $mailing->type = Mailing::BUILDER;
            $mailing->blocks = $template['blocks'];
            $mailing->filters = [];
            $mailing->status = Mailing::DRAFT;
            $mailing->is_template = true;
            $mailing->created_by = $actor?->id;
            $mailing->save();
        }
    }

    private function templates(): array
    {
        $t = fn (string $key) => $this->translator->trans('ernestdefoe-herald.starters.'.$key);
        $style = ['padding' => 16, 'background' => '', 'hideOnMobile' => false];
        $block = fn (string $id, string $type, array $data, array $over = []) => ['id' => $id, 'type' => $type, 'data' => $data, 'style' => $over + $style];

        return [
            [
                'subject' => $t('digest_subject'),
                'preheader' => $t('digest_preheader'),
                'blocks' => [
                    $block('hero', 'hero', ['title' => $t('digest_title'), 'text' => $t('digest_text'), 'image' => '', 'alt' => '', 'label' => '', 'url' => '', 'align' => 'center', 'color' => ''], ['padding' => 32]),
                    $block('list', 'discussions', ['heading' => $t('digest_heading'), 'tags' => [], 'children' => false, 'sort' => 'trending', 'limit' => 5, 'layout' => 'excerpt', 'skipEarlier' => false]),
                    $block('new', 'discussions', ['heading' => $t('digest_new_heading'), 'tags' => [], 'children' => false, 'sort' => 'newest', 'limit' => 5, 'layout' => 'compact', 'skipEarlier' => true]),
                    $block('cta', 'button', ['label' => $t('digest_button'), 'url' => '{suite_url}', 'align' => 'center', 'color' => '']),
                    $block('bye', 'signoff', ['text' => $t('digest_signoff'), 'name' => $t('team')], ['padding' => 24]),
                ],
            ],
            [
                'subject' => $t('announcement_subject'),
                'preheader' => '',
                'blocks' => [
                    $block('hero', 'hero', ['title' => $t('announcement_title'), 'text' => '', 'image' => '', 'alt' => '', 'label' => '', 'url' => '', 'align' => 'left', 'color' => ''], ['padding' => 32]),
                    $block('body', 'text', ['text' => $t('announcement_text'), 'align' => 'left']),
                    $block('cta', 'button', ['label' => $t('announcement_button'), 'url' => '{suite_url}', 'align' => 'left', 'color' => '']),
                    $block('bye', 'signoff', ['text' => $t('announcement_signoff'), 'name' => $t('team')], ['padding' => 24]),
                ],
            ],
        ];
    }
}
