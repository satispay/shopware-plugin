<?php declare(strict_types=1);

namespace Satispay\Controller\Api;

use Psr\Log\LoggerInterface;
use Satispay\Handler\Api\ActivateCode;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
     * @Route("/api/v{version}/_action/satispay/activate", name="api.action.satispay.activate", methods={"GET"})
     */
    public function activate(Request $request, Context $context): JsonResponse
    {
        try {
            $salesChannels = $this->salesChannelRepository->search(new Criteria(), $context);

            if ($salesChannels->count() === 0) {
                throw new \Exception('There are no sales channels, please check your shop sales channels');
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
