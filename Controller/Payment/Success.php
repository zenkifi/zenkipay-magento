<?php
/** 
 * @category    Payments
 * @package     Zenki_Zenkipay
 * @author      Federico Balderas
 * @copyright   Zenki (https://zenki.fi)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */
namespace Zenki\Zenkipay\Controller\Payment;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Zenki\Zenkipay\Model\Zenkipay;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use Magento\Sales\Model\Order\Invoice;
/**
 * Webhook class  
 */
class Success extends \Magento\Framework\App\Action\Action
{
    protected $resultPageFactory;
    protected $request;
    protected $payment;
    protected $checkoutSession;
    protected $orderRepository;
    protected $logger;
    protected $_invoiceService;
    protected $transactionBuilder;
    protected $orderSender;
    protected $invoiceSender;
    protected $transactionRepository;
    protected $searchCriteriaBuilder;
    protected $coreRegistry;
    
    /**
     * 
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param \Magento\Framework\App\Request\Http $request
     * @param Zenkipay $payment
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Psr\Log\LoggerInterface $logger_interface
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\Registry $coreRegistry
     * 
     */
    public function __construct(
            Context $context, 
            PageFactory $resultPageFactory, 
            \Magento\Framework\App\Request\Http $request, 
            Zenkipay $payment,
            \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
            \Magento\Checkout\Model\Session $checkoutSession,
            \Psr\Log\LoggerInterface $logger_interface,
            \Magento\Sales\Model\Service\InvoiceService $invoiceService,
            \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
            \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
            \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
            \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
            \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
            \Magento\Framework\Registry $coreRegistry
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->request = $request;
        $this->payment = $payment;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger_interface;        
        $this->_invoiceService = $invoiceService;
        $this->transactionBuilder = $transactionBuilder;
        $this->orderSender = $orderSender;
        $this->invoiceSender = $invoiceSender;
        $this->transactionRepository = $transactionRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->coreRegistry = $coreRegistry;
    }
    /**     
     * 
     * @url https://magento.stackexchange.com/questions/197310/magento-2-redirect-to-final-checkout-page-checkout-success-failed?rq=1
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute() {                
        try {                        
            $order_id = $this->checkoutSession->getLastOrderId();
            $quote_id = $this->checkoutSession->getLastQuoteId();
            
            $this->checkoutSession->setLastSuccessQuoteId($quote_id);
            
            $this->logger->debug('getLastQuoteId: '.$quote_id);
            $this->logger->debug('getLastOrderId: '.$order_id);
            $this->logger->debug('getLastSuccessQuoteId: '.$this->checkoutSession->getLastSuccessQuoteId());
            $this->logger->debug('getLastRealOrderId: '.$this->checkoutSession->getLastRealOrderId());
                                        
            $order = $this->orderRepository->get($order_id);
            // $charge = // doble validación al API
                        
            // if ($order && $charge->status != 'completed') {
            //     $order->cancel();
            //     $messageError = 'La transacción no pudo ser procesada, ' . $charge->error_message;
            //     $order->addStatusToHistory(\Magento\Sales\Model\Order::STATE_CANCELED, __($messageError));
            //     $statusCanceled = $this->payment->getCustomStatus('canceled');
            //     $order->setStatus($statusCanceled);
            //     $order->save();
                        
            //     $resultPage = $this->resultPageFactory->create();
            //     $code = $charge->error_code;
            //     $this->coreRegistry->register('messageError', $this->getMessageError($code));
            //     return $resultPage;
                        
            // }
            // $this->checkoutSession->setForceOrderMailSentOnSuccess(true);
            // $this->orderSender->send($order, true);

            // $order->setState($status)->setStatus($status);
            // $order->setTotalPaid($charge->amount);  
            // $order->addStatusHistoryComment("Pago recibido exitosamente")->setIsCustomerNotified(true);            
            // $order->save();        
            
            $this->searchCriteriaBuilder->addFilter('order_id', $order_id);
            $list = $this->transactionRepository->getList(
                $this->searchCriteriaBuilder->create()
            );
            $transactions =  $list->getItems();
            foreach ($transactions as $transaction) {
                $transaction->setIsClosed(true);
                $transaction->save();
            }

            $requiresInvoice = true;
            /** @var InvoiceCollection $invoiceCollection */
            $invoiceCollection = $order->getInvoiceCollection();
            if ( $invoiceCollection->count() > 0 ) {
                /** @var Invoice $invoice */
                foreach ($invoiceCollection as $invoice ) {
                    if ( $invoice->getState() == Invoice::STATE_OPEN) {
                        $invoice->setState(Invoice::STATE_PAID);
                        // $invoice->setTransactionId($charge->id);
                        $invoice->pay()->save();
                        $this->invoiceSender->send($invoice, true);
                        $requiresInvoice = false;
                        break;
                    }
                }
            }
            if ( $requiresInvoice ) {
                $invoice = $this->_invoiceService->prepareInvoice($order);
                // $invoice->setTransactionId($charge->id);
                $invoice->pay()->save();
                $this->invoiceSender->send($invoice, true);
            }
            $payment = $order->getPayment();                                
            // $payment->setAmountPaid($charge->amount);
            $payment->setIsTransactionPending(false);
            $payment->save();
            
            $this->logger->debug('#SUCCESS', array('redirect' => 'checkout/onepage/success'));
            return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
            
        } catch (\Exception $e) {
            $this->logger->error('#SUCCESS', array('message' => $e->getMessage(), 'code' => $e->getCode(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()));
            //throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }
        
        return $this->resultRedirectFactory->create()->setPath('checkout/cart'); 
    }

    
    
}