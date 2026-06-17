@extends('layouts.form-iframe')

@section('title', $form->getSeoTitle())
@section('seo_description', $form->getSeoDescription())

@section('styles')
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
@if($form->getRecaptchaEnabled())
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endif
@endsection

@section('content')
<div class="form-container">
    <!-- Server/Client Error Container -->
    <div id="errorBox" class="error-container"></div>

    <!-- MAIN REGISTRATION FORM -->
    <form id="signupForm" class="space-y-4">
        @csrf

        <div class="form-group">
            <label class="form-label" for="firstName">
                First Name <span class="required-star">*</span>
            </label>
            <input type="text" id="firstName" name="first_name" required class="form-control" autocomplete="given-name">
        </div>

        <div class="form-group">
            <label class="form-label" for="lastName">
                Last Name <span class="required-star">*</span>
            </label>
            <input type="text" id="lastName" name="last_name" required class="form-control" autocomplete="family-name">
        </div>

        <div class="form-group">
            <label class="form-label" for="email">
                Work Email <span class="required-star">*</span>
            </label>
            <input type="email" id="email" name="email" required class="form-control" autocomplete="email">
        </div>

        <div class="form-group">
            <label class="form-label" for="organization">Organization</label>
            <input type="text" id="organization" name="organization" class="form-control">
        </div>

        <div class="form-group">
            <label class="form-label" for="jobTitle">Job Title</label>
            <input type="text" id="jobTitle" name="job_title" class="form-control">
        </div>

        <div class="form-group">
            <label class="form-label" for="country">Country</label>
            <select id="country" name="country" class="form-control">
                <option value="" selected>Select</option>
                @foreach ($countries as $code => $name)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="stageInAIAdoption">Stage in AI Adoption</label>
            <select id="stageInAIAdoption" name="stage_in_ai_adoption" class="form-control">
                <option value="" selected>Select</option>
                <option value="just-curious">Just curious</option>
                <option value="specific-need">I have a specific need</option>
                <option value="already-using">Already using AI</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="interestInDrupalAI">Interest in Drupal AI</label>
            <input type="text" id="interestInDrupalAI" name="interest_in_drupal_ai" class="form-control">
        </div>

        <div class="form-group">
            <label class="form-label" for="dataRegion">
                Data Region <span class="required-star">*</span>
            </label>
            <select id="dataRegion" name="data_region" required class="form-control">
                <option value="" disabled selected>Select</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}">{{ $region->name }}</option>
                @endforeach
            </select>
        </div>

        <!-- Experience dropdown - Populated dynamically based on region selection -->
        <div class="form-group" id="appContainer" style="display: none;">
            <label class="form-label" for="selectedApp">
                Experience <span class="required-star">*</span>
            </label>
            <select id="selectedApp" name="trial_app" class="form-control">
                <option value="" disabled selected>Select</option>
            </select>
        </div>

        <div class="disclaimer-text">
            <p>
                By participating in this trial, you acknowledge that you have read, understood, and
                agree to the <a href="/terms-and-conditions" target="_blank">terms of this consent</a> 
                and you grant permission to the Drupal Association and <a href="https://amazee.ai" target="_blank">amazee.ai</a> 
                to <a href="https://docs.amazee.io/policy/acceptable-use" target="_blank">share your personal information</a> 
                for the purposes of facilitating your participation in the trial and the Purpose set forth therein.
            </p>
            <p>
                By participating in this trial, you acknowledge that you have read, understood, and
                agree to the privacy policies of both the <a href="https://www.drupal.org/privacy" target="_blank">Drupal Association</a> 
                and our Trial Partner, <a href="https://amazee.ai" target="_blank">amazee.ai</a>.
            </p>
        </div>

        @if($form->getRecaptchaEnabled())
        <!-- Google reCAPTCHA Block -->
        <div class="recaptcha-container">
            <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
        </div>
        @endif

        <button type="submit" id="btnSubmit" class="btn-submit">Sign Up Now</button>
    </form>

    <!-- PROVISIONING / LOADING SCREEN -->
    <div id="loadingScreen" class="status-screen" style="display: none;">
        <div class="spinner-container">
            <div class="spinner"></div>
        </div>
        <div class="status-title">Creating Your AI Playground!</div>
        <div class="status-message">
            <p>Your demo environment is being built right now! This typically takes 2-3 minutes.</p>
            <p style="margin-top: 1rem; font-size: 0.95rem;">You'll receive an email confirmation once everything is ready. We can't wait for you to experience what's possible!</p>
        </div>
    </div>

    <!-- SUCCESS / CREDENTIALS CARD -->
    <div id="successScreen" class="congratulations-card" style="display: none;">
        <div class="success-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        <h3 class="congratulations-heading">
            <span>Congratulations!</span>You are in!
        </h3>
        <p style="font-size: 1.05rem; margin-bottom: 1rem; font-weight: 500; color: var(--color-primary);">
            Your free Drupal AI demo environment is configured and ready to explore.
        </p>
        <p style="font-size: 0.95rem; color: var(--color-text-muted); margin-bottom: 1.5rem;">
            Log in and discover what's possible with Drupal CMS and AI.
        </p>

        <a id="appLoginButton" class="btn-login" href="#" target="_blank">Log In Now</a>

        <div class="credentials-box">
            <h4>Your login details:</h4>
            <ul class="credentials-list">
                <li>
                    <span class="credentials-label">Username:</span>
                    <span class="credentials-value" id="credUsername"></span>
                </li>
                <li>
                    <span class="credentials-label">Password:</span>
                    <span class="credentials-value" id="credPassword"></span>
                </li>
                <li>
                    <span class="credentials-label">URL:</span>
                    <span class="credentials-value"><a id="credUrl" href="#" target="_blank" style="color: var(--color-primary); font-weight: 500; text-decoration: none;"></a></span>
                </li>
            </ul>
        </div>

        <div class="callout-box">
            <div class="callout-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                <span><strong>Check your inbox</strong> for helpful insights into our AI services.</span>
            </div>
            <div class="callout-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <span>
                    <strong>Need some help?</strong> Our friendly support team is standing by at: 
                    <a href="mailto:ai.support@amazee.io" style="color: var(--color-primary); font-weight: 600; text-decoration: none;">ai.support@amazee.io</a>
                </span>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const regionsData = @json($regionsData);
        const recaptchaEnabled = @json($form->getRecaptchaEnabled());

        const regionSelect = document.getElementById('dataRegion');
        const appSelect = document.getElementById('selectedApp');
        const appContainer = document.getElementById('appContainer');
        const errorBox = document.getElementById('errorBox');
        const signupForm = document.getElementById('signupForm');
        const loadingScreen = document.getElementById('loadingScreen');
        const successScreen = document.getElementById('successScreen');
        const btnSubmit = document.getElementById('btnSubmit');

        // Dynamic Region -> Apps Dropdown mapping
        regionSelect.addEventListener('change', function() {
            const selectedRegionId = this.value;
            const matchedRegion = regionsData.find(r => r.id == selectedRegionId);

            // Clear previous items
            appSelect.innerHTML = '<option value="" disabled selected>Select</option>';

            if (matchedRegion && matchedRegion.apps.length > 0) {
                matchedRegion.apps.forEach(app => {
                    const option = document.createElement('option');
                    option.value = app.uuid;
                    option.textContent = app.name;
                    appSelect.appendChild(option);
                });
                appContainer.style.display = 'flex';
                appSelect.required = true;
            } else {
                appContainer.style.display = 'none';
                appSelect.required = false;
            }
        });

        // Intercept form submission and run server-side handshake
        signupForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Clear errors
            errorBox.style.display = 'none';
            errorBox.textContent = '';

            const formData = new FormData(signupForm);

            if (recaptchaEnabled) {
                const recaptchaResponse = grecaptcha.getResponse();
                if (!recaptchaResponse) {
                    showError('Please verify that you are not a robot.');
                    return;
                }
                formData.append('recaptcha', recaptchaResponse);
            }

            btnSubmit.disabled = true;

            // POST to local FormController submit endpoint
            fetch('{{ route("forms.submit", $form->getSlug()) }}', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: formData
            })
            .then(async response => {
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to submit form');
                }
                return data;
            })
            .then(data => {
                if (data.status === 'pending') {
                    // Transition to loading screen
                    signupForm.style.display = 'none';
                    loadingScreen.style.display = 'block';

                    // Start status polling
                    startPolling(data.id);
                } else {
                    showError(data.message || 'An unexpected status was returned.');
                    btnSubmit.disabled = false;
                }
            })
            .catch(err => {
                showError(err.message || 'An error occurred. Please try again.');
                btnSubmit.disabled = false;
                if (recaptchaEnabled && typeof grecaptcha !== 'undefined') {
                    grecaptcha.reset();
                }
            });
        });

        function showError(msg) {
            errorBox.textContent = msg;
            errorBox.style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function getSafeUrl(urlStr) {
            try {
                const parsed = new URL(urlStr);
                if (parsed.protocol === 'http:' || parsed.protocol === 'https:') {
                    return parsed.href;
                }
            } catch (e) {
                // If it fails parsing as absolute, check if it is relative
                if (urlStr.startsWith('/') && !urlStr.startsWith('//')) {
                    return urlStr;
                }
            }
            return '#';
        }

        // Poll registration status until successful provisioning
        function startPolling(registrationId) {
            const pollUrl = `/api/register/${registrationId}`;
            const pollInterval = 5000; // 5 seconds
            const timeout = 420000; // 7 minutes
            const startTime = Date.now();

            function checkStatus() {
                fetch(pollUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to fetch status');
                    }
                    return response.json();
                })
                .then(data => {
                    const status = data.status;

                    if (status === 'success' || status === 'failed') {
                        if (status === 'success' && data.result_data && data.result_data.app_url) {
                            // Populate success card
                            document.getElementById('credUsername').textContent = data.result_data.app_admin_username || 'admin';
                            document.getElementById('credPassword').textContent = data.result_data.app_admin_password || '********';
                            
                            const safeAppUrl = getSafeUrl(data.result_data.app_url);

                            const credUrlLink = document.getElementById('credUrl');
                            credUrlLink.href = safeAppUrl;
                            credUrlLink.textContent = data.result_data.app_url;

                            const loginBtn = document.getElementById('appLoginButton');
                            loginBtn.href = safeAppUrl;

                            // Show success screen
                            loadingScreen.style.display = 'none';
                            successScreen.style.display = 'block';
                        } else {
                            renderSnagScreen();
                        }
                    } else if (Date.now() - startTime >= timeout) {
                        renderDelayScreen();
                    } else {
                        // Keep polling
                        setTimeout(checkStatus, pollInterval);
                    }
                })
                .catch(err => {
                    console.error('Polling error:', err);
                    // Continue polling despite minor network hiccups, but still respect the timeout
                    if (Date.now() - startTime < timeout) {
                        setTimeout(checkStatus, pollInterval);
                    } else {
                        renderDelayScreen();
                    }
                });
            }

            // Fire first poll
            setTimeout(checkStatus, pollInterval);
        }

        function renderSnagScreen() {
            loadingScreen.style.display = 'none';
            signupForm.style.display = 'none';
            
            const snagHtml = `
                <div class="status-screen" style="animation: fadeIn 0.4s ease-out;">
                    <div style="color: var(--color-accent); font-size: 3rem; margin-bottom: 1.5rem;">⚠️</div>
                    <div class="status-title">We hit a snag!</div>
                    <div class="status-message">
                        <p>We couldn't process your form submission. This is a temporary issue on our side, not yours.</p>
                        <p style="margin-top: 1rem;">Try refreshing your page to submit again, or email us at <a class="text-[#12285f]" href="mailto:ai.support@amazee.io">ai.support@amazee.io</a> for assistance.</p>
                    </div>
                </div>
            `;
            document.querySelector('.form-container').innerHTML = snagHtml;
        }

        function renderDelayScreen() {
            loadingScreen.style.display = 'none';
            signupForm.style.display = 'none';
            
            const delayHtml = `
                <div class="status-screen" style="animation: fadeIn 0.4s ease-out;">
                    <div style="color: var(--color-primary); font-size: 3rem; margin-bottom: 1.5rem;">⏱️</div>
                    <div class="status-title">We're experiencing a brief delay</div>
                    <div class="status-message">
                        <p>Thanks for your patience. We’re provisioning your site in the background and will email you the details as soon as everything is ready.</p>
                        <p style="margin-top: 1rem;">Haven't received anything? Contact us at <a href="mailto:ai.support@amazee.io">ai.support@amazee.io</a> and we'll help you get started right away.</p>
                    </div>
                </div>
            `;
            document.querySelector('.form-container').innerHTML = delayHtml;
        }
    });
</script>
@endsection
