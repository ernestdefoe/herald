{{--
  Herald's email, inside Flarum's own email layout so it carries the forum's
  logo and looks like every other message the forum sends.

  {unsubscribe_url} and {settings_url} are quick tags, filled per recipient
  after this is rendered once per language. Blade leaves single braces alone.
--}}
<x-mail::html :title="$subject" :greeting="false" :signoff="false">
    <x-slot:content>
        <div class="herald-body">{!! $body !!}</div>
    </x-slot:content>

    <x-slot:footer>
        <p>{{ $translator->trans('ernestdefoe-herald.email.why', ['forumTitle' => $forumTitle]) }}</p>
        <p>
            <a href="{unsubscribe_url}">{{ $translator->trans('ernestdefoe-herald.email.unsubscribe') }}</a>
            &nbsp;&middot;&nbsp;
            <a href="{settings_url}">{{ $translator->trans('ernestdefoe-herald.email.how_to_opt_out') }}</a>
        </p>
    </x-slot:footer>
</x-mail::html>
