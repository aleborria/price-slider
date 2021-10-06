<?php
/**
 * Catalog layer filter renderer
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace SummaTheme\LayeredNavigation\Block\Navigation;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Layer\Filter\DataProvider\Price as PriceDataProvider;
use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Json\EncoderInterface;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\LayeredNavigation\Block\Navigation\FilterRendererInterface;
use Magento\LayeredNavigation\Block\Navigation\FilterRenderer;
use Magento\Store\Model\ScopeInterface;
use Magento\CatalogSearch\Model\Layer\Filter\Price;

/**
 * Catalog layer filter renderer
 *
 * @api
 * @since 100.0.2
 */
class FilterPriceRenderer extends FilterRenderer implements FilterRendererInterface
{
    /**
     * @var FilterInterface
     */
    private $filter;

    /**
     * @var CatalogHelper
     */
    private $catalogHelper;

    /**
     * The Data role, used for Javascript mapping of slider Widget
     *
     * @var string
     */
    protected $dataRole = "range-price-slider";

    /**
     * @var EncoderInterface
     */
    private $jsonEncoder;


    /**
     * @var FormatInterface
     */
    protected $localeFormat;

    /**
     *
     * @param Context          $context       Template context.
     * @param CatalogHelper    $catalogHelper Catalog helper.
     * @param EncoderInterface $jsonEncoder   JSON Encoder.
     * @param FormatInterface  $localeFormat   Price format.
     * @param array            $data          Custom data.
     */
    public function __construct(
        Context $context,
        CatalogHelper $catalogHelper,
        EncoderInterface $jsonEncoder,
        FormatInterface $localeFormat,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->catalogHelper = $catalogHelper;
        $this->jsonEncoder  = $jsonEncoder;
        $this->localeFormat  = $localeFormat;
    }

    /**
     * @param FilterInterface $filter
     * @return string
     */
    public function render(FilterInterface $filter)
    {
        $html = '';
        $this->filter = $filter;
        if ($this->canRenderFilter()) {
            $this->assign('filterItems', $filter->getItems());
            $html = $this->_toHtml();
            $this->assign('filterItems', []);
        }

        return $html;
    }

    /**
     * @return FilterInterface
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * Indicates if the product count should be displayed or not.
     *
     * @return boolean
     */
    public function displayProductCount()
    {
        return $this->catalogHelper->shouldDisplayProductCountOnLayer();
    }

    /**
     * Return the config of the price slider JS widget.
     *
     * @return string
     */
    public function getJsonConfig()
    {
        return $this->jsonEncoder->encode($this->getConfig());
    }

    /**
     * Retrieve the data role
     *
     * @return string
     */
    public function getDataRole()
    {
        return $this->dataRole . "-" . $this->getFilter()->getRequestVar();
    }

    /**
     * {@inheritDoc}
     */
    protected function canRenderFilter()
    {
        return $this->getFilter() instanceof Price;
    }

    /**
     * Retrieve configuration
     *
     * @return array
     */
    protected function getConfig()
    {
        $config = [
            'minValue'         => $this->getMinValue(),
            'maxValue'         => $this->getMaxValue(),
            'currentValue'     => $this->getCurrentValue(),
            'priceFormat'      => $this->getPriceFormat(),
            'intervals'        => $this->getIntervals(),
            'urlTemplate'      => $this->getUrlTemplate(),
            'displayCount'     => false,//$this->displayProductCount(),
            'messageTemplates' => [
                'displayOne'    => __('1 product'),
                'displayCount'  => __('<%- count %> products'),
                'displayEmpty'  => __('No products in the selected range.'),
            ],
        ];

        if ($this->isManualCalculation() && ($this->getStepValue() > 0)) {
            $config['step'] = $this->getStepValue();
        }

        if ($this->getFilter()->getCurrencyRate()) {
            $config['rate'] = $this->getFilter()->getCurrencyRate();
        }

        return $config;
    }

    /**
     * Get Current system's Price format
     *
     * @return array
     * @throws NoSuchEntityException
     */
    protected function getPriceFormat()
    {
        return $this->localeFormat->getPriceFormat();
    }


    /**
     * Returns min value of the slider.
     *
     * @return int
     */
    protected function getMinValue()
    {
        $minValue = $this->getFilter()->getMinValue();
        if ($this->isManualCalculation() && ($this->getStepValue() > 0)) {
            $stepValue = $this->getStepValue();
            $minValue  = floor($minValue / $stepValue) * $stepValue;
        }

        return $minValue;
    }

    /**
     * Returns max value of the slider.
     *
     * @return int
     */
    protected function getMaxValue()
    {
        $maxValue = $this->getFilter()->getMaxValue();

        if ($this->isManualCalculation() && ($this->getStepValue() > 0)) {
            $stepValue = $this->getStepValue();
            $maxValue  = ceil($maxValue / $stepValue) * $stepValue;
        }

        return $maxValue;
    }

    /**
     * Returns values currently selected by the user.
     *
     * @return array
     */
    private function getCurrentValue()
    {
        $currentValue = $this->getFilter()->getCurrentValue();

        if (!is_array($currentValue)) {
            $currentValue = [];
        }

        if (!isset($currentValue['from']) || $currentValue['from'] === '') {
            $currentValue['from'] = $this->getMinValue();
        }

        if (!isset($currentValue['to']) || $currentValue['to'] === '') {
            $currentValue['to'] = $this->getMaxValue();
        }

        return $currentValue;
    }

    /**
     * Return available intervals.
     *
     * @return array
     */
    private function getIntervals()
    {
        $intervals = [];

        foreach ($this->getFilter()->getItems() as $item) {
            $intervals[] = ['value' => $item->getValue(), 'count' => $item->getCount()];
        }

        return $intervals;
    }

    /**
     * Retrieve filter URL template with placeholders for range.
     *
     * @return string
     */
    private function getUrlTemplate()
    {
        $filter = $this->getFilter();
        $item = current($this->getFilter()->getItems());

        $regexp = "/({$filter->getRequestVar()})=(-?[0-9]+)/";
        $replacement = '${1}=<%- from %>-<%- to %>';
        $url = preg_replace($regexp, $replacement, $item->getUrl());

        $regexp = "/({$filter->getRequestVar()})=<%- from %>-<%- to %>-(-?[0-9]+)/";

        return preg_replace($regexp, $replacement, $url);
    }

    /**
     * Check if price interval is manually set in the configuration
     *
     * @return bool
     */
    private function isManualCalculation()
    {
        $result      = false;
        $calculation = $this->_scopeConfig->getValue(PriceDataProvider::XML_PATH_RANGE_CALCULATION, ScopeInterface::SCOPE_STORE);

        if ($calculation === PriceDataProvider::RANGE_CALCULATION_MANUAL) {
            $result = true;
        }

        return $result;
    }

    /**
     * Retrieve the value for "Default Price Navigation Step".
     *
     * @return int
     */
    private function getStepValue()
    {
        $value = $this->_scopeConfig->getValue(PriceDataProvider::XML_PATH_RANGE_STEP, ScopeInterface::SCOPE_STORE);

        return (int) $value;
    }
}
