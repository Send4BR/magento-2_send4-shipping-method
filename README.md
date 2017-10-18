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

### Chaves de acesso

Acesse o ambiente de teste, homologação e integração [sandbox](https://tools.send4store.com/). Crie uma conta, cadastre seu e-commerce e obtenha seu `client_id` e `client_secret`. Após a instalação do plugin na plataforma, realize a configuração com a utilização desses dados.

### Ambiente de teste, homologação e integração

No [sandbox](https://tools.send4store.com/) você tem acesso a todos os pedidos criados e clientes vínculados. É nesse local que você irá testar as requisições realizadas pela sua integração.
