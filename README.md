# Zenkipay-Magento2

Zenkipay payment extension for Magento2 (v0.3.3)

## PREREQUISITES

You need to have installed Composer v2.

## Installation

Go to your root project and follow the next steps:

```bash
composer require zenki/zenkipay-magento
bin/magento module:enable Zenki_Zenkipay --clear-static-content
bin/magento setup:upgrade
bin/magento cache:clean
```

## Update

If you already have installed this module and you need to update it, follow the next steps:

```bash
composer clear-cache
composer update zenki/zenkipay-magento
bin/magento setup:upgrade
php bin/magento cache:clean
```

### 1. Administration. Module configuration

Once you logged in as an admin from the store, go to: Stores > Configuration > Sales > Payment Methods > Zenkipay
