<?php
declare(strict_types=1);

namespace Zero1\OpenPosMsi\Model\ResourceModel;

class SourceDeduction extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Init
     */
    protected function _construct() // phpcs:ignore PSR2.Methods.MethodDeclaration
    {
        $this->_init('openpos_msi_source_deduction', 'deduction_id');
    }

    /**
     * Return the stock deducted for an order's till sale, one row per order item.
     *
     * @param int $orderId
     * @return array
     */
    public function getByOrderId(int $orderId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('order_id = ?', $orderId);

        return $connection->fetchAll($select);
    }

    /**
     * Check if stock has been deducted for an order.
     *
     * @param int $orderId
     * @return bool
     */
    public function hasDeductions(int $orderId): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['deduction_id'])
            ->where('order_id = ?', $orderId)
            ->limit(1);

        return (bool)$connection->fetchOne($select);
    }

    /**
     * @param array $rows
     * @return void
     */
    public function insertDeductions(array $rows): void
    {
        if($rows) {
            $this->getConnection()->insertMultiple($this->getMainTable(), $rows);
        }
    }

    /**
     * @param int $orderId
     * @return void
     */
    public function deleteByOrderId(int $orderId): void
    {
        $this->getConnection()->delete($this->getMainTable(), ['order_id = ?' => $orderId]);
    }
}
