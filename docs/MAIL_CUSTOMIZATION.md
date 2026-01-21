# Email Customization

The application uses MJML for responsive email templates. The configuration for these emails is located in `config/mail.php` under the `mjml-config` key. You can customize the look and feel, including themes, logos, and footer content, using environment variables.

## Environment Variables

### General Configuration

### Logo Configuration

The logo is displayed in the header of the email.

| Variable | Default | Description |
| :--- | :--- | :--- |
| `EMAIL_LOGO_URL` | `/emails/logo.svg` | The URL to the logo image. Can be a relative path (which will be resolved against `APP_URL`) or an absolute URL. Note: The dark theme uses `/emails/amazee-io-Logo-Black-White-IO.png` by default. |
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

One theme is currently defined in `config/mail.php`:

### Dark Theme (`dark`)
- **Background**: Light gray (`#e5e7eb`)
- **Content**: White (`#ffffff`)
- **Header**: Light gray (`#f4f4f4`)
- **Footer**: Light gray (`#f4f4f4`)
- **Text**: Dark gray (`#333`)
- **Links**: Blue (`#2563eb`)
- **Primary Button Background**: Black (`#000000`)
- **Primary Button Text**: White (`#ffffff`)
- **Logo**: `/emails/amazee-io-Logo-Black-White-IO.png`

### Typography Settings

The dark theme includes typography configuration:
- **Font Family**: `sans-serif`
- **Heading Color**: `#333`
- **Body Font Size**: `16px`
- **Heading Font Size**: `24px`


