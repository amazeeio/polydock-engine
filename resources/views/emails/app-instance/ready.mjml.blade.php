<x-mjmlwrapper :theme="$config['themes'][$config['default_theme']]" :config="$config">
    <mj-section>
      <mj-column>
        <mj-text>
          Hi {{ e($toUser->name) }},
        </mj-text>
      </mj-column>
    </mj-section>

    <mj-section>
      <mj-column>
        <mj-text font-size="24px" font-weight="bold" margin-bottom="16px">
          Your <em>"{{ $appInstance->storeApp->name }}"</em> Experience is now ready to use.
        </mj-text>
      </mj-column>
    </mj-section>

    <mj-section>
      <mj-column>
        @if($appInstance->getKeyValue('hide-login-email-info') != 'true')
          <mj-button href="{{ route('app-instances.show', $appInstance) }}" background-color="#0066cc">
            Access Your Experience
          </mj-button>
        @else
          <mj-button href="{{ $appInstance->app_url }}" background-color="#0066cc">
            Access Your Experience
          </mj-button>
        @endif
      </mj-column>
    </mj-section>

    <mj-section>
      <mj-column>
        <mj-text font-size="18px" font-weight="bold" margin-top="32px" margin-bottom="8px">
          Access Details:
        </mj-text>
      </mj-column>
    </mj-section>

    <mj-section>
      <mj-column>
        <mj-text>
          @if($appInstance->storeApp->trial_duration_days > 0)
            <strong>Duration:</strong> {{ $appInstance->storeApp->trial_duration_days }} days<br>
          @endif
          <strong>Access URL:</strong> 
          @if($appInstance->getKeyValue('hide-login-email-info') != 'true')
            <a href="{{ route('app-instances.show', $appInstance) }}">{{ route('app-instances.show', $appInstance) }}</a>
          @else
            <a href="{{ $appInstance->app_url }}">{{ $appInstance->app_url }}</a>
          @endif
        </mj-text>
      </mj-column>
    </mj-section>

    <mj-section>
      <mj-column>
        @if($appInstance->getKeyValue('hide-login-email-info') != 'true')
          <mj-text font-size="18px" font-weight="bold" margin-top="32px" margin-bottom="8px">
            User Information & Login Credentials:
          </mj-text>
        @else
          <mj-text font-size="18px" font-weight="bold" margin-top="32px" margin-bottom="8px">
            User Information:
          </mj-text>
        @endif
      </mj-column>
    </mj-section>

    <mj-section>
      <mj-column>
        <mj-table>
          <tr>
            <th style="text-align: left; padding: 8px; border: 1px solid #ddd; background-color: #f5f5f5; font-weight: bold;">Field</th>
            <th style="text-align: left; padding: 8px; border: 1px solid #ddd; background-color: #f5f5f5; font-weight: bold;">Value</th>
          </tr>
          <tr>
            <td style="text-align: left; padding: 8px; border: 1px solid #ddd;">Name</td>
            <td style="text-align: left; padding: 8px; border: 1px solid #ddd;">
              @if($appInstance->getUserFirstName() && $appInstance->getUserLastName())
                {{ $appInstance->getUserFirstName() }} {{ $appInstance->getUserLastName() }}
              @else
                <em>N/A</em>
              @endif
            </td>
          </tr>
          <tr>
            <td style="text-align: left; padding: 8px; border: 1px solid #ddd;">Email</td>
            <td style="text-align: left; padding: 8px; border: 1px solid #ddd;">
              @if($appInstance->getUserEmail())
                {{ $appInstance->getUserEmail() }}
              @else
                <em>N/A</em>
              @endif
            </td>
          </tr>
          @if($appInstance->getKeyValue('hide-login-email-info') != 'true')
          <tr>
            <td style="text-align: left; padding: 8px; border: 1px solid #ddd;">Username</td>
            <td style="text-align: left; padding: 8px; border: 1px solid #ddd;">
              @if($appInstance->getGeneratedAppAdminUsername())
                {{ $appInstance->getGeneratedAppAdminUsername() }}
              @else
                <em>missing - please contact support</em>
              @endif
            </td>
          </tr>
          <tr>
            <td style="text-align: left; padding: 8px; border: 1px solid #ddd;">Password</td>
            <td style="text-align: left; padding: 8px; border: 1px solid #ddd;">
              @if($appInstance->getGeneratedAppAdminPassword())
                {{ $appInstance->getGeneratedAppAdminPassword() }}
              @else
                <em>missing - please contact support</em>
              @endif
            </td>
          </tr>
          @endif
        </mj-table>
      </mj-column>
    </mj-section>

    @if($appInstance->storeApp->email_body_markdown)
    <mj-section>
      <mj-column>
        <mj-divider border-color="#ddd" padding="32px 0"></mj-divider>
      </mj-column>
    </mj-section>

    <mj-section>
      <mj-column>
        <mj-text>
          {!! $appInstance->storeApp->email_body_markdown !!}
        </mj-text>
      </mj-column>
    </mj-section>

    <mj-section>
      <mj-column>
        <mj-divider border-color="#ddd" padding="32px 0"></mj-divider>
      </mj-column>
    </mj-section>
    @else
    <mj-section>
      <mj-column>
        <mj-text>
          Thanks,<br>
          {{ config('app.name') }}
        </mj-text>
      </mj-column>
    </mj-section>
    @endif
</x-mjmlwrapper>