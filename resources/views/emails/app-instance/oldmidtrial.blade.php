<x-mail::message>
# Halfway Through {{ $appInstance->storeApp->name }} Trial

Hi {{ e($toUser->name) }},

@if($appInstance->storeApp->midtrial_email_markdown)
{!! $appInstance->storeApp->midtrial_email_markdown !!}
@else
You're halfway through your trial of {{ $appInstance->storeApp->name }}, which will end on {{ $appInstance->trial_ends_at->format('F j, Y') }}.

Thanks,<br>
{{ config('app.name') }}
@endif

---

<x-mail::button :url="URL::signedRoute('app-instances.show', ['appInstance' => $appInstance])">
Access Your {{ $appInstance->storeApp->name }} Instance
</x-mail::button>

**Access Details:**
- Access URL: <a href="{{ URL::signedRoute('app-instances.show', ['appInstance' => $appInstance]) }}">{{ URL::signedRoute('app-instances.show', ['appInstance' => $appInstance]) }}</a>

Login Credentials: 
- Username: @if($appInstance->getGeneratedAppAdminUsername()) {{ $appInstance->getGeneratedAppAdminUsername() }} @else **missing - please contact support** @endif 
- Password: @if($appInstance->getGeneratedAppAdminPassword()) {{ $appInstance->getGeneratedAppAdminPassword() }} @else **missing - please contact support** @endif
</x-mail::message> 