<?php
class Dealer4dealer_Xcore_Model_Catalog_Product_Api_V2 extends Mage_Catalog_Model_Product_Api_V2
{

    /**
     * Retrieve list of products with basic info (id, sku, type, set, name)
     *
     * @param null|object|array $filters
     * @param string|int $store
     * @param null $limit
     * @return array
     */
    public function items($filters = null, $store = null, $limit = null)
    {
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addStoreFilter($this->_getStoreId($store))
            ->addAttributeToSelect('name');

        if($limit) {
            $collection->setOrder('updated_at', 'ASC');
            $collection->setPageSize($limit);
        }

        /** @var $apiHelper Mage_Api_Helper_Data */
        $apiHelper = Mage::helper('api');
        $filters = $apiHelper->parseFilters($filters, $this->_filtersMap);
        try {
            foreach ($filters as $field => $value) {
                $collection->addFieldToFilter($field, $value);
            }
        } catch (Mage_Core_Exception $e) {
            $this->_fault('filters_invalid', $e->getMessage());
        }
        $result = array();
        foreach ($collection as $product) {
            $result[] = array(
                'product_id' => $product->getId(),
                'sku'        => $product->getSku(),
                'name'       => $product->getName(),
                'set'        => $product->getAttributeSetId(),
                'type'       => $product->getTypeId(),
                'category_ids' => $product->getCategoryIds(),
                'website_ids'  => $product->getWebsiteIds(),
                'updated_at'  => $product->getUpdatedAt(),
                'created_at'  => $product->getCreatedAt()
            );
        }
        return $result;
    }


    /**
     * Retrieve product info and attach the default
     * tax rate based on the tax_class_id.
     *
     * @param int|string $productId
     * @param string|int $store
     * @param stdClass   $attributes
     * @param string     $identifierType
     * @return array
     */
    public function info($productId, $store = null, $attributes = null, $identifierType = null)
    {
        $result = parent::info($productId, $store, $attributes, $identifierType);

        if (isset($result['tax_class_id'])) {
            $taxRate = $this->_getTaxRate($result['tax_class_id']);
            $result['tax_rate'] = $taxRate;
        }

        /** @var Dealer4dealer_Xcore_Model_Custom_Attribute $customAttribute */
        foreach ($this->_getCustomAttributes($result) as $customAttribute) {
            $result['xcore_custom_attributes'][] = $customAttribute->toArray();
        }

        return $result;
    }


    /**
     * @return array
     * @throws Mage_Api_Exception
     */
    public function taxClassesList()
    {
        $result = array();

        $taxClasses = Mage::getModel('tax/class')
            ->getCollection()
            ->addFieldToFilter('class_type', Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT)
            ->setOrder('class_id', 'DESC');

        foreach($taxClasses as $taxClass) {
            $result[] = $taxClass->toArray();
        }

        return $result;
    }

    /**
     * Get the default tax rate of a product based on the default
     * calculation country and the tax class id.
     *
     * @param int $taxClassId
     * @return float
     */
    protected function _getTaxRate($taxClassId)
    {
        $countryId      = Mage::getStoreConfig('tax/defaults/country');
        $rateRequest    = Mage::getSingleton('tax/calculation')->getRateRequest(false, false, false);

        $rateRequest->setCountryId($countryId);
        $rateRequest->setProductClassID($taxClassId);

        $taxRate = Mage::getSingleton('tax/calculation')->getRate($rateRequest);

        return $taxRate;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    protected function _getCustomAttributes($product)
    {
        $mapping = Mage::helper('dealer4dealer_xcore')->getMappingData(Dealer4dealer_Xcore_Helper_Data::XPATH_PRODUCT_COLUMNS_MAPPING);
        $product = Mage::getModel('catalog/product')->load($product['product_id']);

        $response = [];
        foreach ($mapping as $column) {
            $value = $product->getData($column['column']);

            // Get the frontend value instead of option value
            if($value) {
                $attribute = $product->getResource()->getAttribute($column['column']);
                if ($attribute) {
                    $value = $attribute->getFrontend()->getValue($product);
                }
            }

            /** @var Dealer4dealer_Xcore_Model_Custom_Attribute $customAttributes */
            $customAttributes = Mage::getModel('dealer4dealer_xcore/custom_attribute');
            $response[] = $customAttributes->setData([
                'key'       => $column['exact_key'],
                'value'     => $value
            ]);
        }

        return $response;
    }

    /**
     * @param Mage_Catalog_Model_Product
     */
    protected function dispatchEvents($product)
    {
        Mage::dispatchEvent('dealer4dealer_xcore_product_product_custom_attributes', array(
            'product' => $product,
        ));
    }
}