<?php

declare(strict_types=1);

namespace MauticPlugin\AivieRecaptchaBundle\Tests\Unit\EventListener;

use Mautic\FormBundle\Entity\Field;
use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\Event\ValidationEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\AivieRecaptchaBundle\EventListener\FormSubscriber;
use MauticPlugin\AivieRecaptchaBundle\Form\Type\RecaptchaType;
use MauticPlugin\AivieRecaptchaBundle\Integration\ConfigInterface;
use MauticPlugin\AivieRecaptchaBundle\RecaptchaEvents;
use MauticPlugin\AivieRecaptchaBundle\Service\RecaptchaClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormSubscriberTest extends TestCase
{
    private $eventDispatcher;
    private $config;
    private $recaptchaClient;
    private $leadModel;
    private $translator;
    private $logger;
    private FormSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->config          = $this->createMock(ConfigInterface::class);
        $this->recaptchaClient = $this->createMock(RecaptchaClient::class);
        $this->leadModel       = $this->createMock(LeadModel::class);
        $this->translator      = $this->createMock(TranslatorInterface::class);
        $this->logger          = $this->createMock(LoggerInterface::class);

        $this->subscriber = new FormSubscriber(
            $this->eventDispatcher,
            $this->config,
            $this->recaptchaClient,
            $this->leadModel,
            $this->translator,
            $this->logger,
        );
    }

    public function testSubscribedEvents(): void
    {
        $this->assertEquals(
            [
                FormEvents::FORM_ON_BUILD         => ['onFormBuild', 0],
                RecaptchaEvents::ON_FORM_VALIDATE => ['onFormValidate', 0],
                FormEvents::FORM_ON_SUBMIT        => ['onFormSubmit', 0],
            ],
            FormSubscriber::getSubscribedEvents()
        );
    }

    public function testOnFormBuildAddsDisabledFieldWhenNotConfigured(): void
    {
        $event = new FormBuilderEvent($this->translator);

        $this->config->method('isPublished')->willReturn(true);
        $this->config->method('isConfigured')->willReturn(false);

        $this->subscriber->onFormBuild($event);

        $options = $event->getFormFields()['plugin.recaptcha'];
        self::assertSame('@AivieRecaptcha/Field/recaptcha.html.twig', $options['template']);
        self::assertFalse($options['isEnabled']);
        self::assertSame('', $options['siteKey']);
        self::assertSame('', $options['tagAction']);
        self::assertSame(RecaptchaEvents::ON_FORM_VALIDATE, $event->getValidators()['plugin.recaptcha']);
    }

    public function testOnFormBuildAddsDisabledFieldWhenNotPublished(): void
    {
        $event = new FormBuilderEvent($this->translator);

        $this->config->method('isPublished')->willReturn(false);
        $this->config->expects($this->never())->method('isConfigured');

        $this->subscriber->onFormBuild($event);

        $options = $event->getFormFields()['plugin.recaptcha'];
        self::assertSame('@AivieRecaptcha/Field/recaptcha.html.twig', $options['template']);
        self::assertFalse($options['isEnabled']);
        self::assertSame('', $options['siteKey']);
        self::assertSame('', $options['tagAction']);
        self::assertSame(RecaptchaEvents::ON_FORM_VALIDATE, $event->getValidators()['plugin.recaptcha']);
    }

    public function testOnFormBuildAddsRecaptchaFieldWhenConfigured(): void
    {
        $event = new FormBuilderEvent($this->translator);

        $this->config->method('isPublished')->willReturn(true);
        $this->config->method('isConfigured')->willReturn(true);
        $this->config->method('getSiteKey')->willReturn('test_site_key');
        $this->recaptchaClient->method('getTagActionName')->willReturn('test_tag_action');

        $this->subscriber->onFormBuild($event);

        $options = $event->getFormFields()['plugin.recaptcha'];
        self::assertSame(RecaptchaType::class, $options['formType']);
        self::assertSame('@AivieRecaptcha/Field/recaptcha.html.twig', $options['template']);
        self::assertTrue($options['isEnabled']);
        self::assertSame('test_site_key', $options['siteKey']);
        self::assertSame('test_tag_action', $options['tagAction']);
        self::assertSame(RecaptchaEvents::ON_FORM_VALIDATE, $event->getValidators()['plugin.recaptcha']);
    }

    public function testOnFormValidateSuccess(): void
    {
        $event = new ValidationEvent(new Field(), '');

        $this->config->method('isPublished')->willReturn(true);
        $this->config->method('isConfigured')->willReturn(true);
        $this->recaptchaClient->method('verify')->willReturn(true);
        $this->subscriber->onFormValidate($event);

        self::assertTrue($event->isValid());
    }

    public function testOnFormValidateFailure(): void
    {
        $event = new ValidationEvent(new Field(), '');

        $this->config->method('isPublished')->willReturn(true);
        $this->config->method('isConfigured')->willReturn(true);
        $this->recaptchaClient->method('verify')->willReturn(false);
        $this->translator->method('trans')
            ->with('mautic.integration.recaptcha.failure_message')
            ->willReturn('reCAPTCHA was not successful.');

        $this->subscriber->onFormValidate($event);

        self::assertFalse($event->isValid());
        self::assertSame('reCAPTCHA was not successful.', $event->getInvalidReason());
    }

    public function testLeadPostSaveListener(): void
    {
        $lead      = $this->createMock('Mautic\LeadBundle\Entity\Lead');
        $leadEvent = new LeadEvent($lead, true);

        $this->leadModel->expects($this->never())
            ->method('deleteEntity')
            ->with($lead);

        $this->eventDispatcher->method('addListener')
            ->with(LeadEvents::LEAD_POST_SAVE, $this->isType('callable'), -255)
            ->willReturnCallback(function ($eventName, $callback) use ($leadEvent) {
                $callback($leadEvent);
            });

        $event = new ValidationEvent(new Field(), '');

        $this->config->method('isPublished')->willReturn(true);
        $this->config->method('isConfigured')->willReturn(true);
        $this->recaptchaClient->method('verify')->willReturn(true);

        $this->subscriber->onFormValidate($event);
    }
}
