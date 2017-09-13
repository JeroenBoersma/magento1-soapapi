<?php
class Dealer4dealer_Xcore_Model_Customer_Customer_Api_V2 extends Mage_Customer_Model_Customer_Api_V2
{
    /**
     * Retrieve customers data by filters and limit
     *
     * @param  object|array $filters
     * @param null $limit
     * @return array
     */
    public function items($filters, $limit = null)
    {
        $collection = Mage::getModel('customer/customer')->getCollection()->addAttributeToSelect('*');

        if($limit) {
            $collection->setOrder('updated_at', 'ASC');
            $collection->setPageSize($limit);
        }

        /** @var $apiHelper Mage_Api_Helper_Data */
        $apiHelper = Mage::helper('api');
        $filters = $apiHelper->parseFilters($filters, $this->_mapAttributes);
        try {
            foreach ($filters as $field => $value) {
                $collection->addFieldToFilter($field, $value);
            }
        } catch (Mage_Core_Exception $e) {
            $this->_fault('filters_invalid', $e->getMessage());
        }
        $result = array();
        foreach ($collection as $customer) {
            $data = $customer->toArray();
            $row  = array();
            foreach ($this->_mapAttributes as $attributeAlias => $attributeCode) {
                $row[$attributeAlias] = (isset($data[$attributeCode]) ? $data[$attributeCode] : null);
            }
            foreach ($this->getAllowedAttributes($customer) as $attributeCode => $attribute) {
                if (isset($data[$attributeCode])) {
                    $row[$attributeCode] = $data[$attributeCode];
                }
            }
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Create new customer
     *
     * @param array $customerData
     * @return int
     */
    public function create($customerData)
    {
        $customerId = parent::create($customerData);

        if($customerId) {
            if ($customerData->xcore_custom_attributes) {
                $customAttributes = $customerData->xcore_custom_attributes;
                $customer = Mage::getModel('customer/customer')->load($customerId);
                foreach ($customAttributes as $attribute) {
                    $customAttribute = $this->_getCustomAttributeMapping($attribute->key);
                    if ($customAttribute['column']) {
                        $customer->setData($customAttribute['column'], $attribute->value);
                    }
                }
                $customer->save();
            }
        }
        return $customerId;
    }

    /**
     * Update customer data
     *
     * @param int $customerId
     * @param array $customerData
     * @return boolean
     */
    public function update($customerId, $customerData)
    {
        if($customerData->xcore_custom_attributes) {
            $customAttributes = $customerData->xcore_custom_attributes;
            foreach($customAttributes as $attribute) {
                $customAttribute = $this->_getCustomAttributeMapping($attribute->key);
                if($customAttribute['column']) {
                    $customerData->{$customAttribute['column']} = $attribute->value;
                }
            }
        }

        return parent::update($customerId, $customerData);;
    }



    /**
     * Retrieve customer data
     *
     * @param int $customerId
     * @param array $attributes
     * @return array
     */
    public function info($customerId, $attributes = null)
    {
        $result = parent::info($customerId, $attributes);

        $customer = Mage::getModel('customer/customer')->load($customerId);

        /** @var Dealer4dealer_Xcore_Model_Custom_Attribute $customAttribute */
        foreach ($this->_getCustomAttributes($customer) as $customAttribute) {
            $result['xcore_custom_attributes'][] = $customAttribute->toArray();
        }

        return $result;
    }

    /**
     * @param Mage_Customer_Model_Customer $customer
     * @return array
     */
    protected function _getCustomAttributes($customer)
    {
        $mapping = Mage::helper('dealer4dealer_xcore')->getMappingData(Dealer4dealer_Xcore_Helper_Data::XPATH_CUSTOMER_COLUMNS_MAPPING, $customer->getStoreId());

        $response = [];
        foreach ($mapping as $column) {
            $value = $customer->getData($column['column']);

            // Get the frontend value instead of option value
            if($value) {
                $attribute = $customer->getResource()->getAttribute($column['column']);
                if ($attribute) {
                    $value = $attribute->getFrontend()->getValue($customer);
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

    protected function _getCustomAttributeMapping($customAttribute)
    {
        $mapping = Mage::helper('dealer4dealer_xcore')->getMappingData(Dealer4dealer_Xcore_Helper_Data::XPATH_CUSTOMER_COLUMNS_MAPPING);

        foreach ($mapping as $column) {
            if($column['exact_key'] == $customAttribute) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param Mage_Customer_Model_Customer
     */
    protected function dispatchEvents($customer)
    {
        Mage::dispatchEvent('dealer4dealer_xcore_customer_customer_custom_attributes', array(
            'customer' => $customer,
        ));
    }
}