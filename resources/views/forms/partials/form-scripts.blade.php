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
                        } else if (status === 'success' && data.result_data && data.result_data.result_type === 'trial_registered') {
                            // Terminal success without an app URL: the registration
                            // was captured and provisioning/details follow by email.
                            renderRegisteredScreen();
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

        function renderRegisteredScreen() {
            loadingScreen.style.display = 'none';
            signupForm.style.display = 'none';

            const registeredHtml = `
                <div class="status-screen" style="animation: fadeIn 0.4s ease-out;">
                    <div style="color: var(--color-primary); font-size: 3rem; margin-bottom: 1.5rem;">✅</div>
                    <div class="status-title">You're registered!</div>
                    <div class="status-message">
                        <p>We've received your trial registration and will email you your access details as soon as your trial is ready.</p>
                        <p style="margin-top: 1rem;">Questions? Contact us at <a href="mailto:ai.support@amazee.io">ai.support@amazee.io</a>.</p>
                    </div>
                </div>
            `;
            document.querySelector('.form-container').innerHTML = registeredHtml;
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
