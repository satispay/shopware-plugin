<?php declare(strict_types=1);

namespace Satispay\Controller\Api;

use Psr\Log\LoggerInterface;
use Satispay\Handler\Api\ActivateCode;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class ConfigurationController extends AbstractController
{
    public const STOREFRONT_SALESCHANNEL_TYPE_ID = '8a243080f92e4c719546314b577cf82b';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var ActivateCode
     */
    private $helperConfig;

    public function __construct(
        ActivateCode $helperConfig,
        EntityRepositoryInterface $salesChannelRepository,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->helperConfig = $helperConfig;
    }

    /**
     * @Route("/api/_action/satispay/activate", name="api.action.satispay.activate", methods={"GET"})
     * @Route("/api/v{version}/_action/satispay/activate", name="api.action.satispay.activate.version", methods={"GET"})
     */
    public function activate(Request $request, Context $context): JsonResponse
    {
        try {
            $criteria = new Criteria();
            //filter only storefront type of channels
            $criteria->addFilter(new EqualsFilter('typeId', self::STOREFRONT_SALESCHANNEL_TYPE_ID));
            $salesChannels = $this->salesChannelRepository->search($criteria, $context);

            if ($salesChannels->count() === 0) {
                throw new \Exception(
                    'There are no storefront sales channels, please check your shop sales channels'
                );
            }

            $this->helperConfig->activateChannel();

            foreach ($salesChannels as $salesChannel) {
                $this->helperConfig->activateChannel($salesChannel->getId());
            }
        } catch (\Exception $e) {
            $this->logger->error(self::class . ' : ' . $e->getMessage());

            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
