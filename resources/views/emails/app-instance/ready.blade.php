<x-mail::message>
# Your Experience is Ready

Your *"{{ $appInstance->storeApp->name }}"* Experience is now ready to use.

<x-mail::button :url="$appInstance->getKeyValue('claim-command-output')">
Access Your Experience
</x-mail::button>

**Access Details:**
- Duration: 7 days
- Access URL: {{ $appInstance->getKeyValue('claim-command-output') }}

Login Credentials: 
- Username: @if($appInstance->getKeyValue('lagoon-generate-app-admin-username')) {{ $appInstance->getKeyValue('lagoon-generate-app-admin-username') }} @else **missing - please contact support** @endif 
- Password: @if($appInstance->getKeyValue('lagoon-generate-app-admin-password')) {{ $appInstance->getKeyValue('lagoon-generate-app-admin-password') }} @else **missing - please contact support** @endif

@if($appInstance->storeApp->email_body_markdown)
---

{!! $appInstance->storeApp->email_body_markdown !!}

---
@else
Thanks,<br>
{{ config('app.name') }}
@endif

</x-mail::message> 