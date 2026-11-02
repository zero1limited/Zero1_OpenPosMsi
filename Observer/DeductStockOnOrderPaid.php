<?php
declare(strict_types=1);

namespace Zero1\OpenPosMsi\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Zero1\OpenPos\Model\PaymentMethod\Layaways;
use Zero1\OpenPosMsi\Model\Config;
use Zero1\OpenPosMsi\Model\StockDeduction;

class DeductStockOnOrderPaid implements ObserverInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var StockDeduction
     */
    protected $stockDeduction;

    /**
     * @param Config $config
     * @param StockDeduction $stockDeduction
     */
    public function __construct(
        Config $config,
        StockDeduction $stockDeduction
    ) {
        $this->config = $config;
        $this->stockDeduction = $stockDeduction;
    }

    /**
     * Deduct a layaway's stock once it is fully paid (the default).
     * Other till sales are deducted when placed, see DeductStockOnTillSale.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();

        if($order->getPayment()->getMethod() !== Layaways::PAYMENT_METHOD_CODE || $this->config->deductLayawaysWhenPlaced()) {
            return;
        }

        $this->stockDeduction->deduct($order);
    }
}
