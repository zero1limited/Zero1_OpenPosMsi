<?php
declare(strict_types=1);

namespace Zero1\OpenPosMsi\Model\ReturnProcessor;

use Zero1\OpenPosMsi\Model\ResourceModel\SourceDeduction as SourceDeductionResource;
use Magento\InventorySalesApi\Model\ReturnProcessor\GetSourceDeductedOrderItemsInterface;
use Magento\InventorySalesApi\Model\ReturnProcessor\Result\SourceDeductedOrderItemFactory;
use Magento\InventorySalesApi\Model\ReturnProcessor\Result\SourceDeductedOrderItemsResultFactory;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Tells MSI which till sale items were deducted from which source, so a credit memo with
 * "Return to Stock" puts them back there. MSI only knows about shipped items, and invoiced
 * virtual products, so it can't see stock deducted for a till sale by itself.
 */
class GetTillSaleDeductedItems implements GetSourceDeductedOrderItemsInterface
{
    /**
     * @var SourceDeductionResource
     */
    protected $sourceDeductionResource;

    /**
     * @var SourceDeductedOrderItemFactory
     */
    protected $sourceDeductedOrderItemFactory;

    /**
     * @var SourceDeductedOrderItemsResultFactory
     */
    protected $sourceDeductedOrderItemsResultFactory;

    /**
     * @param SourceDeductionResource $sourceDeductionResource
     * @param SourceDeductedOrderItemFactory $sourceDeductedOrderItemFactory
     * @param SourceDeductedOrderItemsResultFactory $sourceDeductedOrderItemsResultFactory
     */
    public function __construct(
        SourceDeductionResource $sourceDeductionResource,
        SourceDeductedOrderItemFactory $sourceDeductedOrderItemFactory,
        SourceDeductedOrderItemsResultFactory $sourceDeductedOrderItemsResultFactory
    ) {
        $this->sourceDeductionResource = $sourceDeductionResource;
        $this->sourceDeductedOrderItemFactory = $sourceDeductedOrderItemFactory;
        $this->sourceDeductedOrderItemsResultFactory = $sourceDeductedOrderItemsResultFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute(OrderInterface $order, array $returnToStockItems): array
    {
        $records = $this->sourceDeductionResource->getByOrderId((int)$order->getEntityId());
        if(!$records) {
            return [];
        }

        // MSI passes the items being returned by ID; a configurable product is passed as its parent.
        $parentItemIds = [];
        foreach($order->getItems() as $item) {
            $parentItemIds[(int)$item->getItemId()] = (int)$item->getParentItemId();
        }

        $qtyBySource = [];
        foreach($records as $record) {
            $itemId = (int)$record['order_item_id'];
            if(!in_array($itemId, $returnToStockItems) && !in_array($parentItemIds[$itemId] ?? 0, $returnToStockItems)) {
                continue;
            }
            if($record['source_code'] === null || (float)$record['qty_deducted'] <= 0) {
                continue;
            }

            $qtyBySource[$record['source_code']][$record['sku']] = ($qtyBySource[$record['source_code']][$record['sku']] ?? 0) + (float)$record['qty_deducted'];
        }

        $result = [];
        foreach($qtyBySource as $sourceCode => $qtyBySku) {
            $items = [];
            foreach($qtyBySku as $sku => $qty) {
                $items[] = $this->sourceDeductedOrderItemFactory->create(['sku' => (string)$sku, 'quantity' => $qty]);
            }
            $result[] = $this->sourceDeductedOrderItemsResultFactory->create(['sourceCode' => (string)$sourceCode, 'items' => $items]);
        }

        return $result;
    }
}
