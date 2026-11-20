<?php
declare(strict_types=1);

namespace MageBooster\Odeal\Controller\Payment;

use MageBooster\Odeal\Model\Gateway;
use MageBooster\Odeal\Model\OrderProcessor;
use MageBooster\Odeal\Model\ReturnUrl;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;

/**
 * The customer comes back from Ödeal here (GET or POST, so CSRF-exempt). Nothing in the request is trusted:
 * the order number is signed, and the payment result is asked from Ödeal server to server.
 */
class Callback implements HttpGetActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private RequestInterface $request,
        private RedirectFactory $redirectFactory,
        private ReturnUrl $returnUrl,
        private OrderProcessor $processor,
        private Gateway $gateway
    ) {
    }

    public function execute()
    {
        $incrementId = (string)$this->request->getParam('o');
        if (!$this->returnUrl->isValid($incrementId, (string)$this->request->getParam('s'))) {
            // never re-sign an order number that came without a valid signature
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }
        $order = $this->processor->load($incrementId);
        if ($order && $this->processor->isOpen($order)) {
            $this->processor->apply($order, $this->gateway->status($order));
        }
        return $this->redirectFactory->create()->setUrl($this->returnUrl->build('mbodeal/payment/result', $incrementId));
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
