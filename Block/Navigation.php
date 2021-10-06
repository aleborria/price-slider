<?php
/**
 * Catalog layer filter renderer
 */
namespace SummaTheme\LayeredNavigation\Block;

/**
 * Catalog layered navigation view block
 *
 * @api
 * @since 100.0.2
 */
class Navigation extends \Magento\LayeredNavigationStaging\Block\Navigation
{
    /**
     * Apply layer
     *
     * @return \SummaTheme\LayeredNavigation\Block\Navigation
     */
    protected function _prepareLayout()
    {
        if (!$this->_scopeConfig->isSetFlag('summa_theme/catalog/price_filter_slider', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)){
            return parent::_prepareLayout();
        }

        $priceFilter = null;
        foreach ($this->filterList->getFilters($this->_catalogLayer) as $filter) {
            if ($filter instanceof \SummaTheme\LayeredNavigation\Model\Layer\Filter\Price){
                $priceFilter = $filter;
            } else {
                $filter->apply($this->getRequest());
            }
        }

        if ($priceFilter){
            $priceFilter->apply($this->getRequest());
        }

        $this->getLayer()->apply();

        return $this;
    }
}
