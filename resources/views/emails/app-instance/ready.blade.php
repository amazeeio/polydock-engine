<x-mail::message>
Hello!

# Your *"{{ $appInstance->storeApp->name }}"* Experience is now ready to use.

<x-mail::button :url="route('app-instances.show', $appInstance)">
    Access Your Experience
</x-mail::button>

**Access Details:**
@if($appInstance->storeApp->trial_duration_days > 0)
- Duration: {{ $appInstance->storeApp->trial_duration_days }} days
@endif
- Access URL: <a href="{{ route('app-instances.show', $appInstance) }}">{{ route('app-instances.show', $appInstance) }}</a>

Login Credentials: 
- Username: @if($appInstance->getGeneratedAppAdminUsername()) {{ $appInstance->getGeneratedAppAdminUsername() }} @else **missing - please contact support** @endif 
- Password: @if($appInstance->getGeneratedAppAdminPassword()) {{ $appInstance->getGeneratedAppAdminPassword() }} @else **missing - please contact support** @endif

@if($appInstance->storeApp->email_body_markdown)
---

{!! $appInstance->storeApp->email_body_markdown !!}

---
@else
Thanks,<br>
{{ config('app.name') }}
@endif

</x-mail::message> 