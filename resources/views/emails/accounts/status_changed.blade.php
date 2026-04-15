<x-mail::message>
# {{ $status === 'active' ? __('emails.account.approved_title') : __('emails.account.rejected_title') }}

{{ __('emails.account.hello', ['name' => $user->name]) }}

@if($status === 'active')
{{ __('emails.account.approved_body') }}

<x-mail::button :url="config('app.url') . '/login'">
{{ __('emails.account.login_button') }}
</x-mail::button>
@else
{{ __('emails.account.rejected_body') }}
@endif

{{ __('emails.account.outro') }}

Cordialement,<br>
{{ __('emails.account.signature', ['app' => config('app.name')]) }}
</x-mail::message>
