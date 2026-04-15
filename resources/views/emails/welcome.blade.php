<x-mail::message>
# {{ __('emails.welcome.title', ['name' => $user->name]) }}

{{ __('emails.welcome.intro') }}

@if($user->role === 'student')
**{{ __('emails.welcome.student_cta') }}**
{{ __('emails.welcome.student_body') }}
@else
**{{ __('emails.welcome.enterprise_cta') }}**
{{ __('emails.welcome.enterprise_body') }}
@endif

<x-mail::button :url="config('app.url') . '/dashboard'">
{{ __('emails.welcome.button') }}
</x-mail::button>

{{ __('emails.welcome.outro') }}

Cordialement,<br>
{{ __('emails.welcome.signature', ['app' => config('app.name')]) }}
</x-mail::message>
