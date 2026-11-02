<?php
declare(strict_types=1);

namespace Zero1\OpenPosMsi\Plugin;

use Magento\InventorySales\Model\AppendReservations;
use Zero1\OpenPos\Model\Configuration as OpenPosConfiguration;
use Zero1\OpenPos\Model\OrderManagement;
use Zero1\OpenPosMsi\Model\StockItems;

class ExcludeReturnsFromReservations
{
    /**
     * @var OpenPosConfiguration
     */
    protected $openPosConfiguration;

    /**
     * @var OrderManagement
     */
    protected $orderManagement;

    /**
     * @var StockItems
     */
    protected $stockItems;

    /**
     * @param OpenPosConfiguration $openPosConfiguration
     * @param OrderManagement $orderManagement
     * @param StockItems $stockItems
     */
    public function __construct(
        OpenPosConfiguration $openPosConfiguration,
        OrderManagement $orderManagement,
        StockItems $stockItems
    ) {
        $this->openPosConfiguration = $openPosConfiguration;
        $this->orderManagement = $orderManagement;
        $this->stockItems = $stockItems;
    }

    /**
     * Leave OpenPOS returns out of the stock MSI checks and reserves when a till sale is placed.
     * A return line has a positive quantity, so MSI would otherwise reserve stock for it, and
     * refuse the sale if the returned product is out of stock.
     *
     * @param AppendReservations $subject
     * @param int $websiteId
     * @param array $itemsBySku quantity ordered by SKU
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param \Magento\InventorySalesApi\Api\Data\ItemToSellInterface[] $itemsToSell negative quantities to reserve
     * @return array
     */
    public function beforeReserve(AppendReservations $subject, $websiteId, $itemsBySku, $order, $itemsToSell): array
    {
        if(!$this->openPosConfiguration->isEnabled()
            || $order->getStoreId() != $this->openPosConfiguration->getPosStoreId()
            || !$this->orderManagement->isTillSale($order)
        ) {
            return [$websiteId, $itemsBySku, $order, $itemsToSell];
        }

        $returnQtyBySku = [];
        foreach($this->stockItems->getLines($order) as $line) {
            if($line['is_return']) {
                $returnQtyBySku[$line['sku']] = ($returnQtyBySku[$line['sku']] ?? 0) + $line['qty'];
            }
        }

        if(!$returnQtyBySku) {
            return [$websiteId, $itemsBySku, $order, $itemsToSell];
        }

        foreach($returnQtyBySku as $sku => $qty) {
            if(isset($itemsBySku[$sku])) {
                $itemsBySku[$sku] -= $qty;
                if($itemsBySku[$sku] <= 0) {
                    unset($itemsBySku[$sku]);
                }
            }
        }

        $remainingItemsToSell = [];
        foreach($itemsToSell as $itemToSell) {
            $qty = $itemToSell->getQuantity() + ($returnQtyBySku[$itemToSell->getSku()] ?? 0);
            if($qty < 0) {
                $itemToSell->setQuantity($qty);
                $remainingItemsToSell[] = $itemToSell;
            }
        }

        return [$websiteId, $itemsBySku, $order, $remainingItemsToSell];
    }
}
