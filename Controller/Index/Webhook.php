<?php
/**
 * @category    Payments
 * @package     Zenki_Zenkipay
 * @author      Federico Balderas
 * @copyright   Zenki (https://zenki.fi)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Zenki\Zenkipay\Controller\Index;

use Magento\Framework\App\Action\Context;
use Zenki\Zenkipay\Model\Zenkipay;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote;

/**
 * Webhook class
 */
class Webhook extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    protected $request;
    protected $payment;
    protected $logger;
    protected $invoiceService;
    protected $salesOrder;

    public function __construct(
        Context $context,
        \Magento\Framework\App\Request\Http $request,
        Zenkipay $zenkipay,
        \Psr\Log\LoggerInterface $logger_interface,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\ResourceModel\Sale\Collection $salesOrder
    ) {
        parent::__construct($context);
        $this->request = $request;
        $this->zenkipay = $zenkipay;
        $this->logger = $logger_interface;
        $this->invoiceService = $invoiceService;
        $this->salesOrder = $salesOrder;
    }

    /**
     * Load the page defined in view/frontend/layout/openpay_index_webhook.xml
     * URL /openpay/index/webhook
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        // Turn off all error reporting
        error_reporting(E_ERROR);
        header('Content-type: application/json');

        $payload = file_get_contents('php://input');

        $headers = apache_request_headers();
        $svix_headers = [];
        foreach ($headers as $key => $value) {
            $header = strtolower($key);
            $svix_headers[$header] = $value;
        }

        $this->logger->info('Zenkipay - $payload => ' . $payload);

        try {
            $secret = $this->zenkipay->getWebhookSigningSecret();
            $wh = new \Svix\Webhook($secret);
            $json = $wh->verify($payload, $svix_headers);
            $payment = json_decode($json['flatData']);

            if ($payment->paymentInfo->cryptoPayment->transactionStatus != 'COMPLETED') {
                header('HTTP/1.1 400 Bad Request');
                header('Content-type: application/json');
                echo json_encode(['error' => true, 'message' => 'Transaction status is not completed.']);
                exit();
            }

            $loadorder = $this->salesOrder->addFieldToFilter('quote_id', $payment->cartId);
            if (!count($loadorder->getData())) {
                header('HTTP/1.1 404 Not Found');
                header('Content-type: application/json');
                echo json_encode(['error' => true, 'message' => 'Order with Quote ID:' . $payment->cartId . ' was not found.']);
                exit();
            }

            $incrementId = $loadorder->getData()[0]['increment_id'];
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $collection = $objectManager->create('\Magento\Sales\Model\Order');
            $order = $collection->loadByIncrementId($incrementId);

            /** @var  Total $total */
            $total = $objectManager->create(Total::class);
            $quote = $objectManager->create(Quote::class);

            $grandTotal = $order->getGrandTotal();
            $zenkipayDiscount = $payment->paymentInfo->cryptoLove->discountAmount;
            $totalWithDiscount = $grandTotal - $zenkipayDiscount;
            $totalDiscount = $zenkipayDiscount + abs($order->getDiscountAmount());

            $total->addTotalAmount('customdiscount', -$zenkipayDiscount);
            $total->addBaseTotalAmount('customdiscount', -$zenkipayDiscount);
            $total->setBaseGrandTotal($total->getBaseGrandTotal() - $zenkipayDiscount);
            $quote->setCustomDiscount(-$totalDiscount);
            $order->setDiscountAmount(-$totalDiscount);

            if ($zenkipayDiscount > 0) {
                $new_description = $order->getDiscountDescription() . ' + Cripto Love';
                $order->setDiscountDescription($new_description);
            }

            $status = \Magento\Sales\Model\Order::STATE_PROCESSING;
            $order->setState($status)->setStatus($status);
            $order->setGrandTotal($totalWithDiscount);
            $order->setTotalPaid($totalWithDiscount);
            $order->addStatusHistoryComment(__('Payment received successfully'))->setIsCustomerNotified(true);
            $order->setExtOrderId($payment->zenkiOrderId);
            $order->save();

            // $status = \Magento\Sales\Model\Order::STATE_PROCESSING;
            // $order->setState($status)->setStatus($status);
            // $order->setTotalPaid($payment->grandTotalAmount);
            // $order->addStatusHistoryComment(__('Payment received successfully'))->setIsCustomerNotified(true);
            // $order->setExtOrderId($payment->zenkiOrderId);
            // $order->save();

            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setTransactionId($payment->zenkiOrderId);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->register();
            $invoice->save();
        } catch (\Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-type: application/json');
            echo json_encode(['error' => true, 'message' => $e->getMessage()]);
            exit();
        }

        header('HTTP/1.1 200 OK');
        header('Content-type: application/json');
        echo json_encode(['success' => true]);
        exit();
    }

    /**
     * Create exception in case CSRF validation failed.
     * Return null if default exception will suffice.
     *
     * @param RequestInterface $request
     * @link https://magento.stackexchange.com/questions/253414/magento-2-3-upgrade-breaks-http-post-requests-to-custom-module-endpoint
     *
     * @return InvalidRequestException|null
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Perform custom request validation.
     * Return null if default validation is needed.
     *
     * @param RequestInterface $request
     *
     * @return bool|null
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
