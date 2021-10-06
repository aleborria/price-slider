<?php
namespace SummaTheme\LayeredNavigation\Model\Layer\Filter;


class Price extends \Magento\CatalogSearch\Model\Layer\Filter\Price
{
    /**
     * @var \Magento\Catalog\Model\Layer\Filter\DataProvider\Price
     */
    private $dataProvider;


    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    private $_sliderActive;

    /**
     * @param \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Layer $layer
     * @param \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder
     * @param \Magento\Catalog\Model\ResourceModel\Layer\Filter\Price $resource
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Search\Dynamic\Algorithm $priceAlgorithm
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     * @param \Magento\Catalog\Model\Layer\Filter\Dynamic\AlgorithmFactory $algorithmFactory
     * @param \Magento\Catalog\Model\Layer\Filter\DataProvider\PriceFactory $dataProviderFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct(
        \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Layer $layer,
        \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder,
        \Magento\Catalog\Model\ResourceModel\Layer\Filter\Price $resource,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Search\Dynamic\Algorithm $priceAlgorithm,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Catalog\Model\Layer\Filter\Dynamic\AlgorithmFactory $algorithmFactory,
        \Magento\Catalog\Model\Layer\Filter\DataProvider\PriceFactory $dataProviderFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct(
            $filterItemFactory,
            $storeManager,
            $layer,
            $itemDataBuilder,
            $resource,
            $customerSession,
            $priceAlgorithm,
            $priceCurrency,
            $algorithmFactory,
            $dataProviderFactory,
            $data
        );
        $this->priceCurrency = $priceCurrency;
        $this->dataProvider = $dataProviderFactory->create(['layer' => $this->getLayer()]);
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * Apply price range filter
     *
     * @param \Magento\Framework\App\RequestInterface $request The request
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function apply(\Magento\Framework\App\RequestInterface $request)
    {
        if (!$this->isSliderActive()){
            return parent::apply($request);
        }

        /**
         * Filter must be string: $fromPrice-$toPrice
         */
        $filter = $request->getParam($this->getRequestVar());
        if (!$filter || is_array($filter)) {
            $this->setMinAndMaxValues($this->getLayer()->getProductCollection());
            return $this;
        }

        $filterParams = explode(',', $filter);
        $filter = $this->dataProvider->validateFilter($filterParams[0]);
        if (!$filter) {
            $this->setMinAndMaxValues($this->getLayer()->getProductCollection());
            return $this;
        }

        $this->dataProvider->setInterval($filter);
        $priorFilters = $this->dataProvider->getPriorFilters($filterParams);
        if ($priorFilters) {
            $this->dataProvider->setPriorIntervals($priorFilters);
        }

        list($from, $to) = $filter;
        $this->setCurrentValue(['from' => $from ?: null, 'to' => $to ?: null]);

        $this->setMinAndMaxValues();

        $this->getLayer()->getProductCollection()->addFieldToFilter(
            'price',
            ['from' => $from, 'to' =>  $to]
        );

        $this->getLayer()->getState()->addFilter(
            $this->_createItem($this->_renderRangeLabel(empty($from) ? 0 : $from, $to), $filter)
        );

        return $this;
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * Get data array for building attribute filter items
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getItemsData()
    {
        if (!$this->isSliderActive()){
            return parent::_getItemsData();
        }

        $attribute = $this->getAttributeModel();
        $this->_requestVar = $attribute->getAttributeCode();

        /** @var \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection $productCollection */
        $productCollection = $this->getLayer()->getProductCollection();
        $facets = $productCollection->getFacetedData($attribute->getAttributeCode());
        $data = [];
        if (count($facets) > 1) {
            $lastFacet = array_key_last($facets);
            foreach ($facets as $key => $aggregation) {
                if (strpos($key, '_') === false) {
                    continue;
                }
                $count = $aggregation['count'];
                $isLast = $lastFacet === $key;
                $data[] = $this->prepareData($key, $count, $isLast);
            }
        }

        return $data;
    }

    /**
     * Prepare text of range label
     *
     * @param float|string $fromPrice
     * @param float|string $toPrice
     * @param boolean $isLast
     * @return float|\Magento\Framework\Phrase
     */
    protected function _renderRangeLabel($fromPrice, $toPrice, $isLast = false)
    {
        if (!$this->isSliderActive()){
            return parent::_renderRangeLabel($fromPrice, $toPrice, $isLast);
        }
        if ($fromPrice && !$toPrice){
            $toPrice = $this->getMaxValue();
        } else {
            if ($toPrice && $fromPrice != $toPrice){
                $toPrice += .01;
            }
        }
        return parent::_renderRangeLabel($fromPrice, $toPrice, false);
    }

    /**
     * Prepare filter data.
     *
     * @param string $key
     * @param int $count
     * @param boolean $isLast
     * @return array
     */
    private function prepareData($key, $count, $isLast = false)
    {
        [$from, $to] = explode('_', $key);
        if (!$from){
            $from = $this->getMinValue();
        }
        if (!$to){
            $to = $this->getMaxValue();
        }
        $label = $this->_renderRangeLabel($from, $to, $isLast);
        $data = [
            'label' => $label,
            'value' => $from,
            'count' => $count,
            'from' => $from,
            'to' => $to,
        ];

        return $data;
    }

    private function setMinAndMaxValues($productCollection = null){
        if (!$productCollection){
            $productCollection = clone $this->getLayer()->getProductCollection();
        }
        $this->setMinValue($productCollection->getMinPrice());
        $this->setMaxValue($productCollection->getMaxPrice());
    }

    protected function isSliderActive(){
        if ($this->_sliderActive === null){
            $this->_sliderActive = $this->_scopeConfig->isSetFlag('summa_theme/catalog/price_filter_slider', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }
        return $this->_sliderActive;
    }
}
