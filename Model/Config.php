<?php
declare(strict_types=1);

namespace Zero1\OpenPosMsi\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    const CONFIG_PATH_SOURCE_CODE = 'openpos/msi/source_code';
    const CONFIG_PATH_LAYAWAY_DEDUCTION = 'openpos/msi/layaway_deduction';

    const LAYAWAY_DEDUCTION_PAID = 'paid';
    const LAYAWAY_DEDUCTION_PLACED = 'placed';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Return the source till sales are deducted from, null for automatic.
     *
     * @return string|null
     */
    public function getSourceCode(): ?string
    {
        $sourceCode = (string)$this->scopeConfig->getValue(self::CONFIG_PATH_SOURCE_CODE);
        return $sourceCode !== '' ? $sourceCode : null;
    }

    /**
     * Check if layaway stock is deducted when the layaway is placed, rather than when fully paid.
     *
     * @return bool
     */
    public function deductLayawaysWhenPlaced(): bool
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_LAYAWAY_DEDUCTION) === self::LAYAWAY_DEDUCTION_PLACED;
    }
}
