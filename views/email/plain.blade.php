{!! $bodyText !!}

--
{!! $translator->trans($guest ? 'ernestdefoe-herald.email.why_guest' : 'ernestdefoe-herald.email.why', ['forumTitle' => $forumTitle]) !!}

{!! $translator->trans('ernestdefoe-herald.email.unsubscribe') !!}: {unsubscribe_url}
@unless ($guest)
{!! $translator->trans('ernestdefoe-herald.email.how_to_opt_out') !!}: {settings_url}
@endunless
