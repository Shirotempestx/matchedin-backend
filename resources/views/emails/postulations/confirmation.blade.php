<x-mail::message>
# {{ __('emails.postulation.confirmation_title') }}

{{ __('emails.postulation.hello', ['name' => $postulation->user->name]) }}

{{ __('emails.postulation.confirmation_intro', ['title' => $postulation->offre->title]) }}

**{{ __('emails.postulation.company') }} :** {{ $postulation->offre->user->company_name ?? $postulation->offre->user->name }}
**{{ __('emails.postulation.date') }} :** {{ $postulation->created_at->format('d/m/Y H:i') }}

{{ __('emails.postulation.confirmation_outro') }}

<x-mail::button :url="config('app.url') . '/dashboard'">
{{ __('emails.postulation.confirmation_button') }}
</x-mail::button>

{{ __('emails.postulation.good_luck') }}

Cordialement,<br>
{{ __('emails.postulation.signature', ['app' => config('app.name')]) }}
</x-mail::message>
