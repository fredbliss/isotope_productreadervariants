<?php

namespace IntelligentSpark\Module;

use Isotope\Module\ProductReader as Isotope_ProductReader;
use Isotope\Model\Product as Product;

class ProductReaderVariants extends Isotope_ProductReader {

    /**
     * Display a wildcard in the back end
     * @return string
     */
    public function generate()
    {
        if ('BE' === TL_MODE) {
            return $this->generateWildcard();
        }

        // Return if no product has been specified
        if (Input::getAutoItem('product', false, true) == '') {
            if ($this->iso_display404Page) {
                $this->generate404();
            } else {
                return '';
            }
        }

        return parent::generate();
    }

    /**
     * Generate module
     * @return void
     */
    protected function compile()
    {
        global $objPage;
        global $objIsotopeListPage;

        $objProduct = Product::findAvailableByIdOrAlias(Input::getAutoItem('product'));

        if (null === $objProduct) {
            $this->generate404();
        }

        $objProductVariants = $this->getProductVariants($objProduct->id);

        var_dump($objProductVariants);

        $arrConfig = array(
            'module'      => $this,
            'template'    => $this->iso_reader_layout ? : $objProduct->getType()->reader_template,
            'gallery'     => $this->iso_gallery ? : $objProduct->getType()->reader_gallery,
            'buttons'     => $this->iso_buttons,
            'useQuantity' => $this->iso_use_quantity,
            'jumpTo'      => $objIsotopeListPage ? : $objPage,
        );

        if (\Environment::get('isAjaxRequest')
            && \Input::post('AJAX_MODULE') == $this->id
            && \Input::post('AJAX_PRODUCT') == $objProduct->getProductId()
        ) {
            try {
                $objResponse = new HtmlResponse($objProduct->generate($arrConfig));
                $objResponse->send();
            } catch (\InvalidArgumentException $e) {
                return;
            }
        }

        $this->addMetaTags($objProduct);
        $this->addCanonicalProductUrls($objProduct);

        $this->Template->product       = $objProduct->generate($arrConfig);
        $this->Template->product_id    = $this->getCssId($objProduct);
        $this->Template->product_class = $this->getCssClass($objProduct);
        $this->Template->referer       = 'javascript:history.go(-1)';
        $this->Template->back          = $GLOBALS['TL_LANG']['MSC']['goBack'];
    }

    protected function getProductVariants($intPid) {

        $t             = Product::getTable();
        $arrColumns    = array();

        $arrTypes = \Database::getInstance()
            ->query("SELECT id FROM tl_iso_producttype WHERE variants='1'")
            ->fetchEach('id')
        ;

        if (empty($arrProductIds)) {
            return array();
        }

        $queryBuilder = new FilterQueryBuilder(
            Isotope::getRequestCache()->getFiltersForModules($this->iso_filterModules)
        );

        $arrColumns[] = "(
            ($t.id=$intPid AND $t.type NOT IN (" . implode(',', $arrTypes) . ")))";

        if (!empty($arrCacheIds) && is_array($arrCacheIds)) {
            $arrColumns[] = Product::getTable() . ".id IN (" . implode(',', $arrCacheIds) . ")";
        }

        // Apply new/old product filter
        if ($this->iso_newFilter == 'show_new') {
            $arrColumns[] = Product::getTable() . ".dateAdded>=" . Isotope::getConfig()->getNewProductLimit();
        } elseif ($this->iso_newFilter == 'show_old') {
            $arrColumns[] = Product::getTable() . ".dateAdded<" . Isotope::getConfig()->getNewProductLimit();
        }

        if ($this->iso_list_where != '') {
            $arrColumns[] = $this->iso_list_where;
        }

        if ($queryBuilder->hasSqlCondition()) {
            $arrColumns[] = $queryBuilder->getSqlWhere();
        }

        $arrSorting = Isotope::getRequestCache()->getSortingsForModules($this->iso_filterModules);

        if (empty($arrSorting) && $this->iso_listingSortField != '') {
            $direction = ($this->iso_listingSortDirection == 'DESC' ? Sort::descending() : Sort::ascending());
            $arrSorting[$this->iso_listingSortField] = $direction;
        }

        $objProducts = Product::findAvailableBy(
            $arrColumns,
            $queryBuilder->getSqlValues(),
            array(
                'order'   => 'c.sorting',
                'filters' => $queryBuilder->getFilters(),
                'sorting' => $arrSorting,
            )
        );

        return (null === $objProducts) ? array() : $objProducts->getModels();
    }

}