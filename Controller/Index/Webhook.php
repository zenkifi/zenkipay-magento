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
use Magento\TestFramework\Utility\ChildrenClassesSearch\E;

/**
 * Webhook class
 */
class Webhook extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    protected $request;
    protected $payment;
    protected $logger;
    protected $invoiceService;

    public function __construct(Context $context, \Magento\Framework\App\Request\Http $request, Zenkipay $zenkipay, \Psr\Log\LoggerInterface $logger_interface, \Magento\Sales\Model\Service\InvoiceService $invoiceService)
    {
        parent::__construct($context);
        $this->request = $request;
        $this->zenkipay = $zenkipay;
        $this->logger = $logger_interface;
        $this->invoiceService = $invoiceService;
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

        try {
            $secret = $this->zenkipay->getWebhookSigningSecret();
            $wh = new \Svix\Webhook($secret);
            $json = $wh->verify($payload, $svix_headers);

            if (!($decrypted_data = $this->zenkipay->RSADecyrpt($json['encryptedData']))) {
                throw new \Exception(__('Unable to decrypt data.'));
            }

            $event = json_decode($decrypted_data);
            $payment = $event->eventDetails;

            if ($payment->transactionStatus != 'COMPLETED' || !$payment->merchantOrderId) {
                throw new \Exception(__('Transaction status is no tcompleted or merchantOrderId is empty.'));
            }

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $order = $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($payment->merchantOrderId);

            /** @var  Total $total */
            $total = $objectManager->create(Total::class);
            $quote = $objectManager->create(Quote::class);
            
            $grandTotal = $order->getGrandTotal();
            $discount = $payment->cryptoLoveFiatAmount;
            $totalWithDiscount = $grandTotal-$discount;
            
            $total->addTotalAmount('customdiscount', -$discount);
            $total->addBaseTotalAmount('customdiscount', -$discount);
            $total->setBaseGrandTotal($total->getBaseGrandTotal() - $discount);
            $total_discount = $discount+(-1 * $order->getDiscountAmount());
            $quote->setCustomDiscount(-$total_discount);
            $order->setDiscountAmount(-$total_discount);
            
            if ($discount > 0) {
                $new_description = $order->getDiscountDescription().' + Cripto Love';
                $order->setDiscountDescription($new_description);
            }

            $status = \Magento\Sales\Model\Order::STATE_PROCESSING;
            $order->setState($status)->setStatus($status);
            $order->setGrandTotal($totalWithDiscount);
            $order->setTotalPaid($totalWithDiscount);
            $order->addStatusHistoryComment(__('Payment received successfully'))->setIsCustomerNotified(true);
            $order->setExtOrderId($payment->orderId);
            $order->save();

            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setTransactionId($payment->orderId);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->register();
            $invoice->save();
        } catch (\Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => true, 'msg' => $e->getMessage()]);
            exit();
        }

        header('HTTP/1.1 200 OK');
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
