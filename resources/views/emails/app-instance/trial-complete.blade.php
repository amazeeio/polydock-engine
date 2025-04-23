<x-mail::message>
# {{ $appInstance->storeApp->name }} Trial Complete

Hi {{ $toUser->name }},

@if($appInstance->storeApp->trial_complete_email_markdown)
{!! $appInstance->storeApp->trial_complete_email_markdown !!}
@else
Your trial of {{ $appInstance->storeApp->name }} has ended.

Thanks,<br>
{{ config('app.name') }}
@endif
</x-mail::message> 