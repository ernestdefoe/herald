@php
    $t = fn ($key, $params = []) => $translator->trans('ernestdefoe-herald.unsubscribe.'.$key, $params);
@endphp
<!DOCTYPE html>
<html lang="{{ $translator->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $t('title') }} · {{ $forumTitle }}</title>
    <style>
        :root { color-scheme: light dark; --bg: #f3f5f7; --card: #fff; --text: #222; --muted: #667; --line: #e1e4e8; --accent: {{ $accent }}; }
        @media (prefers-color-scheme: dark) { :root { --bg: #16181c; --card: #1f2227; --text: #e8e8e8; --muted: #9aa0a6; --line: #30343a; } }
        body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        main { max-width: 460px; margin: 12vh auto 0; padding: 0 16px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 28px; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        p { margin: 0 0 16px; color: var(--muted); }
        button { font: inherit; font-weight: 600; border: 0; border-radius: 6px; padding: 10px 18px; cursor: pointer; background: var(--accent); color: #fff; }
        button.secondary { background: transparent; color: var(--accent); border: 1px solid var(--line); }
        a { color: var(--accent); }
        .foot { margin-top: 16px; font-size: 13px; color: var(--muted); text-align: center; }
    </style>
</head>
<body>
<main>
    <div class="card">
        @if ($state === 'invalid')
            <h1>{{ $t('invalid_title') }}</h1>
            <p>{{ $t('invalid_text') }}</p>
            <p><a href="{{ $settingsUrl }}">{{ $t('manage') }}</a></p>
        @elseif ($state === 'confirm')
            <h1>{{ $t('confirm_title') }}</h1>
            <p>{{ $t('confirm_text', ['forumTitle' => $forumTitle]) }}</p>
            <form method="post" action="{{ $action }}">
                <button type="submit">{{ $t('confirm_button') }}</button>
            </form>
        @elseif ($state === 'unsubscribed')
            <h1>{{ $t('done_title') }}</h1>
            <p>{{ $t('done_text', ['forumTitle' => $forumTitle]) }}</p>
            <form method="post" action="{{ $action }}">
                <input type="hidden" name="action" value="resubscribe">
                <button type="submit" class="secondary">{{ $t('resubscribe_button') }}</button>
            </form>
        @else
            <h1>{{ $t('resubscribed_title') }}</h1>
            <p>{{ $t('resubscribed_text', ['forumTitle' => $forumTitle]) }}</p>
        @endif
    </div>
    <p class="foot"><a href="{{ $forumUrl }}">{{ $forumTitle }}</a></p>
</main>
</body>
</html>
