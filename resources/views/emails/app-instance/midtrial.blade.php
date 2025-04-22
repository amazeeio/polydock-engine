<x-mail::message>
# Halfway Through Your Trial

Hi {{ $toUser->name }},

You're halfway through your trial of {{ $appInstance->storeApp->name }}, which will end on {{ $appInstance->trial_ends_at->format('F j, Y') }}.

<x-mail::button :url="route('app-instances.show', $appInstance)">
View Your Instance
</x-mail::button>

**Access Details:**
@if($appInstance->storeApp->trial_duration_days > 0)
- Duration: {{ $appInstance->storeApp->trial_duration_days }} days
@endif
- Access URL: <a href="{{ route('app-instances.show', $appInstance) }}">{{ route('app-instances.show', $appInstance) }}</a>

Login Credentials: 
- Username: @if($appInstance->getGeneratedAppAdminUsername()) {{ $appInstance->getGeneratedAppAdminUsername() }} @else **missing - please contact support** @endif 
- Password: @if($appInstance->getGeneratedAppAdminPassword()) {{ $appInstance->getGeneratedAppAdminPassword() }} @else **missing - please contact support** @endif

@if($appInstance->storeApp->midtrial_email_markdown)
---

{!! $appInstance->storeApp->midtrial_email_markdown !!}

---
@else
Thanks,<br>
{{ config('app.name') }}
@endif
</x-mail::message> 