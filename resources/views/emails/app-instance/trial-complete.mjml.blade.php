<x-mjmlwrapper :theme="$config['themes'][$config['default_theme']]" :config="$config">
  <x-mjml-title>
    {{ $appInstance->storeApp->name }} Trial Complete
  </x-mjml-title>

  <mj-section>
    <mj-column>
      <mj-text>
        Hi {{ e($toUser->name) }},
      </mj-text>
    </mj-column>
  </mj-section>

  @if($appInstance->storeApp->trial_complete_email_markdown)
  <mj-section>
    <mj-column>
      <mj-text>
        {!! Illuminate\Mail\Markdown::parse($appInstance->storeApp->trial_complete_email_markdown) !!}
      </mj-text>
    </mj-column>
  </mj-section>
  @else
  <mj-section>
    <mj-column>
      <mj-text>
        Your trial of {{ $appInstance->storeApp->name }} has ended.
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
</x-mjmlwrapper>
