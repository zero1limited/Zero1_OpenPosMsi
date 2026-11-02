<?php
declare(strict_types=1);

namespace Zero1\OpenPosMsi\Model;

use Zero1\OpenPosMsi\Model\ResourceModel\SourceDeduction as SourceDeductionResource;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesEventExtensionFactory;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\InventorySourceDeductionApi\Model\ItemToDeductFactory;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestFactory;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Deducts till sales from the shop's source, as MSI would when an order is shipped.
 * OpenPOS orders are virtual, so they are never shipped and MSI never deducts them itself.
 */
class StockDeduction
{
    const EVENT_TILL_SALE = 'openpos_till_sale';
    const EVENT_TILL_SALE_CANCELED = 'openpos_till_sale_canceled';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var StockItems
     */
    protected $stockItems;

    /**
     * @var SourceDeductionResource
     */
    protected $sourceDeductionResource;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var StockByWebsiteIdResolverInterface
     */
    protected $stockByWebsiteIdResolver;

    /**
     * @var GetStockItemConfigurationInterface
     */
    protected $getStockItemConfiguration;

    /**
     * @var GetSourcesAssignedToStockOrderedByPriorityInterface
     */
    protected $getSourcesAssignedToStockOrderedByPriority;

    /**
     * @var GetSourceItemsBySkuInterface
     */
    protected $getSourceItemsBySku;

    /**
     * @var SourceDeductionServiceInterface
     */
    protected $sourceDeductionService;

    /**
     * @var SourceDeductionRequestFactory
     */
    protected $sourceDeductionRequestFactory;

    /**
     * @var ItemToDeductFactory
     */
    protected $itemToDeductFactory;

    /**
     * @var PlaceReservationsForSalesEventInterface
     */
    protected $placeReservationsForSalesEvent;

    /**
     * @var ItemToSellInterfaceFactory
     */
    protected $itemToSellFactory;

    /**
     * @var SalesChannelInterfaceFactory
     */
    protected $salesChannelFactory;

    /**
     * @var SalesEventInterfaceFactory
     */
    protected $salesEventFactory;

