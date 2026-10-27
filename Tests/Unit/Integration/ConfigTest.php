<?php

declare(strict_types=1);

namespace MauticPlugin\AivieRecaptchaBundle\Tests\Unit\Integration;

use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\IntegrationsBundle\Integration\Interfaces\IntegrationInterface;
use Mautic\PluginBundle\Entity\Integration;
use MauticPlugin\AivieRecaptchaBundle\Integration\AivieRecaptchaIntegration;
use MauticPlugin\AivieRecaptchaBundle\Integration\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ConfigTest extends TestCase
{
    private MockObject $integrationsHelper;
    private MockObject $integration;
    private MockObject $integrationEntity;
    private LoggerInterface|MockObject $logger;
    private Config $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->integrationsHelper = $this->createMock(IntegrationsHelper::class);
        $this->integration        = $this->createMock(IntegrationInterface::class);
        $this->integrationEntity  = $this->createMock(Integration::class);
        $this->logger             = $this->createMock(LoggerInterface::class);

        $this->integrationsHelper
            ->method('getIntegration')
            ->with(AivieRecaptchaIntegration::NAME)
            ->willReturn($this->integration);

        $this->integration
            ->method('getIntegrationConfiguration')
            ->willReturn($this->integrationEntity);

        // Clear both getenv and $_ENV to avoid test contamination
        putenv('GC_RECAPTCHA_SITE_KEY'); // unsets
        unset($_ENV['GC_RECAPTCHA_SITE_KEY']);
        putenv('GC_RECAPTCHA_PROJECT_ID'); // unsets
        unset($_ENV['GC_RECAPTCHA_PROJECT_ID']);
        putenv('PROJECT'); // unsets
        unset($_ENV['PROJECT']);
        putenv('GOOGLE_CLOUD_PROJECT'); // unsets
        unset($_ENV['GOOGLE_CLOUD_PROJECT']);

        $this->config = new Config($this->integrationsHelper, $this->logger);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Remove the environment variable.
        putenv('GC_RECAPTCHA_SITE_KEY');
        putenv('GC_RECAPTCHA_PROJECT_ID');
        putenv('PROJECT');
        putenv('GOOGLE_CLOUD_PROJECT');
    }

    public function testIsPublishedThrowsException(): void
    {
        $this->integrationsHelper
            ->method('getIntegration')
            ->willThrowException(new IntegrationNotFoundException());

        $this->assertEmpty($this->config->isPublished());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataForPluginIsPublished')]
    public function testIfPluginIsPublished(bool $setting, bool $expected): void
    {
        $this->integrationEntity
            ->method('getIsPublished')
            ->willReturn($setting);

        $this->assertSame($expected, $this->config->isPublished());
    }

    /**
     * @return iterable<string, bool[]>
     */
    public static function dataForPluginIsPublished(): iterable
    {
        yield 'Published' => [true, true];

        yield 'Unpublished' => [false, false];
    }

    public function testIsConfiguredReturnsTrueWhenSiteKeyIsPresent(): void
    {
        // Simulate environment variable
        putenv('GC_RECAPTCHA_SITE_KEY=test_site_key');
        putenv('GC_RECAPTCHA_PROJECT_ID=test_project');

        $this->assertTrue($this->config->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenSiteKeyIsEmpty(): void
    {
        // Remove the env variable
        putenv('GC_RECAPTCHA_SITE_KEY');
        putenv('GC_RECAPTCHA_PROJECT_ID');
        putenv('PROJECT');
        putenv('GOOGLE_CLOUD_PROJECT');

        // Expect error logging
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Recaptcha is not configured properly - check your integration settings or ENV variables');

        $this->assertFalse($this->config->isConfigured());
    }

    public function testGetSiteKeyReturnsCorrectValue(): void
    {
        putenv('GC_RECAPTCHA_SITE_KEY=test_site_key');
        $this->assertSame('test_site_key', $this->config->getSiteKey());
    }

    public function testGetSiteKeyReturnsEmptyWhenNotSet(): void
    {
        putenv('GC_RECAPTCHA_SITE_KEY');
        $this->assertSame('', $this->config->getSiteKey());
    }

    public function testGetProjectReturnsCorrectValueFromRecaptchaEnvironment(): void
    {
        putenv('GC_RECAPTCHA_PROJECT_ID=test_recaptcha_project');

        $this->assertSame('test_recaptcha_project', $this->config->getProjectId());
    }

    public function testGetProjectReturnsCorrectValueFromProjectEnvironment(): void
    {
        putenv('PROJECT=test_project');

        $this->assertSame('test_project', $this->config->getProjectId());
    }

    public function testGetProjectReturnsCorrectValueFromLegacyEnvironment(): void
    {
        putenv('GOOGLE_CLOUD_PROJECT=test_project');
        $this->assertSame('test_project', $this->config->getProjectId());
    }

    public function testGetProjectReturnsEmptyWhenNotSet(): void
    {
        putenv('GC_RECAPTCHA_PROJECT_ID');
        putenv('PROJECT');
        putenv('GOOGLE_CLOUD_PROJECT');
        $this->assertSame('', $this->config->getProjectId());
    }

    public function testRecaptchaProjectEnvironmentTakesPrecedenceOverProjectEnvironment(): void
    {
        putenv('GC_RECAPTCHA_PROJECT_ID=recaptcha_project');
        putenv('PROJECT=infrastructure_project');

        $this->assertSame('recaptcha_project', $this->config->getProjectId());
    }

    public function testProjectEnvironmentTakesPrecedenceOverLegacyEnvironment(): void
    {
        putenv('PROJECT=infrastructure_project');
        putenv('GOOGLE_CLOUD_PROJECT=legacy_project');

        $this->assertSame('infrastructure_project', $this->config->getProjectId());
    }

    public function testApiKeysOverrideEnv(): void
    {
        $this->integrationEntity
            ->method('getApiKeys')
            ->willReturn([
                AivieRecaptchaIntegration::SITE_KEY_NAME    => 'ui_site_key',
                AivieRecaptchaIntegration::PROJECT_ID_NAME  => 'ui_project',
            ]);

        putenv('GC_RECAPTCHA_SITE_KEY=env_site_key');
        putenv('GC_RECAPTCHA_PROJECT_ID=env_recaptcha_project');
        putenv('PROJECT=env_infrastructure_project');
        putenv('GOOGLE_CLOUD_PROJECT=env_project');

        $this->assertSame('ui_site_key', $this->config->getSiteKey());
        $this->assertSame('ui_project', $this->config->getProjectId());
        $this->assertTrue($this->config->isConfigured());
    }
}
