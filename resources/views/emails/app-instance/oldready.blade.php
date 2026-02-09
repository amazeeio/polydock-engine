<x-mail::message>
Hi {{ e($toUser->name) }},

<h2 style="margin-bottom: 16px;">Your <em>"{{ $appInstance->storeApp->name }}"</em> Experience is now ready to use.</h2>

@if($appInstance->getKeyValue('hide-login-email-info') != 'true')
<x-mail::button :url="route('app-instances.show', $appInstance)">
    Access Your Experience
</x-mail::button>
@else
<x-mail::button :url="$appInstance->app_url">
    Access Your Experience
</x-mail::button>
@endif

<h3 style="margin-top: 32px; margin-bottom: 8px;">Access Details:</h3>
<ul style="margin-top: 0;">
@if($appInstance->storeApp->trial_duration_days > 0)
    <li>Duration: {{ $appInstance->storeApp->trial_duration_days }} days</li>
@endif
@if($appInstance->getKeyValue('hide-login-email-info') != 'true')
    <li>Access URL: <a href="{{ route('app-instances.show', $appInstance) }}">{{ route('app-instances.show', $appInstance) }}</a></li>
@else
    <li>Access URL: <a href="{{ $appInstance->app_url }}">{{ $appInstance->app_url }}</a></li>
@endif
</ul>
@if($appInstance->getKeyValue('hide-login-email-info') != 'true')
<h3 style="margin-top: 32px; margin-bottom: 8px;">User Information & Login Credentials:</h3>
@else
<h3 style="margin-top: 32px; margin-bottom: 8px;">User Information:</h3>
@endif
<table style="border-collapse: collapse; width: 100%; max-width: 600px;">
    <tr style="background: #f5f5f5;">
        <th style="text-align: left; padding: 8px; border: 1px solid #ddd;">Field</th>
        <th style="text-align: left; padding: 8px; border: 1px solid #ddd;">Value</th>
    </tr>
    <tr>
        <td style="padding: 8px; border: 1px solid #ddd;">Name</td>
        <td style="padding: 8px; border: 1px solid #ddd;">
            @if($appInstance->getUserFirstName() && $appInstance->getUserLastName())
                {{ $appInstance->getUserFirstName() }} {{ $appInstance->getUserLastName() }}
            @else
                <em>N/A</em>
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding: 8px; border: 1px solid #ddd;">Email</td>
        <td style="padding: 8px; border: 1px solid #ddd;">
            @if($appInstance->getUserEmail())
                {{ $appInstance->getUserEmail() }}
            @else
                <em>N/A</em>
            @endif
        </td>
    </tr>
    @if($appInstance->getKeyValue('hide-login-email-info') != 'true')
    <tr>
        <td style="padding: 8px; border: 1px solid #ddd;">Username</td>
        <td style="padding: 8px; border: 1px solid #ddd;">
            @if($appInstance->getGeneratedAppAdminUsername())
                {{ $appInstance->getGeneratedAppAdminUsername() }}
            @else
                <em>missing - please contact support</em>
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding: 8px; border: 1px solid #ddd;">Password</td>
        <td style="padding: 8px; border: 1px solid #ddd;">
            @if($appInstance->getGeneratedAppAdminPassword())
                {{ $appInstance->getGeneratedAppAdminPassword() }}
            @else
                <em>missing - please contact support</em>
            @endif
        </td>
    </tr>
    @endif
</table>

@if($appInstance->storeApp->email_body_markdown)
<hr style="margin: 32px 0;" />
{!! $appInstance->storeApp->email_body_markdown !!}
<hr style="margin: 32px 0;" />
@else
Thanks,<br>
{{ config('app.name') }}
@endif

</x-mail::message> 