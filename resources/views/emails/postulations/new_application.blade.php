<x-mail::message>
# {{ __('emails.postulation.new_application_title') }}

{{ __('emails.postulation.hello', ['name' => ($postulation->offre->user->company_name ?? $postulation->offre->user->name)]) }}

{{ __('emails.postulation.new_application_intro', ['title' => $postulation->offre->title]) }}

**{{ __('emails.postulation.candidate') }} :** {{ $postulation->user->name }}
**{{ __('emails.postulation.profile') }} :** {{ $postulation->user->profile_type ?? __('emails.postulation.not_specified') }}

@if($postulation->message)
**{{ __('emails.postulation.candidate_message') }} :**
> {{ $postulation->message }}
@endif

<x-mail::button :url="config('app.url') . '/dashboard/recruiter'">
{{ __('emails.postulation.view_candidate') }}
</x-mail::button>

Cordialement,<br>
{{ __('emails.postulation.signature', ['app' => config('app.name')]) }}
</x-mail::message>
