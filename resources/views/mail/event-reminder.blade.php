<x-mail::message>
# {{ $card['name'] }} is in {{ $window }} ⏰

Hi {{ $attendee->name }},

Just a reminder that you're registered for an event coming up in **{{ $window }}**:

**When:** {{ $card['time']['local_label'] }} ({{ $card['time']['tz_abbr'] }}, {{ $card['time']['timezone'] }})
**Where:** {{ $card['venue'] }} — {{ $card['location']['label'] }}

<x-mail::button :url="config('app.url').'/events/'.$card['id']">
View event details
</x-mail::button>

See you there!<br>
{{ config('app.name') }}
</x-mail::message>
