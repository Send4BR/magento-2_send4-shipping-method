# [SEND4](https://send4store.com) Shipping Method

### Instalação
```
$ cd /magento/app/code
$ git clone https://github.com/send4store/magento-2_send4-shipping-method Send4
$ cd ../../
$ php -f bin/magento module:status
$ php -f bin/magento module:enable Send4_Shipping
$ rm -rf var/cache var/di var/generation var/page_cache
$ php -f bin/magento setup:di:compile
$ php -f bin/magento cache:clean
$ sudo chmod -R 777 var
$ sudo chmod -R 777 pub
```

### Ambiente de teste, homologação e integração

No [documentação da API](https://documenter.getpostman.com/view/447313/send4-public-api/7TQAWsr) você tem acesso a todos os endpoints disponíveis. É nesse local que você irá testar as requisições realizadas pela sua integração.
