<x-mail::message>
@if($appInstance->storeApp->one_day_left_email_markdown)
{!! $appInstance->storeApp->one_day_left_email_markdown !!}
@else
# One Day Left in Your Trial

Hi {{ $appInstance->userGroup->owner->name }},

Your trial of {{ $appInstance->name }} will end tomorrow.

<x-mail::button :url="route('app-instances.show', $appInstance)">
View Your Instance
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
@endif
</x-mail::message> 