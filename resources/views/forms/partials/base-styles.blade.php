<style>
    /* Premium Visual Design System - Tailored to match amazee.ai */
    :root {
        --color-primary: #12285f;
        --color-primary-hover: #1b387a;
        --color-accent: #d81159;
        --color-bg: #ffffff;
        --color-text: #1f2937;
        --color-text-muted: #4b5563;
        --color-border: #d1d5db;
        --color-focus: rgba(18, 40, 95, 0.15);
        --color-success: #10b981;
        --color-error: #ef4444;
        --font-sans: 'Outfit', 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        background-color: var(--color-bg);
        color: var(--color-text);
        font-family: var(--font-sans);
        font-size: 16px;
        line-height: 1.5;
        padding: 1.5rem;
    }

    .form-container {
        max-width: 600px;
        margin: 0 auto;
        animation: fadeIn 0.4s ease-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 1.25rem;
    }

    .form-label {
        font-weight: 500;
        font-size: 0.95rem;
        color: var(--color-primary);
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .required-star {
        color: var(--color-accent);
        font-weight: bold;
    }

    .form-control {
        font-family: var(--font-sans);
        font-size: 1rem;
        padding: 0.75rem 1rem;
        border: 1px solid var(--color-border);
        border-radius: 0.5rem;
        color: var(--color-text);
        background-color: #ffffff;
        transition: all 0.2s ease-in-out;
        width: 100%;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px var(--color-focus);
    }

    select.form-control {
        appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg fill='%2312285f' height='24' viewBox='0 0 24 24' width='24' xmlns='http://www.w3.org/2000/svg'><path d='M7 10l5 5 5-5z'/><path d='M0 0h24v24H0z' fill='none'/></svg>");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1.5rem;
        padding-right: 2.5rem;
    }

    .disclaimer-text {
        font-size: 0.875rem;
        color: var(--color-text-muted);
        margin: 1.5rem 0;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .disclaimer-text p {
        line-height: 1.4rem;
    }

    .disclaimer-text a {
        color: #3b82f6;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s;
    }

    .disclaimer-text a:hover {
        color: #1d4ed8;
        text-decoration: underline;
    }

    .btn-submit {
        font-family: var(--font-sans);
        font-size: 1.05rem;
        font-weight: 600;
        background-color: var(--color-primary);
        color: #ffffff;
        border: 2px solid var(--color-primary);
        border-radius: 0.5rem;
        padding: 0.85rem 2rem;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        display: inline-block;
        width: auto;
    }

    .btn-submit:hover {
        background-color: #ffffff;
        color: var(--color-primary);
        border-color: var(--color-primary);
    }

    .btn-submit:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* Loading and Status Screens */
    .status-screen {
        text-align: center;
        padding: 3rem 1.5rem;
        animation: fadeIn 0.4s ease-out;
    }

    .spinner-container {
        display: flex;
        justify-content: center;
        margin-bottom: 2rem;
    }

    .spinner {
        border: 4px solid rgba(18, 40, 95, 0.1);
        border-top: 4px solid var(--color-primary);
        border-radius: 50%;
        width: 50px;
        height: 50px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .status-title {
        color: var(--color-primary);
        font-size: 1.6rem;
        font-weight: 700;
        margin-bottom: 1rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-message {
        font-size: 1.1rem;
        color: var(--color-text-muted);
        max-width: 500px;
        margin: 0 auto;
        line-height: 1.6;
    }

    /* Success Card Aesthetics */
    .congratulations-card {
        text-align: center;
        padding: 2.5rem;
        background: #ffffff;
        border-radius: 1rem;
        border: 1px solid var(--color-border);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        max-width: 550px;
        margin: 0 auto;
        animation: fadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .success-icon {
        width: 64px;
        height: 64px;
        background-color: rgba(16, 185, 129, 0.1);
        color: var(--color-success);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem auto;
    }

    .congratulations-heading {
        color: var(--color-primary);
        font-size: 1.8rem;
        font-weight: 800;
        margin-bottom: 1.5rem;
    }

    .congratulations-heading span {
        display: block;
        font-size: 1.1rem;
        font-weight: 500;
        color: var(--color-text-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.25rem;
    }

    .credentials-box {
        background-color: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1.5rem;
        margin: 1.5rem 0;
        text-align: left;
    }

    .credentials-box h4 {
        color: var(--color-primary);
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .credentials-list {
        list-style: none;
    }

    .credentials-list li {
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
        display: flex;
        gap: 0.5rem;
    }

    .credentials-list li:last-child {
        margin-bottom: 0;
    }

    .credentials-label {
        font-weight: 600;
        color: var(--color-primary);
        min-width: 90px;
    }

    .credentials-value {
        color: var(--color-text);
        word-break: break-all;
    }

    .btn-login {
        display: inline-block;
        background-color: var(--color-primary);
        color: #ffffff !important;
        font-weight: 600;
        text-decoration: none;
        padding: 0.85rem 2rem;
        border-radius: 0.5rem;
        margin: 1rem 0 2rem 0;
        transition: all 0.2s;
        border: 2px solid var(--color-primary);
    }

    .btn-login:hover {
        background-color: #ffffff;
        color: var(--color-primary) !important;
    }

    .callout-box {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        text-align: left;
        border-top: 1px solid #e5e7eb;
        padding-top: 1.5rem;
        margin-top: 1.5rem;
        font-size: 0.9rem;
        color: var(--color-text-muted);
    }

    .callout-item {
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
    }

    .callout-item svg {
        flex-shrink: 0;
        color: var(--color-primary);
        margin-top: 0.15rem;
    }

    .recaptcha-container {
        margin: 1.5rem 0;
    }

    .error-container {
        background-color: rgba(239, 68, 68, 0.05);
        border: 1px solid rgba(239, 68, 68, 0.15);
        color: var(--color-error);
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
        font-size: 0.95rem;
        display: none;
    }
</style>
