<?php

declare(strict_types=1);

namespace MauticPlugin\AivieRecaptchaBundle\Tests\Unit;

use Mautic\FormBundle\Entity\Field;
use Mautic\FormBundle\Event\ValidationEvent;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\AivieRecaptchaBundle\EventListener\FormSubscriber;
use MauticPlugin\AivieRecaptchaBundle\Integration\AivieRecaptchaIntegration;
use MauticPlugin\AivieRecaptchaBundle\Integration\ConfigInterface;
use MauticPlugin\AivieRecaptchaBundle\Service\RecaptchaClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class IntegrationTest extends TestCase
{
    protected AivieRecaptchaIntegration $integration;

    protected IntegrationsHelper $integrationsHelper;

    protected EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->integration = $this->getMockBuilder(AivieRecaptchaIntegration::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->integrationsHelper = $this->getMockBuilder(IntegrationsHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->integrationsHelper
            ->method('getIntegration')
            ->willReturn($this->integration);
    }

    public function testOnFormValidate(): void
    {
        /** @var LeadModel $leadModel */
        $leadModel = $this->getMockBuilder(LeadModel::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var MockObject|ValidationEvent $validationEvent */
        $validationEvent = $this->getMockBuilder(ValidationEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $translator = $this->createMock(TranslatorInterface::class);

        $validationEvent
            ->method('getValue')
            ->willReturn('any-value-should-work');
        $validationEvent
            ->expects($this->never())
            ->method('failedValidation');
        $validationEvent
            ->method('getValue')
            ->willReturn('test');
        $validationEvent
            ->method('getField')
            ->willReturn(new Field());

        $formSubscriber =  new FormSubscriber(
            $this->eventDispatcher,
            $this->createMock(ConfigInterface::class),
            $this->createMock(RecaptchaClient::class),
            $this->createMock(LeadModel::class),
            $this->createMock(TranslatorInterface::class),
            $this->createMock(LoggerInterface::class),
        );
        $formSubscriber->onFormValidate($validationEvent);
    }
}
