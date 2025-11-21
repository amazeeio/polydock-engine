# Mail Theming System

## Overview

The Mail Theming System allows you to create and manage multiple email templates and themes for transactional emails sent by the Polydock Engine. Each theme can override the default email layout, styling, and content while maintaining fallback to default templates if themed versions don't exist.

## Directory Structure

```
storage/app/private/templates/
├── theme-name/                      # Theme name (directory name = theme key)
│   ├── emails/
│   │   └── app-instance/
│   │       ├── ready.blade.php (optional)
│   │       ├── midtrial.blade.php (optional)
│   │       ├── one-day-left.blade.php (optional)
│   │       └── trial-complete.blade.php (optional)
│   └── css/
│       └── theme.css        # Optional theme-specific styles
├── another-theme/
│   └── emails/
│       └── ...
```

## Creating a New Theme

### Step 1: Create Theme Directory

```bash
mkdir -p storage/app/private/templates/your-theme-name/emails/app-instance
```

### Step 2: create your theme

Add a theme.css file with your mail's css

### Step 3: Create Email Templates (Optional)

If you want to override specific email templates, create them in the theme directory:

- `storage/app/private/templates/your-theme-name/emails/app-instance/ready.blade.php`
- `storage/app/private/templates/your-theme-name/emails/app-instance/midtrial.blade.php`
- `storage/app/private/templates/your-theme-name/emails/app-instance/one-day-left.blade.php`
- `storage/app/private/templates/your-theme-name/emails/app-instance/trial-complete.blade.php`

If these files don't exist, the system falls back to the default templates in `resources/views/emails/app-instance/`.

## Using Themes

### In the Admin UI

1. Navigate to Apps → Apps
2. Create or edit a store app
3. Select a theme from the **Email Theme** dropdown
4. Save the record

The theme name will be stored in the `polydock_store_apps.mail_theme` column.

## Theme Resolution Logic

When an email is sent:

1. Check if a `mail_theme` is set on the store app
2. If set, resolve the themed template path (e.g., `promet::emails.app-instance.ready`)
3. If the themed template exists, use it
4. If the themed template doesn't exist, fall back to the default template (e.g., `emails.app-instance.ready`)
5. If no fallback exists, throw an exception
