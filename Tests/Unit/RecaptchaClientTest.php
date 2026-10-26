<?php

declare(strict_types=1);

namespace MauticPlugin\AivieRecaptchaBundle\Tests\Unit;

use Mautic\FormBundle\Entity\Field;
use MauticPlugin\AivieRecaptchaBundle\Integration\ConfigInterface;
use MauticPlugin\AivieRecaptchaBundle\Service\RecaptchaClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RecaptchaClientTest extends TestCase
{
    private Field $field;

    protected function setUp(): void
    {
        parent::setUp();

        $this->field = new Field();
    }

    public function testVerifyWhenPluginIsNotInstalled(): void
    {
        $test = $this->createRecaptchaClient()->verify('', $this->field);
        $this->assertFalse($test);
    }

    private function createRecaptchaClient(): RecaptchaClient
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('getSiteKey')->willReturn('');
        $config->method('getProjectId')->willReturn('');

        return new RecaptchaClient($config, $this->createMock(LoggerInterface::class));
    }
}
