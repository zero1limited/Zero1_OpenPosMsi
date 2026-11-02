<?php
declare(strict_types=1);

namespace Zero1\OpenPosMsi\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\InventoryApi\Api\SourceRepositoryInterface;

class SourceCode implements OptionSourceInterface
{
    /**
     * @var SourceRepositoryInterface
     */
    protected $sourceRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @param SourceRepositoryInterface $sourceRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        SourceRepositoryInterface $sourceRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->sourceRepository = $sourceRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Return an array of inventory sources, plus automatic selection.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $values = [
            ['value' => '', 'label' => __('Automatic (highest priority source)')]
        ];

        foreach ($this->sourceRepository->getList($this->searchCriteriaBuilder->create())->getItems() as $source) {
            $values[] = [
                'value' => $source->getSourceCode(),
                'label' => $source->getName() . ' (' . $source->getSourceCode() . ')'
            ];
        }

        return $values;
    }
}
