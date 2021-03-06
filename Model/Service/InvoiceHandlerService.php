<?php
/**
 * Cmsbox.fr Magento 2 Mercanet Payment.
 *
 * PHP version 7
 *
 * @category  Cmsbox
 * @package   Mercanet
 * @author    Cmsbox Development Team <contact@cmsbox.fr>
 * @copyright 2019 Cmsbox.fr all rights reserved
 * @license   https://opensource.org/licenses/mit-license.html MIT License
 * @link      https://www.cmsbox.fr
 */

namespace Cmsbox\Mercanet\Model\Service;

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction;
use Cmsbox\Mercanet\Gateway\Config\Core;

class InvoiceHandlerService
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var InvoiceRepositoryInterface
     */
    protected $invoiceRepository;

    /**
     * @var Watchdog
     */
    protected $watchdog;

    /**
     * InvoiceHandlerService constructor.
     */
    public function __construct(
        \Cmsbox\Mercanet\Gateway\Config\Config $config,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository,
        \Cmsbox\Mercanet\Helper\Watchdog $watchdog
    ) {
        $this->config             = $config;
        $this->invoiceService     = $invoiceService;
        $this->invoiceRepository  = $invoiceRepository;
        $this->watchdog           = $watchdog;
    }

    public function processInvoice($order)
    {
        if ($this->shouldInvoice($order)) {
            $this->createInvoice($order);
        }
    }

    public function shouldInvoice($order)
    {
        return $order->canInvoice()
        && ($this->config->params[$order->getPayment()->getMethodInstance()->getCode()]
        [Core::KEY_AUTO_GENERATE_INVOICE]);
    }

    public function createInvoice($order)
    {
        try {
            // Prepare the invoice
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
            $invoice->register();

            // Save the invoice
            $this->invoiceRepository->save($invoice);
        } catch (\Exception $e) {
            $this->watchdog->logError($e);
        }
    }
}
