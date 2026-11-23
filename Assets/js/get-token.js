/**
 * Communicate with Google reCAPTCHA Enterprise to get a token and add the token 
 * to the form field to be validated (assessed) in the backend after submitting.
 * Has to happen within 2 minutes after the token is generated.
 *
 * @param {Event} event Form submit event
 * @param {string} siteKey reCAPTCHA site key
 * @param {string} tagAction reCAPTCHA tag action
 * @link https://cloud.google.com/recaptcha/docs/create-assessment-website
 */
function getTokenAndValidateRecaptcha(formId, siteKey, tagAction) {
    const recaptchaTokenClass = 'mautic-recaptcha-token';
    const form = document.getElementById(formId);
    if (!form) {
        throw new Error(`Form with id '${formId}' not found`);
    }
    const inputElement = form.querySelector(`input.${recaptchaTokenClass}`);
    if (!inputElement) {
        throw new Error(`No input element found with class '${recaptchaTokenClass}'`);
    }
    grecaptcha.enterprise.ready(async () => {
        const token = await grecaptcha.enterprise.execute(siteKey, { action: tagAction });

        inputElement.value = token;
        console.debug('Recaptcha: Token received and added to form field. Refreshing token in 110 seconds.');
    });
}

/**
 * 
 * @param {string} formId Mautic form name (alias)
 * @param {string} siteKey reCAPTCHA site key
 * @param {string} tagAction reCAPTCHA tag action
 */
function validateFormByRecaptcha(formId, siteKey, tagAction) {
    
    getTokenAndValidateRecaptcha(formId, siteKey, tagAction);
    // This code sets up a recurring function call using setInterval.
    // Every 110 seconds, the function getTokenAndValidateRecaptcha
    // is called with the arguments event, siteKey, and tagAction.
    const startTime = Date.now();
    const intervalId = setInterval(function () {
        const elapsedTime = Date.now() - startTime;
        if (elapsedTime >= 3600000) { // 1 hour in milliseconds = 3600000
            clearInterval(intervalId);
            console.debug('Recaptcha: Interval cleared after 1 hour.');
            return;
        }
        getTokenAndValidateRecaptcha(formId, siteKey, tagAction);
    }, 110000);

};
