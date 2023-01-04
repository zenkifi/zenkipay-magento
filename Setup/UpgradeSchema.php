<?php

namespace Zenki\Zenkipay\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        //Add new fields to the created table
        if (version_compare($context->getVersion(), '2.4.0', '<')) {
            $table = $installer->getTable('zenkipay_credentials');

            //Check for the existence of the table
            if ($setup->getConnection()->isTableExists($table) == true) {
                $table = $installer
                    ->getConnection()
                    ->newTable($installer->getTable('zenkipay_credentials'))
                    ->addColumn('id', Table::TYPE_INTEGER, null, [
                        'identity' => true,
                        'unsigned' => true,
                        'nullable' => false,
                        'primary' => true,
                    ])
                    ->addColumn('sync_code', Table::TYPE_TEXT, 10, ['nullable' => false])
                    ->addColumn('api_key', Table::TYPE_TEXT, 100, ['nullable' => false])
                    ->addColumn('secret_key', Table::TYPE_TEXT, 100, ['nullable' => false])
                    ->addColumn('whsec', Table::TYPE_TEXT, 50, ['nullable' => false], 'Webhook signing secret')
                    ->addColumn('env', Table::TYPE_TEXT, 5, ['nullable' => false], 'Environment')
                    ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default' => Table::TIMESTAMP_INIT])
                    ->addColumn('updated_at', Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE])
                    ->setComment('Zenkipay Credentials Table')
                    ->setOption('type', 'InnoDB')
                    ->setOption('charset', 'utf8_general_ci');

                $installer->getConnection()->createTable($table);
            }

            $setup->endSetup();
        }
    }
}
