# Zero1_OpenPosMsi

Magento Inventory (MSI) support for ZERO-1 OpenPOS.

OpenPOS on its own works with Magento's legacy stock handling. Install this module on stores where Magento Inventory (MSI) is enabled, so till sales update stock levels:

```
composer require zero1/open-pos-msi
php bin/magento setup:upgrade
```

## What it does

- **Till sales deduct stock.** OpenPOS orders are virtual, so they are never shipped and MSI never deducts them from a source. This module deducts the items from the shop's source when a till sale is placed, and closes MSI's reservation for the order.
- **Layaways** are deducted when they are fully paid, or when they are placed (see configuration).
- **Cancelled layaways** that were already deducted have their stock put back.
- **Credit memos** with "Return to Stock" put till sale items back on the source they were deducted from.
- **Bypass stock checks** works under MSI: when enabled, the till can sell items MSI considers out of stock. If a source holds less than was sold, it is reduced to zero.
- **Returns** (OpenPOS RMA) no longer reduce stock or fail for out of stock items. As without MSI, returned items are not added back to stock.

## Configuration

[Stores] -> [Configuration] -> [OpenPOS] -> [Configuration] -> [Inventory (MSI)]

| Setting | Description |
|---------|-------------|
| Source | The source representing the shop. Automatic uses the highest priority source, assigned to the POS website's stock, that holds the product. |
| Deduct layaway stock | When fully paid (default), or when placed. |

Tested with Magento 2.4.8 (Magento Inventory 1.3).
