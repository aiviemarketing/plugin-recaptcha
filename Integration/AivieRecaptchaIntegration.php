<?php

declare(strict_types=1);

namespace MauticPlugin\AivieRecaptchaBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\ConfigurationTrait;

// Config/config.php explicitly registers the basic integration. Support subclasses must not
// inherit BasicInterface and be auto-tagged as additional basic integrations.
class AivieRecaptchaIntegration extends BasicIntegration
{
    use ConfigurationTrait;

    public const NAME             = 'AivieRecaptcha';
    public const DISPLAY_NAME     = 'reCAPTCHA';
    public const SITE_KEY_NAME    = 'site_key';
    public const PROJECT_ID_NAME  = 'projectId';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDisplayName(): string
    {
        return self::DISPLAY_NAME;
    }

    public function getIcon(): string
    {
        return 'plugins/AivieRecaptchaBundle/Assets/img/recaptcha.png';
    }
}
