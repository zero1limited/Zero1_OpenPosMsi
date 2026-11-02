<?php
declare(strict_types=1);

namespace Zero1\OpenPosMsi\Model;

use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\Sales\Api\Data\OrderInterface;

class StockItems
{
    /**
     * @var GetSkusByProductIdsInterface
     */
    protected $getSkusByProductIds;

    /**
     * @var GetProductTypesBySkusInterface
     */
    protected $getProductTypesBySkus;

    /**
     * @var IsSourceItemManagementAllowedForProductTypeInterface
     */
    protected $isSourceItemManagementAllowedForProductType;

    /**
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param GetProductTypesBySkusInterface $getProductTypesBySkus
     * @param IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType
     */
    public function __construct(
        GetSkusByProductIdsInterface $getSkusByProductIds,
        GetProductTypesBySkusInterface $getProductTypesBySkus,
        IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType
    ) {
        $this->getSkusByProductIds = $getSkusByProductIds;
        $this->getProductTypesBySkus = $getProductTypesBySkus;
        $this->isSourceItemManagementAllowedForProductType = $isSourceItemManagementAllowedForProductType;
    }

    /**
     * Return an order's lines for products whose stock is held in sources, resolved the same way
     * MSI resolves them when reserving stock for the order. Configurable and bundle parents are
     * skipped in favour of their children.
     * Lines with a negative row total are returns added by OpenPOS RMA.
     *
     * @param OrderInterface $order
     * @return array[] each ['order_item_id' => int, 'sku' => string, 'qty' => float, 'is_return' => bool]
     */
    public function getLines(OrderInterface $order): array
    {
        $items = $order->getItems();

        $productIds = [];
        foreach($items as $item) {
            $productIds[(int)$item->getProductId()] = (int)$item->getProductId();
        }

        // Deleted products can't be looked up (e.g. a layaway paid weeks later), so fall back to the
        // SKU and type recorded on the order item.
        try {
            $skus = $this->getSkusByProductIds->execute(array_values($productIds));
            $types = $this->getProductTypesBySkus->execute(array_values($skus));
        } catch(\Exception $e) {
            $skus = [];
            $types = [];
        }

        $lines = [];
        foreach($items as $item) {
            $sku = (string)($skus[(int)$item->getProductId()] ?? $item->getSku());
            $type = (string)($types[$sku] ?? $item->getProductType());
            if(!$this->isSourceItemManagementAllowedForProductType->execute($type)) {
                continue;
            }

            // Children of configurable products carry no price, so a return is identified by its parent.
            $priceItem = $item->getParentItem() ?: $item;

            $lines[] = [
                'order_item_id' => (int)$item->getItemId(),
                'sku' => $sku,
                'qty' => (float)$item->getQtyOrdered(),
                'is_return' => (float)$priceItem->getRowTotal() < 0
            ];
        }

        return $lines;
    }
}
