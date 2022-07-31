<?php declare(strict_types=1);

namespace Shopware\Production\Merchants\Storefront\Page\Confirm;

use GuzzleHttp\ClientInterface;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Production\Merchants\Content\Merchant\MerchantCollection;
use Shopware\Production\Merchants\Content\Merchant\MerchantEntity;
use Shopware\Production\Merchants\Events\BlockPaymentMethodsEvent;
use Shopware\Production\Merchants\Events\BlockShippingMethodsEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;

class ConfirmPageLoadedSubscriber
{
    private const API_URL = 'http://codingaustria-co2-calculator-backend.test/api/customer/pickup';

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ClientInterface
     */
    private $httpClient;

    public function __construct(
        EntityRepositoryInterface $productRepository,
        EventDispatcherInterface $eventDispatcher,
        ClientInterface $httpClient
)
    {
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->httpClient = $httpClient;
    }

    public function __invoke(CheckoutConfirmPageLoadedEvent $event): void
    {
        $merchant = $this->getMerchant($event->getPage(), $event->getSalesChannelContext());
        if ($merchant === null) {
            return;
        }

        $cart = $event->getPage()->getCart();
        $delivery = $cart->getDeliveries()->first();
        $shippingAddress = $delivery->getLocation()->getAddress();
        $body = [
            'merchantId' => $merchant->getId(),
            'street' => $shippingAddress->getStreet(),
            'city' => $shippingAddress->getCity(),
            'zip' => $shippingAddress->getZipcode()
        ];

        $response = $this->httpClient->get(
            self::API_URL,
            ['query' => $body]
        );

        $content = $response->getBody()->getContents();
        $event->getPage()->addExtension('co2_data', new ArrayStruct(json_decode($content, true)));

        $deliveryType = 1;
        switch($delivery->getShippingMethod()->getId()) {
            case 'fe17b8d42bb541fd9bda50cfee4cec3a':
                $deliveryType = 1;
            case 'bf46f82df28c4103a32d26951834ef5d':
                $deliveryType = 2;
            case '7b0d18a44f844b9684b67292d1cd42f4':
                $deliveryType = 3;
            default:
                $deliveryType = 1;
        }

        $event->getPage()->addExtension('additional_info', new ArrayStruct(['deliveryType' => $deliveryType]));

        $event->getPage()->addExtension('merchant', $merchant);

        $this->filterShippingMethods($merchant, $event->getPage()->getShippingMethods(), $event->getSalesChannelContext());

        $this->filterAvailablePaymentMethods($merchant, $event->getPage()->getPaymentMethods(), $event->getSalesChannelContext());
    }

    private function getMerchant(CheckoutConfirmPage $page, SalesChannelContext $context): ?MerchantEntity
    {
        $productLineItem = $page->getCart()->getLineItems()->first();
        if ($productLineItem === null) {
            return null;
        }

        $productId = $productLineItem->getReferencedId();

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('merchants.shippingMethods');

        $product = $this->productRepository->search($criteria, $context->getContext())->first();
        if ($product === null) {
            return null;
        }

        /** @var MerchantCollection|null $merchants */
        $merchants = $product->getExtension('merchants');
        if ($merchants === null || \count($merchants) <= 0) {
            return null;
        }

        return $merchants->first();
    }

    private function filterShippingMethods(MerchantEntity $merchant, ShippingMethodCollection $shippingMethods, SalesChannelContext $context): void
    {
        $event = new BlockShippingMethodsEvent($shippingMethods, $merchant, $context);
        $this->eventDispatcher->dispatch($event);

        foreach ($event->getShippingMethodCollection() as $id => $shippingMethod) {
            // The merchant cannot block the default shipping method
            if ($id === $context->getSalesChannel()->getShippingMethodId()) {
                continue;
            }

            if ($merchant->getShippingMethods()->has($id)) {
                continue;
            }

            $shippingMethods->remove($id);
        }
    }

    /**
     * Reduces available payment methods to
     * only be the one that are also allowed
     * by the current merchant.
     *
     * @param MerchantEntity $merchant
     * @param PaymentMethodCollection $shippingMethods
     * @param SalesChannelContext $context
     */
    private function filterAvailablePaymentMethods(MerchantEntity $merchant, PaymentMethodCollection $cartPaymentMethods, SalesChannelContext $context): void
    {
        $event = new BlockPaymentMethodsEvent($cartPaymentMethods, $merchant, $context);
        $this->eventDispatcher->dispatch($event);

        /** @var array $allowedIds */
        $allowedIds = array();

        if (!empty($merchant->getPaymentMethods())) {
            $list = json_decode($merchant->getPaymentMethods(), true);
            # build flat list
            foreach ($list as $entry) {
                $allowedIds[] = $entry['id'];
            }
        }

        foreach ($event->getPaymentMethodCollection() as $id => $paymentMethod) {
            if (!in_array($id, $allowedIds)) {
                $cartPaymentMethods->remove($id);
            }
        }

    }
}
