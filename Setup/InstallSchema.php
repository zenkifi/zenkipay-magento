<?php

namespace Zenki\Zenkipay\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        $installer->startSetup();

        \Magento\Framework\App\ObjectManager::getInstance()
            ->get('Psr\Log\LoggerInterface')
            ->info('InstallSchema............');

        try {
            if (!$installer->tableExists('zenkipay_credentials')) {
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
        } catch (Exception $err) {
            \Magento\Framework\App\ObjectManager::getInstance()
                ->get('Psr\Log\LoggerInterface')
                ->info($err->getMessage());
        }

        $installer->endSetup();
    }
}
