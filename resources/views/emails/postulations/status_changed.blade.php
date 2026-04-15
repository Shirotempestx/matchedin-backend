<x-mail::message>
# {{ __('emails.postulation.status_changed_title') }}

{{ __('emails.postulation.hello', ['name' => $postulation->user->name]) }}

{{ __('emails.postulation.status_changed_intro', ['title' => $postulation->offre->title]) }}

**{{ __('emails.postulation.new_status') }} :**
{{ $postulation->status === 'accepted' ? __('emails.postulation.status_accepted') : ($postulation->status === 'rejected' ? __('emails.postulation.status_rejected') : __('emails.postulation.status_pending')) }}

@if($postulation->status === 'accepted')
{{ __('emails.postulation.status_accepted_body') }}
@elseif($postulation->status === 'rejected')
{{ __('emails.postulation.status_rejected_body') }}
@endif

<x-mail::button :url="config('app.url') . '/dashboard'">
{{ __('emails.postulation.view_application') }}
</x-mail::button>

Cordialement,<br>
{{ __('emails.postulation.signature', ['app' => config('app.name')]) }}
</x-mail::message>
