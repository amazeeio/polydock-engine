<x-mail::message>
# Trial Complete

Hi {{ $appInstance->userGroup->owner->name }},

Your trial of {{ $appInstance->name }} has ended.

@if($appInstance->storeApp->trial_complete_email_markdown)
---

{!! $appInstance->storeApp->trial_complete_email_markdown !!}

---
@else
Thanks,<br>
{{ config('app.name') }}
@endif
</x-mail::message> 