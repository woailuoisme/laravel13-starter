<x-mail::message>
# {{ __('auth.verification_code_subject') }}

{{ __('auth.verification_code_intro', ['action' => $actionLabel]) }}

<x-mail::panel>
{{ $code }}
</x-mail::panel>

{{ __('auth.verification_code_expire_notice') }}

{{ __('auth.verification_code_ignore_notice') }}

{{ config('app.name') }}
</x-mail::message>
