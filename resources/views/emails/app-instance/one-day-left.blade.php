<x-mail::message>
# One Day Left In Your {{ $appInstance->storeApp->name }} Trial

Hi {{ $toUser->name }},

@if($appInstance->storeApp->one_day_left_email_markdown)
{!! $appInstance->storeApp->one_day_left_email_markdown !!}
@else
Your trial of {{ $appInstance->storeApp->name }} will end tomorrow.

Thanks,<br>
{{ config('app.name') }}
@endif

---

<x-mail::button :url="route('app-instances.show', $appInstance)">
Access Your {{ $appInstance->storeApp->name }} Instance
</x-mail::button>

**Access Details:**
- Access URL: <a href="{{ route('app-instances.show', $appInstance) }}">{{ route('app-instances.show', $appInstance) }}</a>

Login Credentials: 
- Username: @if($appInstance->getGeneratedAppAdminUsername()) {{ $appInstance->getGeneratedAppAdminUsername() }} @else **missing - please contact support** @endif 
- Password: @if($appInstance->getGeneratedAppAdminPassword()) {{ $appInstance->getGeneratedAppAdminPassword() }} @else **missing - please contact support** @endif
</x-mail::message> 