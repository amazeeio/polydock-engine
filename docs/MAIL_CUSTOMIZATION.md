# Email Customization

The application uses MJML for responsive email templates. The configuration for these emails is located in `config/mail.php` under the `mjml-config` key. You can customize the look and feel, including themes, logos, and footer content, using environment variables.

## Environment Variables

### General Configuration

| Variable | Default | Description |
| :--- | :--- | :--- |
| `EMAIL_DEFAULT_THEME` | `dark` | Controls the default visual theme for emails. Available options are `light` and `dark`. |

### Logo Configuration

The logo is displayed in the header of the email.

| Variable | Default | Description |
| :--- | :--- | :--- |
| `EMAIL_LOGO_URL` | `/emails/logo.svg` | The URL to the logo image. Can be a relative path (which will be resolved against `APP_URL`) or an absolute URL. |
| `EMAIL_LOGO_ALT` | `Company Logo` | The alternative text for the logo image. |
| `EMAIL_LOGO_WIDTH` | `150` | The width of the logo in pixels. |
| `EMAIL_LOGO_HEIGHT` | `auto` | The height of the logo in pixels. |

### Footer Configuration

The footer appears at the bottom of every email and contains company information and links.

| Variable | Default | Description |
| :--- | :--- | :--- |
| `EMAIL_FOOTER_COMPANY_NAME` | `amazee.io` | The company name displayed in the footer. |
| `EMAIL_FOOTER_COMPANY_URL` | `https://amazee.io` | The URL that the company name links to. |
| `EMAIL_FOOTER_ADDRESS` | `amazee.io, Hardturmstrasse 161...` | The physical address displayed in the footer. |
| `EMAIL_FOOTER_COPYRIGHT` | `© [Year] amazee.io...` | The copyright text displayed in the footer. Defaults to the current year. |
| `EMAIL_FOOTER_DISCLAIMER` | `This email was sent to you...` | The disclaimer text displayed at the very bottom. |

### Footer Links

You can control the visibility and destination of links in the footer.

| Variable | Default | Description |
| :--- | :--- | :--- |
| `EMAIL_SUPPORT_URL` | `https://www.amazee.io/product/support` | The URL for the "Contact Support" link. |
| `EMAIL_SUPPORT_LINK_ENABLED` | `true` | Set to `false` to hide the "Contact Support" link. |
| `EMAIL_PRIVACY_URL` | `https://www.amazee.io/privacy-policy` | The URL for the "Privacy Policy" link. |
| `EMAIL_PRIVACY_LINK_ENABLED` | `true` | Set to `false` to hide the "Privacy Policy" link. |
| `EMAIL_TERMS_URL` | *(empty)* | The URL for the "Terms of Service" link. |
| `EMAIL_TERMS_LINK_ENABLED` | `false` | Set to `true` to show the "Terms of Service" link. |
| `EMAIL_UNSUBSCRIBE_URL` | `/unsubscribe` | The URL for the unsubscribe link. *(Note: This is configured but may not be displayed in all templates by default)* |

## Themes

Two themes are defined in `config/mail.php` by default:

### Light Theme (`light`)
- **Background**: Light gray (`#f0f4f8`)
- **Content**: White (`#ffffff`)
- **Footer**: Dark gray (`#263238`)
- **Links**: Cyan (`#0891b2`)
- **Logo**: Defaults to `/emails/amazee-logo-light.svg`

### Dark Theme (`dark`)
- **Background**: Light gray (`#e5e7eb`)
- **Content**: White (`#ffffff`)
- **Header**: Dark gray (`#1f2937`)
- **Footer**: Very dark gray (`#111827`)
- **Links**: Blue (`#2563eb`)
- **Logo**: Defaults to `/emails/amazee-logo-dark.svg`

The theme controls the color palette for the email body, header, footer, text, and buttons. You can select the active theme using `EMAIL_DEFAULT_THEME`.
