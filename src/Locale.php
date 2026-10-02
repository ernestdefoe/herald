<?php

namespace ErnestDefoe\Herald;

use Flarum\Locale\LocaleManager;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

class Locale
{
    public function __construct(
        private SettingsRepositoryInterface $settings,
        private LocaleManager $locales,
    ) {
    }

    public function default(): string
    {
        return (string) ($this->settings->get('default_locale') ?: 'en');
    }

    /**
     * The language a member reads the forum in, falling back to the forum's
     * own when they never chose one or chose a pack that has since been
     * removed.
     */
    public function for(User $user): string
    {
        $locale = $user->getPreference('locale');

        return $locale && $this->locales->hasLocale($locale) ? $locale : $this->default();
    }
}
