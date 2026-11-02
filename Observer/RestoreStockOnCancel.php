<?php
declare(strict_types=1);

namespace Zero1\OpenPosMsi\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Zero1\OpenPos\Model\Configuration as OpenPosConfiguration;
use Zero1\OpenPos\Model\OrderManagement;
use Zero1\OpenPosMsi\Model\StockDeduction;

class RestoreStockOnCancel implements ObserverInterface
{
    /**
     * @var OpenPosConfiguration
     */
    protected $openPosConfiguration;

    /**
     * @var StockDeduction
     */
    protected $stockDeduction;

    /**
     * @var OrderManagement
     */
    protected $orderManagement;

    /**
     * @param OpenPosConfiguration $openPosConfiguration
     * @param StockDeduction $stockDeduction
     * @param OrderManagement $orderManagement
     */
    public function __construct(
        OpenPosConfiguration $openPosConfiguration,
        StockDeduction $stockDeduction,
        OrderManagement $orderManagement
    ) {
        $this->openPosConfiguration = $openPosConfiguration;
        $this->stockDeduction = $stockDeduction;
        $this->orderManagement = $orderManagement;
    }

    /**
     * Put back the stock of a cancelled till sale that was already deducted.
     * Only an unpaid layaway deducted when placed can be cancelled after deduction: paid
     * orders are invoiced, and Magento doesn't release stock for invoiced items on cancel.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();

        if(!$this->openPosConfiguration->isEnabled() || $order->getStoreId() != $this->openPosConfiguration->getPosStoreId()) {
            return;
        }

        if((float)$order->getBaseTotalInvoiced() > 0) {
            return;
        }

        // Don't stop the order being cancelled (e.g. unstashed at the till) if stock can't be put back.
        try {
            $this->stockDeduction->restore($order);
        } catch(\Throwable $e) {
            $this->orderManagement->reportProcessingFailure(
                $order,
                (string)__('OpenPOS could not return this order\'s stock to its source after it was cancelled. Please check stock for its items.'),
                $e
            );
        }
    }
}
