{{--
  The small standalone pages behind Herald's email links: unsubscribe,
  resubscribe and confirm a newsletter signup. No forum JavaScript, so they
  work from any mail client and for anyone, signed in or not.
--}}
<!DOCTYPE html>
<html lang="{{ $translator->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $heading }} · {{ $forumTitle }}</title>
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
        <h1>{{ $heading }}</h1>
        <p>{{ $text }}</p>
        @if ($button)
            <form method="post" action="{{ $action }}">
                @if ($formAction)
                    <input type="hidden" name="action" value="{{ $formAction }}">
                @endif
                <button type="submit" @if ($secondary) class="secondary" @endif>{{ $button }}</button>
            </form>
        @endif
        @if ($link)
            <p><a href="{{ $link }}">{{ $linkLabel }}</a></p>
        @endif
    </div>
    <p class="foot"><a href="{{ $forumUrl }}">{{ $forumTitle }}</a></p>
</main>
</body>
</html>
