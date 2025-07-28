<?php declare(strict_types=1);

namespace Satispay;

use Satispay\Handler\PaymentHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class Satispay extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $this->addPaymentMethod($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);
        // Only set the payment method to inactive when uninstalling. Removing the payment method would
        // cause data consistency issues, since the payment method might have been used in several orders
        $this->setPaymentMethodIsActive(false, $uninstallContext->getContext());
    }

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);
        $this->setPaymentMethodIsActive(true, $activateContext->getContext());
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        parent::deactivate($deactivateContext);
        $this->setPaymentMethodIsActive(false, $deactivateContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        $currentVersion = $updateContext->getCurrentPluginVersion();

        if (\version_compare($currentVersion, '1.1.1', '<')) {
            $this->updateTranslations($updateContext);
        }

        if (\version_compare($currentVersion, '3.0.1', '<')) {
            $this->updateAllowPaymentChange($updateContext);
        }
    }

    private function addPaymentMethod(Context $context): void
    {
        $paymentMethodExists = $this->getPaymentMethodId($context);

        // Payment method exists already, no need to continue here
        if ($paymentMethodExists) {
            $this->addAllowPaymentChange($context);
            return;
        }

        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass(static::class, $context);

        $satispayPaymentData = [
            // payment handler will be selected by the identifier
            'handlerIdentifier' => PaymentHandler::class,
            'name' => 'Satispay',
            'technicalName' => 'satispay_satispay',
            'description' => 'Do it smart. Choose Satispay and pay with a tap!',
            'pluginId' => $pluginId,
            'afterOrderEnabled' => true,
        ];

        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->create([$satispayPaymentData], $context);

        $this->addTranslationsToPaymentMethod($context);
    }

    private function getPaymentMethodId($context): ?string
    {
        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        // Fetch ID for update
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', PaymentHandler::class));

        return $paymentRepository->searchIds($paymentCriteria, $context)->firstId();
    }

    private function setPaymentMethodIsActive(bool $active, Context $context): void
    {
        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethodId = $this->getPaymentMethodId($context);

        // Payment does not even exist, so nothing to (de-)activate here
        if (!$paymentMethodId) {
            return;
        }

        $paymentMethod = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];

        $paymentRepository->update([$paymentMethod], $context);
    }

    private function updateTranslations(UpdateContext $updateContext): void
    {
        //update translations for Satispay Payment checkout description
        $this->addTranslationsToPaymentMethod(
            $updateContext->getContext()
        );
    }

    private function updateAllowPaymentChange(UpdateContext $updateContext): void
    {
        //update allow payment change for Satispay Payment checkout
        $this->addAllowPaymentChange(
            $updateContext->getContext()
        );
    }

    private function addTranslationsToPaymentMethod(Context $context)
    {
        $paymentId = $this->getPaymentMethodId($context);
        if (!$paymentId) {
            return;
        }

        $languageRepo = $this->container->get('language.repository');
        $languageEN = $languageRepo->search((new Criteria())->addFilter(new EqualsFilter('language.translationCode.code','en-GB')),Context::createDefaultContext())->first();
        $languageDE = $languageRepo->search((new Criteria())->addFilter(new EqualsFilter('language.translationCode.code','de-DE')),Context::createDefaultContext())->first();
        $languageIT = $languageRepo->search((new Criteria())->addFilter(new EqualsFilter('language.translationCode.code','it-IT')),Context::createDefaultContext())->first();

        // english
        if ($languageEN) {
            $this->upsertTranslation($context, $paymentId, $languageEN->getId(), 'Satispay', 'Do it smart. Choose Satispay and pay with a tap!');
        }
        // german
        if ($languageDE) {
            $this->upsertTranslation($context, $paymentId, $languageDE->getId(), 'Satispay', 'Do it smart. Jetzt in einem Klick mit Satispay bezahlen!');
        }
        // italian
        if ($languageIT) {
            $this->upsertTranslation($context, $paymentId, $languageIT->getId(), 'Satispay', 'Paga smart, con Satispay hai tutto a portata di app!');
        }
    }

    private function addAllowPaymentChange(Context $context)
    {
        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethodId = $this->getPaymentMethodId($context);

        // Payment does not even exist, so nothing to update here
        if (!$paymentMethodId) {
            return;
        }

        $paymentMethod = [
            'id' => $paymentMethodId,
            'afterOrderEnabled' => true,
        ];

        $paymentRepository->update([$paymentMethod], $context);
    }

    private function upsertTranslation(Context $context, $paymentId, $languageId, $name, $description)
    {
        /** @var EntityRepository $paymentTranslationRepository */
        $paymentTranslationRepository = $this->container->get('payment_method_translation.repository');

        $paymentTranslationRepository->upsert([
            [
                'paymentMethodId' => $paymentId,
                'languageId' => $languageId,
                'name' => $name,
                'description' => $description
            ]
        ], $context);
    }
}
