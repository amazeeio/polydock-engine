<x-mail::message>
@if($appInstance->storeApp->trial_complete_email_markdown)
{!! $appInstance->storeApp->trial_complete_email_markdown !!}
@else
# Trial Complete

Hi {{ $appInstance->userGroup->owner->name }},

Your trial of {{ $appInstance->name }} has ended.

<x-mail::button :url="route('app-instances.show', $appInstance)">
View Your Instance
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
@endif
</x-mail::message> 