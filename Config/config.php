<?php

declare(strict_types=1);

use MauticPlugin\AivieRecaptchaBundle\Integration\AivieRecaptchaIntegration;
use MauticPlugin\AivieRecaptchaBundle\Integration\Support\ConfigSupport;

return [
    'name'        => 'reCAPTACHA',
    'description' => 'Enables reCAPTCHA integration.',
    'version'     => '7.2.5',
    'author'      => 'Aivie',
    'routes'      => [],
    'menu'        => [],
    'services'    => [
        'integrations' => [
            'mautic.integration.aivierecaptcha' => [
                'class'     => AivieRecaptchaIntegration::class,
                'tags'      => [
                    'mautic.integration',
                    'mautic.basic_integration',
                ],
            ],
            // Provides the form types to use for the configuration UI
            'mautic.integration.aivierecaptcha.configuration' => [
                'class'     => ConfigSupport::class,
                'arguments' => [],
                'tags'      => [
                    'mautic.config_integration',
                ],
            ],
        ],
    ],
    'parameters' => [],
];
