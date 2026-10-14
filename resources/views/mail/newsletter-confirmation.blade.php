{{
    __('mail.newsletter.greeting', ['app' => config('app.name')])
}}

{{ __('mail.newsletter.intro') }}

@component('mail::button', ['url' => $confirmUrl])
{{ __('mail.newsletter.action') }}
@endcomponent

{{ __('mail.newsletter.ignore') }}
