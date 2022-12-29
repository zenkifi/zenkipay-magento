<?php

namespace Zenki\Zenkipay\Model;

class Credentials extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'zenkipay_credentials';

    protected $_cacheTag = 'zenkipay_credentials';
    protected $_eventPrefix = 'zenkipay_credentials';

    protected function _construct()
    {
        $this->_init('Zenki\Zenkipay\Model\ResourceModel\Credentials');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues()
    {
        $values = [];

        return $values;
    }

    public function fetchOneBy($field, $value)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $connection->getTableName('zenkipay_credentials'); //gives table name with prefix

        $sql = 'SELECT * FROM ' . $tableName . ' WHERE ' . $field . ' = "' . $value . '" limit 1';
        $result = $connection->fetchAll($sql);

        if (count($result)) {
            return $result[0];
        }

        return false;
    }

    public function deleteAll()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $connection->getTableName('zenkipay_credentials'); //gives table name with prefix

        $sql = 'TRUNCATE TABLE ' . $tableName;
        $connection->fetchAll($sql);

        return true;
    }
}
