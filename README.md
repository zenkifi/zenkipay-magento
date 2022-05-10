# Zenkipay-Magento2

Extensión de pagos con Zenkipay para Magento2 (v1.0.0)

## Instalación

Ir a la carpeta raíz del proyecto de Magento y seguir los siguiente pasos:

```bash
composer require zenki/zenkipay
php bin/magento module:enable Zenki_Zenkipay --clear-static-content
php bin/magento setup:upgrade
php bin/magento cache:clean
```

## Actualización

En caso de ya contar con el módulo instalado y sea necesario actualizar, seguir los siguientes pasos:

```bash
composer clear-cache
composer update zenki/zenkipay
bin/magento setup:upgrade
php bin/magento cache:clean
```

### 1. Administración. Configuración del módulo

Para configurar el módulo desde el panel de administración de la tienda diríjase a: Stores > Configuration > Sales > Payment Methods > Zenkipay