    /**
     * @var SalesEventExtensionFactory
     */
    protected $salesEventExtensionFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Config $config
     * @param StockItems $stockItems
     * @param SourceDeductionResource $sourceDeductionResource
     * @param StoreManagerInterface $storeManager
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     * @param GetStockItemConfigurationInterface $getStockItemConfiguration
     * @param GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStockOrderedByPriority
     * @param GetSourceItemsBySkuInterface $getSourceItemsBySku
     * @param SourceDeductionServiceInterface $sourceDeductionService
     * @param SourceDeductionRequestFactory $sourceDeductionRequestFactory
     * @param ItemToDeductFactory $itemToDeductFactory
     * @param PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent
     * @param ItemToSellInterfaceFactory $itemToSellFactory
     * @param SalesChannelInterfaceFactory $salesChannelFactory
     * @param SalesEventInterfaceFactory $salesEventFactory
     * @param SalesEventExtensionFactory $salesEventExtensionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        StockItems $stockItems,
        SourceDeductionResource $sourceDeductionResource,
        StoreManagerInterface $storeManager,
        StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        GetStockItemConfigurationInterface $getStockItemConfiguration,
        GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStockOrderedByPriority,
        GetSourceItemsBySkuInterface $getSourceItemsBySku,
        SourceDeductionServiceInterface $sourceDeductionService,
        SourceDeductionRequestFactory $sourceDeductionRequestFactory,
        ItemToDeductFactory $itemToDeductFactory,
        PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent,
        ItemToSellInterfaceFactory $itemToSellFactory,
        SalesChannelInterfaceFactory $salesChannelFactory,
        SalesEventInterfaceFactory $salesEventFactory,
        SalesEventExtensionFactory $salesEventExtensionFactory,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->stockItems = $stockItems;
        $this->sourceDeductionResource = $sourceDeductionResource;
        $this->storeManager = $storeManager;
        $this->stockByWebsiteIdResolver = $stockByWebsiteIdResolver;
        $this->getStockItemConfiguration = $getStockItemConfiguration;
        $this->getSourcesAssignedToStockOrderedByPriority = $getSourcesAssignedToStockOrderedByPriority;
        $this->getSourceItemsBySku = $getSourceItemsBySku;
        $this->sourceDeductionService = $sourceDeductionService;
        $this->sourceDeductionRequestFactory = $sourceDeductionRequestFactory;
        $this->itemToDeductFactory = $itemToDeductFactory;
        $this->placeReservationsForSalesEvent = $placeReservationsForSalesEvent;
        $this->itemToSellFactory = $itemToSellFactory;
        $this->salesChannelFactory = $salesChannelFactory;
        $this->salesEventFactory = $salesEventFactory;
        $this->salesEventExtensionFactory = $salesEventExtensionFactory;
        $this->logger = $logger;
    }

    /**
     * Deduct a till sale's items from the shop's source and close the reservation MSI placed for the order.
     * Does nothing if the order has already been deducted.
     *
     * The goods have physically left the shop, so a sale is never refused here: if a source holds
     * less than was sold (e.g. stock checks are bypassed) it is reduced to zero.
     *
     * @param OrderInterface $order
     * @return void
     */
    public function deduct(OrderInterface $order): void
    {
        $orderId = (int)$order->getEntityId();
        if($this->sourceDeductionResource->hasDeductions($orderId)) {
            return;
        }

        // Returns are left out: like OpenPOS without MSI, they don't change stock.
        $lines = array_filter($this->stockItems->getLines($order), function (array $line) {
            return !$line['is_return'];
        });
        if(!$lines) {
            return;
        }

        $stockId = (int)$this->stockByWebsiteIdResolver->execute($this->getWebsiteId($order))->getStockId();

        $sources = [];
        $records = [];
        $qtyBySku = [];
        $deductBySource = [];
        foreach($lines as $line) {
            $sku = $line['sku'];
            if(!isset($sources[$sku])) {
                $sources[$sku] = $this->getSource($sku, $stockId);
            }

            $qtyDeducted = min($line['qty'], $sources[$sku]['available']);
            $sources[$sku]['available'] -= $qtyDeducted;

            if($sources[$sku]['managed'] && $qtyDeducted < $line['qty']) {
                $this->logger->warning(sprintf(
                    'OpenPOS order %s sold %s of %s but source %s only held %s; deducted what was available.',
                    $order->getIncrementId(),
                    $line['qty'],
                    $sku,
                    $sources[$sku]['source_code'] ?? '(none)',
                    $qtyDeducted
                ));
            }

            if($qtyDeducted > 0) {
                $deductBySource[$sources[$sku]['source_code']][$sku] = ($deductBySource[$sources[$sku]['source_code']][$sku] ?? 0) + $qtyDeducted;
            }
            $qtyBySku[$sku] = ($qtyBySku[$sku] ?? 0) + $line['qty'];

            $records[] = [
                'order_id' => $orderId,
                'order_item_id' => $line['order_item_id'],
                'sku' => $sku,
                'source_code' => $sources[$sku]['source_code'],
                'qty' => $line['qty'],
                'qty_deducted' => $qtyDeducted
            ];
        }

        $salesChannel = $this->getSalesChannel($order);
        $salesEvent = $this->getSalesEvent(self::EVENT_TILL_SALE, $order);

        $connection = $this->sourceDeductionResource->getConnection();
        $connection->beginTransaction();
        try {
            foreach($deductBySource as $sourceCode => $qtys) {
                $this->deductFromSource((string)$sourceCode, $qtys, $salesChannel, $salesEvent);
            }

            // The full quantity sold closes the order's reservation, as MSI does when an order is shipped.
            $this->placeReservations($qtyBySku, $salesChannel, $salesEvent);

            $this->sourceDeductionResource->insertDeductions($records);
            $connection->commit();
        } catch(\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Undo the deduction for a till sale that has since been cancelled, e.g. an unpaid layaway.
     * Cancelling releases the order's reservation, which the deduction had already closed,
     * so the reservation is reversed along with the source quantities.
     *
     * @param OrderInterface $order
     * @return void
     */
    public function restore(OrderInterface $order): void
    {
        $orderId = (int)$order->getEntityId();
        $records = $this->sourceDeductionResource->getByOrderId($orderId);
        if(!$records) {
            return;
        }

        $qtyBySku = [];
        $returnBySource = [];
        foreach($records as $record) {
            $qtyBySku[$record['sku']] = ($qtyBySku[$record['sku']] ?? 0) - (float)$record['qty'];
            if($record['source_code'] !== null && (float)$record['qty_deducted'] > 0) {
                // A negative deduction puts the quantity back on the source.
                $returnBySource[$record['source_code']][$record['sku']] = ($returnBySource[$record['source_code']][$record['sku']] ?? 0) - (float)$record['qty_deducted'];
            }
        }

        $salesChannel = $this->getSalesChannel($order);
        $salesEvent = $this->getSalesEvent(self::EVENT_TILL_SALE_CANCELED, $order);

        $connection = $this->sourceDeductionResource->getConnection();
        $connection->beginTransaction();
        try {
            foreach($returnBySource as $sourceCode => $qtys) {
                $this->deductFromSource((string)$sourceCode, $qtys, $salesChannel, $salesEvent);
            }
            $this->placeReservations($qtyBySku, $salesChannel, $salesEvent);
            $this->sourceDeductionResource->deleteByOrderId($orderId);
            $connection->commit();
        } catch(\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Return the source a SKU is deducted from, and how much it holds.
     * Uses the configured source, otherwise the highest priority enabled source assigned to the
     * stock that holds the SKU, the same way MSI picks a source when returning invoiced items.
     *
     * @param string $sku
     * @param int $stockId
     * @return array ['source_code' => string|null, 'available' => float, 'managed' => bool]
     */
    protected function getSource(string $sku, int $stockId): array
    {
        if(!$this->getStockItemConfiguration->execute($sku, $stockId)->isManageStock()) {
            return ['source_code' => null, 'available' => 0.0, 'managed' => false];
        }

        $sourceItems = [];
        foreach($this->getSourceItemsBySku->execute($sku) as $sourceItem) {
            $sourceItems[$sourceItem->getSourceCode()] = $sourceItem;
        }

        $sourceCodes = [];
        if($this->config->getSourceCode() !== null) {
            $sourceCodes[] = $this->config->getSourceCode();
        } else {
            foreach($this->getSourcesAssignedToStockOrderedByPriority->execute($stockId) as $source) {
                if($source->isEnabled()) {
                    $sourceCodes[] = $source->getSourceCode();
                }
            }
        }

        foreach($sourceCodes as $sourceCode) {
            if(isset($sourceItems[$sourceCode])) {
                return [
                    'source_code' => $sourceCode,
                    'available' => max(0.0, (float)$sourceItems[$sourceCode]->getQuantity()),
                    'managed' => true
                ];
            }
        }

        return ['source_code' => null, 'available' => 0.0, 'managed' => true];
    }

    /**
     * @param string $sourceCode
     * @param array $qtyBySku negative quantities put stock back
     * @param SalesChannelInterface $salesChannel
     * @param SalesEventInterface $salesEvent
     * @return void
     */
    protected function deductFromSource(string $sourceCode, array $qtyBySku, SalesChannelInterface $salesChannel, SalesEventInterface $salesEvent): void
    {
        $items = [];
        foreach($qtyBySku as $sku => $qty) {
            $items[] = $this->itemToDeductFactory->create(['sku' => (string)$sku, 'qty' => (float)$qty]);
        }

        $this->sourceDeductionService->execute($this->sourceDeductionRequestFactory->create([
            'sourceCode' => $sourceCode,
            'items' => $items,
            'salesChannel' => $salesChannel,
            'salesEvent' => $salesEvent
        ]));
    }

    /**
     * @param array $qtyBySku positive quantities close a reservation, negative ones reserve stock
     * @param SalesChannelInterface $salesChannel
     * @param SalesEventInterface $salesEvent
     * @return void
     */
    protected function placeReservations(array $qtyBySku, SalesChannelInterface $salesChannel, SalesEventInterface $salesEvent): void
    {
        $items = [];
        foreach($qtyBySku as $sku => $qty) {
            $items[] = $this->itemToSellFactory->create(['sku' => (string)$sku, 'qty' => (float)$qty]);
        }

        $this->placeReservationsForSalesEvent->execute($items, $salesChannel, $salesEvent);
    }

    /**
     * @param OrderInterface $order
     * @return int
     */
    protected function getWebsiteId(OrderInterface $order): int
    {
        return (int)$this->storeManager->getStore($order->getStoreId())->getWebsiteId();
    }

    /**
     * @param OrderInterface $order
     * @return SalesChannelInterface
     */
    protected function getSalesChannel(OrderInterface $order): SalesChannelInterface
    {
        return $this->salesChannelFactory->create([
            'data' => [
                'type' => SalesChannelInterface::TYPE_WEBSITE,
                'code' => $this->storeManager->getWebsite($this->getWebsiteId($order))->getCode()
            ]
        ]);
    }

    /**
     * Reservations are matched to their order by ID and increment ID, e.g. by MSI's reservation cleanup.
     *
     * @param string $type
     * @param OrderInterface $order
     * @return SalesEventInterface
     */
    protected function getSalesEvent(string $type, OrderInterface $order): SalesEventInterface
    {
        $salesEvent = $this->salesEventFactory->create([
            'type' => $type,
            'objectType' => SalesEventInterface::OBJECT_TYPE_ORDER,
            'objectId' => (string)$order->getEntityId()
        ]);
        $salesEvent->setExtensionAttributes($this->salesEventExtensionFactory->create([
            'data' => ['objectIncrementId' => (string)$order->getIncrementId()]
        ]));

        return $salesEvent;
    }
}
