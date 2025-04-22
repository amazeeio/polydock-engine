<x-mail::message>
# One Day Left In Your Trial

Hi {{ $toUser->name }},

Your trial of {{ $appInstance->storeApp->name }} will end tomorrow.

<x-mail::button :url="route('app-instances.show', $appInstance)">
View Your Instance
</x-mail::button>

**Access Details:**
@if($appInstance->storeApp->trial_duration_days > 0)
- Duration: {{ $appInstance->storeApp->trial_duration }} days
@endif
- Access URL: <a href="{{ route('app-instances.show', $appInstance) }}">{{ route('app-instances.show', $appInstance) }}</a>

Login Credentials: 
- Username: @if($appInstance->getGeneratedAppAdminUsername()) {{ $appInstance->getGeneratedAppAdminUsername() }} @else **missing - please contact support** @endif 
- Password: @if($appInstance->getGeneratedAppAdminPassword()) {{ $appInstance->getGeneratedAppAdminPassword() }} @else **missing - please contact support** @endif

@if($appInstance->storeApp->one_day_left_email_markdown)
---

{!! $appInstance->storeApp->one_day_left_email_markdown !!}

---
@else
Thanks,<br>
{{ config('app.name') }}
@endif
</x-mail::message> 