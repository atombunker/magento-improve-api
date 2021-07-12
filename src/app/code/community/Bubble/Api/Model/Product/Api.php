<?php

class Bubble_Api_Model_Product_Api extends Bubble_Api_Model_Product_Api_Resource
{  
    protected $_filtersMap = array(
        'product_id' => 'entity_id',
        'set'        => 'attribute_set_id',
        'type'       => 'type_id'
    );

    protected $_defaultProductAttributeList = array(
        'type_id',
        'category_ids',
        'website_ids',
        'name',
        'description',
        'short_description',
        'sku',
        'weight',
        'status',
        'url_key',
        'url_path',
        'visibility',
        'has_options',
        'gift_message_available',
        'price',
        'special_price',
        'special_from_date',
        'special_to_date',
        'tax_class_id',
        'tier_price',
        'meta_title',
        'meta_keyword',
        'meta_description',
        'custom_design',
        'custom_layout_update',
        'options_container',
        'image_label',
        'small_image_label',
        'thumbnail_label',
        'created_at',
        'updated_at'
    );

    public function __construct()
    {
        $this->_storeIdSessionField = 'product_store_id';
        $this->_ignoredAttributeTypes[] = 'gallery';
        $this->_ignoredAttributeTypes[] = 'media_image';
    }
    /**
     * Check if product type exists
     *
     * @param  $productType
     * @throw Mage_Api_Exception
     * @return void
     */
    protected function _checkProductTypeExists($productType)
    {
        if (!in_array($productType, array_keys(Mage::getModel('catalog/product_type')->getOptionArray()))) {
            $this->_fault('product_type_not_exists');
        }
    }

    /**
     * Check if attributeSet is exits and in catalog_product entity group type
     *
     * @param  $attributeSetId
     * @throw Mage_Api_Exception
     * @return void
     */
    protected function _checkProductAttributeSet($attributeSetId)
    {
        $attributeSet = Mage::getModel('eav/entity_attribute_set')->load($attributeSetId);
        if (is_null($attributeSet->getId())) {
            $this->_fault('product_attribute_set_not_exists');
        }
        if (Mage::getModel('catalog/product')->getResource()->getTypeId() != $attributeSet->getEntityTypeId()) {
            $this->_fault('product_attribute_set_not_valid');
        }
    }

