<x-mail::message>
# You're on the list! 🎉

Hi {{ $attendee->name }},

Thanks for registering your interest. We've confirmed your spot for:

## {{ $card['name'] }}

**When:** {{ $card['time']['local_label'] }} ({{ $card['time']['tz_abbr'] }}, {{ $card['time']['timezone'] }})
**Where:** {{ $card['venue'] }} — {{ $card['location']['label'] }}

@if ($card['description'])
{{ \Illuminate\Support\Str::limit($card['description'], 220) }}
@endif

<x-mail::button :url="config('app.url').'/events/'.$card['id']">
View event details
</x-mail::button>

We'll send you a reminder as the date approaches.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
