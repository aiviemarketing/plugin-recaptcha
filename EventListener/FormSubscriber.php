<?php

declare(strict_types=1);

namespace MauticPlugin\AivieRecaptchaBundle\EventListener;

use Mautic\CoreBundle\Exception\BadConfigurationException;
use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\Event\ValidationEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\AivieRecaptchaBundle\Form\Type\RecaptchaType;
use MauticPlugin\AivieRecaptchaBundle\Integration\ConfigInterface;
use MauticPlugin\AivieRecaptchaBundle\RecaptchaEvents;
use MauticPlugin\AivieRecaptchaBundle\Service\RecaptchaClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ConfigInterface $config,
        private RecaptchaClient $recaptchaClient,
        private LeadModel $leadModel,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_ON_BUILD         => ['onFormBuild', 0],
            FormEvents::FORM_ON_SUBMIT        => ['onFormSubmit', 0],
            RecaptchaEvents::ON_FORM_VALIDATE => ['onFormValidate', 0],
        ];
    }

    /**
     * @throws BadConfigurationException
     */
    public function onFormBuild(FormBuilderEvent $event): void
    {
        $isPublished  = $this->config->isPublished();
        $isConfigured = $isPublished && $this->config->isConfigured();

        $event->addFormField('plugin.recaptcha', [
            'label'          => 'mautic.plugin.actions.recaptcha',
            'formType'       => RecaptchaType::class,
            'template'       => '@AivieRecaptcha/Field/recaptcha.html.twig',
            'builderOptions' => [
                'addLeadFieldList' => false,
                'addIsRequired'    => false,
                'addDefaultValue'  => false,
                'addSaveResult'    => true,
            ],
            'isEnabled' => $isConfigured,
            'siteKey'   => $isConfigured ? $this->config->getSiteKey() : '',
            'tagAction' => $isConfigured ? $this->recaptchaClient->getTagActionName() : '',
        ]);

        $event->addValidator('plugin.recaptcha.validator', [
            'eventName' => RecaptchaEvents::ON_FORM_VALIDATE,
            'fieldType' => 'plugin.recaptcha',
        ]);
    }

    public function onFormSubmit(SubmissionEvent $event): void
    {
        if (!$this->config->isPublished() || !$this->config->isConfigured() || 'debug' !== $_ENV['MAUTIC_LOG_LEVEL']) {
            return;
        }

        // Only log if debug level is enabled (debug() automatically checks this)
        $formData = $event->getPost();

        // Filter out Mautic internal fields and sensitive data
        $filteredData = [];
        foreach ($formData as $key => $value) {
            if (!in_array($key, ['messenger', 'submit', 'formId', 'formid', 'formName', 'return', 'g-recaptcha-response'])) {
                // Mask password fields
                if (is_string($key) && str_contains(strtolower($key), 'pass')) {
                    $filteredData[$key] = '*********';
                // if it is a an email field, mask the value by replacing the domain with *
                } elseif (is_string($key) && str_contains(strtolower($key), 'mail')) {
                    $filteredData[$key]         = str_replace(substr($value, strpos($value, '@') + 1), '***', $value);
                    $filteredData[$key.'_hash'] = $this->hashPii($value);
                } else {
                    $filteredData[$key] = $value;
                }
            }
        }

        $this->logger->debug('Recaptcha: Form submitted', [
            'form_id'       => $event->getForm()->getId(),
            'form_name'     => $event->getForm()->getName(),
            'submission_id' => $event->getSubmission()->getId(),
            'form_data'     => $filteredData,
        ]);
    }

    public function onFormValidate(ValidationEvent $event): void
    {
        if (!$this->config->isPublished() || !$this->config->isConfigured()) {
            return;
        }

        if ($this->recaptchaClient->verify($event->getValue(), $event->getField())) {
            return;
        }

        $event->failedValidation($this->translator->trans('mautic.integration.recaptcha.failure_message'));

        $this->eventDispatcher->addListener(LeadEvents::LEAD_POST_SAVE, function (LeadEvent $event) {
            $this->logger->info('Recaptcha: lead is not valid', [
                'lead' => $event->getLead()->getId(),
            ]);
            if ($event->isNew()) {
                $this->logger->info('Recaptcha: lead is new and not valid, deleting it', ['lead' => $event->getLead()->getId()]);
                $this->leadModel->deleteEntity($event->getLead());
            }
        }, -255);
    }

    /**
     * Create a deterministic, irreversible hash (non-PII) of e.g. the email address.
     */
    private function hashPii(string $data): string
    {
        return md5(strtolower(trim($data)));
    }
}
