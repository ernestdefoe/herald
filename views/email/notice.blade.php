{{-- A one-off message about the newsletter: a confirmation link, or a note to a member. --}}
<x-mail::html :title="$title" :greeting="false" :signoff="false">
    <x-slot:content>
        <p style="font-size:15px;line-height:1.6;color:#333;">{{ $text }}</p>
        <p style="margin:24px 0;">
            <a href="{{ $link }}" style="display:inline-block;padding:11px 22px;border-radius:6px;background:{{ $accent }};color:#ffffff;font-weight:600;text-decoration:none;">{{ $button }}</a>
        </p>
    </x-slot:content>

    <x-slot:footer>
        <p>{{ $footer }}</p>
    </x-slot:footer>
</x-mail::html>
