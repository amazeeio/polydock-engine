<x-mail::message>
# Trial Complete

Hi {{ $toUser->name }},

Your trial of {{ $appInstance->storeApp->name }} has ended.

@if($appInstance->storeApp->trial_complete_email_markdown)
---

{!! $appInstance->storeApp->trial_complete_email_markdown !!}

---
@else
Thanks,<br>
{{ config('app.name') }}
@endif
</x-mail::message> 