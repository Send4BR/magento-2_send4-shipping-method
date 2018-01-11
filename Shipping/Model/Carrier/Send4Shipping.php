<?php
namespace Send4\Shipping\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;

class Send4Shipping extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'send4_shipping';

    protected $_checkoutSession = null;

    protected $_logger;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        array $data = []
    ) {
        $this->_checkoutSession = $checkoutSession;
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_logger = $logger;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return [
            'send4_shipping' => $this->getConfigData('name')
        ];
    }

    /**
     * @param RateRequest $request
     * @return bool|Result
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $result = $this->_rateResultFactory->create();

        $token = $this->_ecommerceAuth();

        $getSend4Dots = $this->_getSend4Dots($token);

        if ( !empty($getSend4Dots['dots']) ) {
            foreach($getSend4Dots['dots'] as $dot) {
                $result->append($this->_getSend4Rates($dot));
            }
        }

        return $result;
    }



    protected function _ecommerceAuth()
    {
        $url = $this->getConfigData('url');
        $clientId = $this->getConfigData('client_id');
        $key = $this->getConfigData('client_secret');
        $grant_type = "client_credentials";

        $data = [
            "grant_type" => $grant_type,
            "client_id" => $clientId,
            "client_secret" => $key
        ];

        $payload = json_encode($data);

        #$this->_logger->debug('url:' . $payload);

        $ch = curl_init( $url . "auth/connect");

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json"
            ]
        );

        $result = curl_exec($ch);
        curl_close($ch);
        $retorno = json_decode($result, true);

        return $retorno['token'];

    }


    protected function _getSend4Dots($token)
    {

        $url = $this->getConfigData('url');

        $shippingAddress = $this->_checkoutSession->getQuote()->getShippingAddress();
        $zip = $shippingAddress->getPostcode();

        $itens = $this->getConfigData('items_to_returno');

        $address = $this->_getFullAddress($zip);
        $add = json_decode($address);

        $data = [
            "items_to_return" => $itens,
            "location" => [
                "address" => $add->logradouro,
                "number" => "",
                "complement" => "",
                "neighbor" => $add->bairro,
                "city "=> $add->cidade,
                "state" => $add->uf,
                "country" => "Brasil",
                "postal_code" => $zip,
                "lat" => null,
                "lng" => null
            ]
        ];

        $payload = json_encode($data);

        $ch = curl_init($url . "dots/closests");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer " . $token,
                "Contet-Lenght: " . strlen($payload)
            ]
        );

        $result = curl_exec($ch);
        curl_close($ch);

        $dots = json_decode($result, true);
        return $dots;

    }

    protected function _getFullAddress($cep)
    {
        $webservice = 'http://cep.republicavirtual.com.br/web_cep.php';
        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_URL, $webservice . '?cep='. urlencode($cep) . '&formato=JSON');
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $resultado = curl_exec($ch);
        curl_close($ch);

        return $resultado;
    }

    protected function _getSend4Rates($dot)
    {

        $address = $dot['address'] . " - " . $dot['complement'] . " - ".$dot['neighbor'] . ", " . $dot['zip_code'];

        $method = $this->_rateMethodFactory->create();
        $method->setCarrier($this->_code . '_' . $dot['id']);
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod($this->_code);
        $method->setMethodTitle($dot['id'] . " - " . $dot['trade_name'] . " - " . $dot['complement']);
        $method->setMethodDescription($address);
        $method->setPrice($this->getConfigData('price'));
        $method->setCost(0);

        return $method;
    }

}
