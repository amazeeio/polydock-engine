<x-mjmlwrapper :theme="$config['theme']" :config="$config">
  <x-mjml-title>
    One Day Left In Your {{ $appInstance->storeApp->name }} Trial
  </x-mjml-title>

  <mj-section>
    <mj-column>
      <mj-text>
        Hi {{ e($toUser->name) }},
      </mj-text>
    </mj-column>
  </mj-section>

  @if($appInstance->storeApp->one_day_left_email_markdown)
  <mj-section>
    <mj-column>
      <mj-text>
        {!! Illuminate\Mail\Markdown::parse($appInstance->storeApp->one_day_left_email_markdown) !!}
      </mj-text>
    </mj-column>
  </mj-section>
  @else
  <mj-section>
    <mj-column>
      <mj-text>
        Your trial of {{ $appInstance->storeApp->name }} will end tomorrow.
      </mj-text>
    </mj-column>
  </mj-section>

  <mj-section>
    <mj-column>
      <mj-text>
        Thanks,<br>
        {{ config('app.name') }}
      </mj-text>
    </mj-column>
  </mj-section>
  @endif

  <mj-section>
    <mj-column>
      <mj-divider border-color="#ddd" padding="20px 0"></mj-divider>
    </mj-column>
  </mj-section>

  <x-app-instance-credentials :appInstance="$appInstance" />
</x-mjmlwrapper>
