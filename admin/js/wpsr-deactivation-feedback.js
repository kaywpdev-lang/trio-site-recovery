(function () {
    'use strict';

    function init() {
        if (typeof wpsrFeedback === 'undefined') {
            return;
        }

        const row = document.querySelector('tr[data-plugin="' + CSS.escape(wpsrFeedback.pluginFile) + '"]');
        const deactivateLink = row ? row.querySelector('.deactivate a') : null;

        if (!deactivateLink) {
            return;
        }

        document.body.insertAdjacentHTML('beforeend', `
            <div id="wpsr-feedback-modal" class="wpsr-feedback-modal" aria-hidden="true">
                <div class="wpsr-feedback-overlay" data-wpsr-close></div>
                <div class="wpsr-feedback-dialog" role="dialog" aria-modal="true" aria-labelledby="wpsr-feedback-title">
                    <button type="button" class="wpsr-feedback-close" data-wpsr-close aria-label="Close">&times;</button>
                    <h2 id="wpsr-feedback-title">Why are you deactivating Trio Site Recovery?</h2>
                    <p>Your feedback helps us improve the plugin.</p>
                    <form id="wpsr-feedback-form">
                        <label><input type="radio" name="reason" value="setup_difficult"> I couldn't understand the setup</label>
                        <label><input type="radio" name="reason" value="not_working"> The plugin didn't work</label>
                        <label><input type="radio" name="reason" value="missing_feature"> A feature is missing</label>
                        <label><input type="radio" name="reason" value="found_alternative"> I found another plugin</label>
                        <label><input type="radio" name="reason" value="temporary"> Temporary deactivation</label>
                        <label><input type="radio" name="reason" value="other"> Other</label>
                        <textarea name="details" rows="4" placeholder="Please share more details..."></textarea>
                        <div class="wpsr-feedback-error" hidden></div>
                        <div class="wpsr-feedback-actions">
                            <button type="button" class="button" id="wpsr-skip-feedback">Skip and deactivate</button>
                            <button type="submit" class="button button-primary" id="wpsr-submit-feedback">Submit and deactivate</button>
                        </div>
                    </form>
                </div>
            </div>
        `);

        const modal = document.getElementById('wpsr-feedback-modal');
        const form = document.getElementById('wpsr-feedback-form');
        const errorBox = modal.querySelector('.wpsr-feedback-error');
        let deactivateUrl = deactivateLink.href;

        deactivateLink.addEventListener('click', function (event) {
            event.preventDefault();
            deactivateUrl = deactivateLink.href;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        });

        modal.addEventListener('click', function (event) {
            if (event.target.closest('[data-wpsr-close]')) {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
            }

            if (event.target.closest('#wpsr-skip-feedback')) {
                window.location.href = deactivateUrl;
            }
        });

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const reason = form.querySelector('input[name="reason"]:checked');

            if (!reason) {
                errorBox.textContent = 'Please select a reason.';
                errorBox.hidden = false;
                return;
            }

            const button = document.getElementById('wpsr-submit-feedback');
            button.disabled = true;
            button.textContent = 'Submitting...';

            const body = new FormData();
            body.append('action', 'wpsr_save_deactivation_feedback');
            body.append('nonce', wpsrFeedback.nonce);
            body.append('reason', reason.value);
            body.append('details', form.elements.details.value);

            try {
                const response = await fetch(wpsrFeedback.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: body
                });
                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.data && result.data.message ? result.data.message : 'Unable to save feedback.');
                }

                window.location.href = deactivateUrl;
            } catch (error) {
                errorBox.textContent = error.message;
                errorBox.hidden = false;
                button.disabled = false;
                button.textContent = 'Submit and deactivate';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
