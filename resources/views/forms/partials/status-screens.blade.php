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
