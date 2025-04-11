<x-mail::message>
@if($appInstance->storeApp->midtrial_email_markdown)
{!! $appInstance->storeApp->midtrial_email_markdown !!}
@else
# Halfway Through Your Trial

Hi {{ $appInstance->userGroup->owner->name }},

You're halfway through your trial of {{ $appInstance->name }}. We hope you're enjoying it so far!

Your trial will end on {{ $appInstance->trial_ends_at->format('F j, Y') }}.

<x-mail::button :url="route('app-instances.show', $appInstance)">
View Your Instance
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
@endif
</x-mail::message> 