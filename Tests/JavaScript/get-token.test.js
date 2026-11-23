const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

test('writes concurrent reCAPTCHA responses to their matching form inputs', async () => {
    const inputs = {
        formA: { value: '' },
        formB: { value: '' },
    };
    const readyCallbacks = [];
    const tokenResolvers = {};
    const context = {
        console: { debug() {} },
        document: {
            getElementById(formId) {
                const input = inputs[formId];

                return input ? { querySelector: () => input } : null;
            },
        },
        grecaptcha: {
            enterprise: {
                execute(siteKey) {
                    return new Promise((resolve) => {
                        tokenResolvers[siteKey] = resolve;
                    });
                },
                ready(callback) {
                    readyCallbacks.push(callback);
                },
            },
        },
    };

    vm.createContext(context);
    const scriptPath = path.resolve(__dirname, '../../Assets/js/get-token.js');
    vm.runInContext(fs.readFileSync(scriptPath, 'utf8'), context);

    context.getTokenAndValidateRecaptcha('formA', 'siteKeyA', 'mautic_form');
    context.getTokenAndValidateRecaptcha('formB', 'siteKeyB', 'mautic_form');

    const pendingCallbacks = readyCallbacks.map((callback) => callback());
    tokenResolvers.siteKeyB('tokenB');
    tokenResolvers.siteKeyA('tokenA');
    await Promise.all(pendingCallbacks);

    assert.equal(inputs.formA.value, 'tokenA');
    assert.equal(inputs.formB.value, 'tokenB');
});
