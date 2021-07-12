<?php

class Bubble_Api_Model_Store_Api extends Mage_Api_Model_Resource_Abstract
{
    public function items()
    {
        $result = [];
        $allStores = Mage::app()->getStores();
        foreach ($allStores as $store)
        {
            $storeData = [];
            $storeData['store_id'] = $store->getId();
            $storeData['code'] = $store->getCode();
            $storeData['website_id'] = $store->getWebsiteId();
            $storeData['group_id'] = $store->getGroupId();
            $storeData['name'] = $store->getName();
            $storeData['sort_order'] = $store->getSortOrder();
            $storeData['is_active'] = $store->getIsActive();
            $result[] = $storeData;
        }
        return $result;
    }
}