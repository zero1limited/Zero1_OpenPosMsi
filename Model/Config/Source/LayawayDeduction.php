<?php
declare(strict_types=1);

namespace Zero1\OpenPosMsi\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Zero1\OpenPosMsi\Model\Config;

class LayawayDeduction implements OptionSourceInterface
{
    /**
     * Return an array of points at which layaway stock can be deducted.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::LAYAWAY_DEDUCTION_PAID, 'label' => __('When fully paid (default)')],
            ['value' => Config::LAYAWAY_DEDUCTION_PLACED, 'label' => __('When placed')]
        ];
    }
}
