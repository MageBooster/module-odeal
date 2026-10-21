<?php
declare(strict_types=1);

namespace MageBooster\Odeal\Controller\Redirect;

use MageBooster\Odeal\Model\ChargeAmount;
use MageBooster\Odeal\Model\DebugLog;
use MageBooster\Odeal\Model\Gateway;
use MageBooster\Odeal\Model\OrderProcessor;
use MageBooster\Odeal\Model\Settings;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Right after "Place order": works out the amount to charge, opens the payment at Ödeal and sends the customer there.
 */
class Start implements HttpGetActionInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly ChargeAmount $chargeAmount,
        private readonly Gateway $gateway,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderProcessor $processor,
        private readonly DebugLog $log
    ) {
    }

    public function execute()
    {
        $redirect = $this->redirectFactory->create();
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order->getId() || $order->getPayment()->getMethod() !== Settings::CODE
            || $order->getState() !== Order::STATE_PENDING_PAYMENT) {
            return $redirect->setPath('checkout/cart');
        }
        try {
            $charge = $this->chargeAmount->forOrder($order);
            $this->chargeAmount->remember($order, $charge);
            $url = $this->gateway->start($order, $charge);
            $this->orderRepository->save($order);
            return $redirect->setUrl($url);
        } catch (\Throwable $e) {
            $this->log->error('Start failed: ' . $e->getMessage(), ['order' => $order->getIncrementId()]);
            $this->processor->markFailed($order, $e->getMessage());
            $this->checkoutSession->restoreQuote();
            $this->messageManager->addErrorMessage($e instanceof LocalizedException
                ? $e->getMessage() : __('The payment could not be started. Please try again.'));
            return $redirect->setPath('checkout/cart');
        }
    }
}
