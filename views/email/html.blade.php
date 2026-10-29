{{--
  Herald's email, inside Flarum's own email layout so it carries the forum's
  logo and looks like every other message the forum sends.

  {unsubscribe_url} and {settings_url} are quick tags, filled per recipient
  after this is rendered once per language. Blade leaves single braces alone.

  A builder mailing brings its own 600px layout table and inline styles, so
  it is wrapped in .herald-built, which content.css leaves alone; an editor
  mailing is a post's HTML and gets .herald-body's typography.
--}}
<x-mail::html :title="$subject" :greeting="false" :signoff="false">
    <x-slot:content>
        @if ($preheader !== '')
            {{-- The line inboxes show after the subject. Hidden in the body, then padded so the client does not fill the rest of the line with the body's first words. --}}
            <div style="display:none;max-height:0;max-width:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;opacity:0;">{{ $preheader }}{!! str_repeat('&#8199;&#65279;&#847; ', 40) !!}</div>
        @endif
        <div class="{{ $built ? 'herald-built' : 'herald-body' }}">{!! $body !!}</div>
    </x-slot:content>

    <x-slot:footer>
        <p>{{ $translator->trans($guest ? 'ernestdefoe-herald.email.why_guest' : 'ernestdefoe-herald.email.why', ['forumTitle' => $forumTitle]) }}</p>
        <p>
            <a href="{unsubscribe_url}">{{ $translator->trans('ernestdefoe-herald.email.unsubscribe') }}</a>
            @unless ($guest)
                &nbsp;&middot;&nbsp;
                <a href="{settings_url}">{{ $translator->trans('ernestdefoe-herald.email.how_to_opt_out') }}</a>
            @endunless
        </p>
    </x-slot:footer>
</x-mail::html>
