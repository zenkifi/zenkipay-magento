<?php

namespace Zenki\Zenkipay\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Psr\Log\LoggerInterface;

class InstallSchema implements InstallSchemaInterface
{
    protected $logger;

    public function __construct(LoggerInterface $logger_interface)
    {
        $this->logger = $logger_interface;
    }

    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $this->logger->debug('#InstallSchema', ['version' => $context->getVersion()]);

        $installer = $setup;
        $installer->startSetup();

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
                    ->setComment('Zenkipay Credentials Table');
                // ->setOption('type', 'InnoDB')
                // ->setOption('charset', 'utf8_general_ci');

                $installer->getConnection()->createTable($table);
            }
        } catch (\Exception $e) {
            $this->logger->error('#InstallSchema' . $e->getMessage());
        }

        $installer->endSetup();
    }
}
