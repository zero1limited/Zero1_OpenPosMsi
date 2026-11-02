<?php
declare(strict_types=1);

namespace Zero1\OpenPosMsi\Plugin;

use Magento\InventorySales\Model\CheckItemsQuantity;
use Zero1\OpenPos\Model\Configuration as OpenPosConfiguration;
use Zero1\OpenPos\Model\TillSessionManagement;

class BypassStockCheck
{
    /**
     * @var OpenPosConfiguration
     */
    protected $openPosConfiguration;

    /**
     * @var TillSessionManagement
     */
    protected $tillSessionManagement;

    /**
     * @param OpenPosConfiguration $openPosConfiguration
     * @param TillSessionManagement $tillSessionManagement
     */
    public function __construct(
        OpenPosConfiguration $openPosConfiguration,
        TillSessionManagement $tillSessionManagement
    ) {
        $this->openPosConfiguration = $openPosConfiguration;
        $this->tillSessionManagement = $tillSessionManagement;
    }

    /**
     * Let the till sell items MSI considers out of stock when OpenPOS is configured to bypass stock checks.
     * MSI checks salable quantity again when an order is placed, which OpenPOS's own bypass
     * (written for Magento's legacy stock handling) doesn't cover.
     *
     * @param CheckItemsQuantity $subject
     * @param callable $proceed
     * @param array $items
     * @param int $stockId
     * @return void
     */
    public function aroundExecute(CheckItemsQuantity $subject, callable $proceed, array $items, int $stockId): void
    {
        if($this->openPosConfiguration->bypassStock() && $this->tillSessionManagement->isTillSessionActive()) {
            return;
        }

        $proceed($items, $stockId);
    }
}