    public function listOfAdditionalAttributes($productType, $attributeSetId)
    {
        $this->_checkProductTypeExists($productType);
        $this->_checkProductAttributeSet($attributeSetId);

        /** @var $product Mage_Catalog_Model_Product */
        $productAttributes = Mage::getModel('catalog/product')
            ->setAttributeSetId($attributeSetId)
            ->setTypeId($productType)
            ->getTypeInstance(false)
            ->getEditableAttributes();

        $result = array();
        foreach ($productAttributes as $attribute) {
            /* @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
            if ($attribute->isInSet($attributeSetId) && $this->_isAllowedAttribute($attribute)
                && !in_array($attribute->getAttributeCode(), $this->_defaultProductAttributeList)) {

                if ($attribute->isScopeGlobal()) {
                    $scope = 'global';
                } elseif ($attribute->isScopeWebsite()) {
                    $scope = 'website';
                } else {
                    $scope = 'store';
                }

                $result[] = array(
                    'attribute_id' => $attribute->getId(),
                    'code' => $attribute->getAttributeCode(),
                    'type' => $attribute->getFrontendInput(),
                    'required' => $attribute->getIsRequired(),
                    'scope' => $scope
                );
            }
        }

        return $result;
    }

    /**
     * Create new product.
     *
     * @param string $type
     * @param int $set
     * @param string $sku
     * @param array $productData
     * @param string $store
     * @return int
     */
    public function create($type, $set, $sku, $productData, $store = null)
    {
        if (!$type || !$set || !$sku) {
            $this->_fault('data_invalid');
        }

        $this->_checkProductTypeExists($type);
        $this->_checkProductAttributeSet($set);

        /** @var $product Mage_Catalog_Model_Product */
        $product = Mage::getModel('catalog/product');
        $product->setStoreId($this->_getStoreId($store))
            ->setAttributeSetId($set)
            ->setTypeId($type)
            ->setSku($sku);

        if (!isset($productData['stock_data']) || !is_array($productData['stock_data'])) {
            //Set default stock_data if not exist in product data
            $product->setStockData(array('use_config_manage_stock' => 0));
        }

        foreach ($product->getMediaAttributes() as $mediaAttribute) {
            $mediaAttrCode = $mediaAttribute->getAttributeCode();
            $product->setData($mediaAttrCode, 'no_selection');
        }

        $this->_prepareDataForSave($product, $productData);

        try {
            /**
             * @todo implement full validation process with errors returning which are ignoring now
             * @todo see Mage_Catalog_Model_Product::validate()
             */
            if (is_array($errors = $product->validate())) {
                $strErrors = array();
                foreach($errors as $code => $error) {
                    if ($error === true) {
                        $error = Mage::helper('catalog')->__('Attribute "%s" is invalid.', $code);
                    }
                    $strErrors[] = $error;
                }
                $this->_fault('data_invalid', implode("\n", $strErrors));
            }

            $product->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return $product->getId();
    }

    /**
     * Update product data
     *
     * @param int|string $productId
     * @param array $productData
     * @param string|int $store
     * @return boolean
     */
    public function update($productId, $productData, $store = null, $identifierType = null)
    {
        $product = $this->_getProduct($productId, $store, $identifierType);

        $this->_prepareDataForSave($product, $productData);

        try {
            /**
             * @todo implement full validation process with errors returning which are ignoring now
             * @todo see Mage_Catalog_Model_Product::validate()
             */
            if (is_array($errors = $product->validate())) {
                $strErrors = array();
                foreach($errors as $code => $error) {
                    if ($error === true) {
                        $error = Mage::helper('catalog')->__('Value for "%s" is invalid.', $code);
                    } else {
                        $error = Mage::helper('catalog')->__('Value for "%s" is invalid: %s', $code, $error);
                    }
                    $strErrors[] = $error;
                }
                $this->_fault('data_invalid', implode("\n", $strErrors));
            }

            $product->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return true;
    }

    /**
     *  Set additional data before product saved
     *
     *  @param    Mage_Catalog_Model_Product $product
     *  @param    array $productData
     *  @return   object
     */
    protected function _prepareDataForSave($product, $productData)
    {
        if (isset($productData['website_ids']) && is_array($productData['website_ids'])) {
            $product->setWebsiteIds($productData['website_ids']);
        }

        foreach ($product->getTypeInstance(true)->getEditableAttributes($product) as $attribute) {
            //Unset data if object attribute has no value in current store
            if (Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID !== (int) $product->getStoreId()
                && !$product->getExistsStoreValueFlag($attribute->getAttributeCode())
                && !$attribute->isScopeGlobal()
            ) {
                $product->setData($attribute->getAttributeCode(), false);
            }

            if ($this->_isAllowedAttribute($attribute)) {
                if (isset($productData[$attribute->getAttributeCode()])) {
                    $product->setData(
                        $attribute->getAttributeCode(),
                        $productData[$attribute->getAttributeCode()]
                    );
                } elseif (isset($productData['additional_attributes']['single_data'][$attribute->getAttributeCode()])) {
                    $product->setData(
                        $attribute->getAttributeCode(),
                        $productData['additional_attributes']['single_data'][$attribute->getAttributeCode()]
                    );
                } elseif (isset($productData['additional_attributes']['multi_data'][$attribute->getAttributeCode()])) {
                    $product->setData(
                        $attribute->getAttributeCode(),
                        $productData['additional_attributes']['multi_data'][$attribute->getAttributeCode()]
                    );
                }
            }
        }

        if (isset($productData['categories']) && is_array($productData['categories'])) {
            $product->setCategoryIds($productData['categories']);
        }

        if (isset($productData['websites']) && is_array($productData['websites'])) {
            foreach ($productData['websites'] as &$website) {
                if (is_string($website)) {
                    try {
                        $website = Mage::app()->getWebsite($website)->getId();
                    } catch (Exception $e) { }
                }
            }
            $product->setWebsiteIds($productData['websites']);
        }

        if (Mage::app()->isSingleStoreMode()) {
            $product->setWebsiteIds(array(Mage::app()->getStore(true)->getWebsite()->getId()));
        }

        if (isset($productData['stock_data']) && is_array($productData['stock_data'])) {
            $product->setStockData($productData['stock_data']);
        }

        if (isset($productData['tier_price']) && is_array($productData['tier_price'])) {
             $tierPrices = Mage::getModel('catalog/product_attribute_tierprice_api')
                 ->prepareTierPrices($product, $productData['tier_price']);
             $product->setData(Mage_Catalog_Model_Product_Attribute_Tierprice_Api::ATTRIBUTE_CODE, $tierPrices);
        }
    }

    /**
     * Retrieve product info
     *
     * @param int|string $productId
     * @param string|int $store
     * @param array      $attributes
     * @param string     $identifierType
     * @return array
     */
    public function info($productId, $store = null, $attributes = null, $identifierType = null)
    {
        // make sku flag case-insensitive
        if (!empty($identifierType)) {
            $identifierType = strtolower($identifierType);
        }

        $product = $this->_getProduct($productId, $store, $identifierType);

        $result = array( // Basic product data
            'product_id' => $product->getId(),
            'sku'        => $product->getSku(),
            'set'        => $product->getAttributeSetId(),
            'type'       => $product->getTypeId(),
            'categories' => $product->getCategoryIds(),
            'websites'   => $product->getWebsiteIds()
        );

        foreach ($product->getTypeInstance(true)->getEditableAttributes($product) as $attribute) {
            if ($this->_isAllowedAttribute($attribute, $attributes)) {
                $result[$attribute->getAttributeCode()] = $product->getData(
                                                                $attribute->getAttributeCode());
            }
        }

        return $result;
    }

    public function listPageWise($curPage = 1, $pageSize = 10, $filters = null, $store = null)
    {
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addStoreFilter($this->_getStoreId($store))
            ->addAttributeToSelect('name');
        
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
        $collection->setPageSize($pageSize)
                    ->setCurPage($curPage);
        $result = array();
        if ($collection->getSize()>($pageSize*(($curPage-1)))) {
            foreach ($collection as $product) {
                $result[] = array(
                'product_id' => $product->getId(),
                'sku'        => $product->getSku(),
                'name'       => $product->getName(),
                'set'        => $product->getAttributeSetId(),
                'type'       => $product->getTypeId(),
                'category_ids' => $product->getCategoryIds(),
                'website_ids'  => $product->getWebsiteIds()
            );
            }
        }
        return $result;
    }

    public function listSku($curPage = 1, $pageSize = 10, $filters = null, $store = null)
    {
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addStoreFilter($this->_getStoreId($store))
            ->addAttributeToSelect('name');
        
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
        $collection->setPageSize($pageSize)
                    ->setCurPage($curPage);
        $result = array();
        if ($collection->getSize()>($pageSize*(($curPage-1)))) {
            $result = $collection->getColumnValues('sku');
        }
        return $result;
    }
}