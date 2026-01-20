<mj-section padding="0">
  <mj-column>
    <mj-button href="{{ route('app-instances.show', $appInstance) }}" color="#000" background-color="#00b6ed">
      Access Your {{ $appInstance->storeApp->name }} Instance
    </mj-button>
  </mj-column>
</mj-section>

<mj-section>
  <mj-column>
    <mj-text font-size="18px" font-weight="600" margin-top="32px" margin-bottom="12px" color="#1a202c">
      Access Details:
    </mj-text>
  </mj-column>
</mj-section>

<mj-section>
  <mj-column>
    <mj-text margin-bottom="8px" color="#1a202c">
      <strong>Access URL:</strong> <a href="{{ route('app-instances.show', $appInstance) }}" style="color: #0891b2; text-decoration: none;">{{ route('app-instances.show', $appInstance) }}</a>
    </mj-text>
  </mj-column>
</mj-section>

<mj-section>
  <mj-column>
    <mj-text font-size="16px" font-weight="600" margin-top="24px" margin-bottom="8px" color="#1a202c">
      Login Credentials:
    </mj-text>
  </mj-column>
</mj-section>

<mj-section>
  <mj-column>
    <mj-text margin-bottom="6px" color="#1a202c">
      <strong>Username:</strong> 
      @if($appInstance->getGeneratedAppAdminUsername())
        {{ $appInstance->getGeneratedAppAdminUsername() }}
      @else
        <em>missing - please contact support</em>
      @endif
    </mj-text>

    <mj-text margin-bottom="6px" color="#1a202c">
      <strong>Password:</strong> 
      <span style="font-family: monospace; font-weight: 600;">
        @if($appInstance->getGeneratedAppAdminPassword())
          {{ $appInstance->getGeneratedAppAdminPassword() }}
        @else
          <em>missing - please contact support</em>
        @endif
      </span>
    </mj-text>
  </mj-column>
</mj-section>
