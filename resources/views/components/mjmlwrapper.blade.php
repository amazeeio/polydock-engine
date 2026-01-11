<mjml>
  <mj-head>
    <mj-title>{{ $emailTitle ?? 'Email' }}</mj-title>
    <mj-preview>{{ $emailPreview ?? '' }}</mj-preview>

    <!-- Default font and styles -->
    <mj-attributes>
      <mj-all font-family="{{ $theme['typography']['font_family'] }}" />
      <mj-text font-size="{{ $theme['typography']['body_font_size'] }}" color="{{ $theme['colors']['text'] }}" line-height="1.6" />
      <mj-section padding="0px" />
    </mj-attributes>

    <!-- Custom styles -->
    <mj-style>
      .link-style {
      color: {{ $theme['colors']['links'] }};
      text-decoration: none;
      }
      .link-style:hover {
      text-decoration: underline;
      }
      .footer-link {
      color: {{ $theme['colors']['text_muted'] }};
      text-decoration: none;
      padding: 0 8px;
      }
      .footer-link:hover {
      text-decoration: underline;
      }
    </mj-style>
  </mj-head>

  <mj-body background-color="{{ $theme['colors']['body_background'] }}">
    <!-- Header Section -->
    @if (!empty($theme['logo']))
    <mj-section background-color="{{ $theme['colors']['header_background'] }}" padding="30px 20px">
      <mj-column>
        <mj-image
          src="{{ $theme['logo']['url'] }}"
          alt="{{ $theme['logo']['alt'] }}"
          width="{{ $theme['logo']['width'] }}px"
          align="center"
          padding="0" />
      </mj-column>
    </mj-section>
    @endif

    <!-- Main Content Section -->
    <mj-section background-color="{{ $theme['colors']['content_background'] }}" padding="40px 20px">
      <mj-column>
        {{ $slot }}
      </mj-column>
    </mj-section>


    <!-- Footer Section -->
    <mj-section background-color="{{ $theme['colors']['footer_background'] }}" padding="40px 20px">
      <mj-column>

        <!-- Company Name -->
        <mj-text
          align="center"
          color="{{ $theme['colors']['text_muted'] }}"
          font-size="14px"
          font-weight="600"
          padding-bottom="10px">
          @if(!empty($config['footer']['company_url']))
            <a href="{{ $config['footer']['company_url'] }}" style="color: {{ $theme['colors']['text_muted'] }}; text-decoration: none;">{{ $config['footer']['company_name'] }}</a>
          @else
            {{ $config['footer']['company_name'] }}
          @endif
        </mj-text>

        <!-- Address -->
        <mj-text
          align="center"
          color="{{ $theme['colors']['text_muted'] }}"
          font-size="12px"
          line-height="1.5"
          padding-bottom="20px">
          {{ $config['footer']['address'] }}
        </mj-text>

        <!-- Footer Links -->
        @if (!empty($config['footer']['links']))
        <mj-text align="center" padding-bottom="20px">
          <mj-raw>
            @if(isset($config['footer']['links']['support']) && ($config['footer']['links']['support']['enabled'] ?? true))
              <a href="{{ $config['footer']['links']['support']['url'] }}" class="footer-link">{{ $config['footer']['links']['support']['text'] }}</a>
            @endif
            @if(isset($config['footer']['links']['privacy']) && ($config['footer']['links']['privacy']['enabled'] ?? true))
              <a href="{{ $config['footer']['links']['privacy']['url'] }}" class="footer-link">{{ $config['footer']['links']['privacy']['text'] }}</a>
            @endif
            @if(isset($config['footer']['links']['terms']) && ($config['footer']['links']['terms']['enabled'] ?? true))
              <a href="{{ $config['footer']['links']['terms']['url'] }}" class="footer-link">{{ $config['footer']['links']['terms']['text'] }}</a>
            @endif
          </mj-raw>
        </mj-text>
        @endif

        <!-- Copyright -->
        @if (!empty($config['footer']['copyright_text']))
        <mj-text
          align="center"
          color="{{ $theme['colors']['text_muted'] }}"
          font-size="11px"
          padding-bottom="10px">
          {{ $config['footer']['copyright_text'] }}
        </mj-text>
        @endif

        <!-- Disclaimer -->
        @if (!empty($config['footer']['disclaimer']))
        <mj-text
          align="center"
          color="{{ $theme['colors']['text_muted'] }}"
          font-size="11px"
          line-height="1.4">
          {{ $config['footer']['disclaimer'] }}
        </mj-text>
        @endif
      </mj-column>
    </mj-section>

  </mj-body>
</mjml>