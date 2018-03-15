<?php
namespace Send4\Shipping\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;

/**
 * Class Send4Shipping
 * @package Send4\Shipping\Model\Carrier
 */
class Send4Shipping extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'send4shipping';

    protected $_checkoutSession = null;

    protected $_logger;

    protected $_rateFactory;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Shipping\Model\Rate\ResultFactory $rateFactory,
        array $data = []
    )
    {
        $this->_checkoutSession = $checkoutSession;
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_logger = $logger;
        $this->_rateFactory = $rateFactory;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        $this->_logger->info('@send4 - Get allowed Methods');

        return [
            $this->getCarrierCode() => $this->getConfigData('name')
        ];
    }

    /**
     * @param RateRequest $request
     * @return bool|Result
     */
    public function collectRates(RateRequest $request)
    {
        /**
         * Method is active
         */
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $this->_logger->info('@send4 - Shipping method is active.');

        /**
         * Shipping results
         */
        return $this->_rateOptionFactory();
    }

    public function _rateOptionFactory()
    {

        /**
         * Get send4 dots
         */
        $dots = (object) $this->_getSend4Dots();

        if (!$dots->success) {
            return false;
        }

        /**
         * Shipping results
         */
        $result = $this->_rateFactory->create();

        foreach ($dots->dots as $dot) {

            /**
             * Description Pattern: Place name - Address - Complement - Aprox
             */
            $description = $dot['display_name'];

            if ($dot['address']){
                $description .= ' - ' . $dot['address'];
            }

            if ($dot['complement']){
                $description .= " - " . $dot['complement'];
            }

            if ($dot['distance']){
                $description .= ' - Aprox. ' . $dot['distance'];
            }

            $rate = $this->_rateMethodFactory->create();
            $rate->setCarrier($this->_code);
            $rate->setCarrierTitle($this->getConfigData('title'));
            $rate->setMethodTitle($description);
            $rate->setMethod($this->_code . '_#' . $dot['id']);
            $rate->setCost($this->getConfigData('price'));
            $rate->setMethodDescription($description);
            $rate->setPrice($this->getConfigData('price'));
            $result->append($rate);
        }

        return $result;
    }


    public function _ecommerceAuth()
    {
        $this->_logger->info('@send4 - E-commerce authentication.');

        /**
         * Get e-commerce infos from admin
         */
        $url = $this->getConfigData('url');
        $clientId = $this->getConfigData('client_id');
        $key = $this->getConfigData('client_secret');
        $grant_type = "client_credentials";

        /**
         * Prepare data to authentication
         */
        $data = [
            "grant_type" => $grant_type,
            "client_id" => $clientId,
            "client_secret" => $key
        ];

        $payload = json_encode($data);

        $ch = curl_init($url . "auth/client/login");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json"
            ]
        );

        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result, true);

        if (isset($response) && isset($response['access_token'])){
            $this->_logger->info('@send4 - E-commerce authentication success!');
            return $response['access_token'];
        }

        return false;

    }

    /**
     * Get dots from Send4
     *
     * @return mixed
     * @throws \Exception
     */
    protected function _getSend4Dots()
    {
        /**
         * E-commerce Authentication
         */
        $token = $this->_ecommerceAuth();

        if (!$token){
            return false;
        }

        /**
         * Get config data
         */
        $url = $this->getConfigData('url');
        $itens = $this->getConfigData('itens_to_return');

        /**
         * Get informed checkout zip code
         */
        $zip = $this->_checkoutSession->getQuote()->getShippingAddress()->getPostcode();

        $this->_logger->critical('itens:' . json_encode($itens));

        $data = [
            "itens_to_return" => ($itens ?? 5),
            "location" => [
                "postal_code" => $zip,
            ]
        ];

        $payload = json_encode($data);

        try {
            $ch = curl_init($url . "client/dots/closest");
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

            $this->_logger->info('Buscou os pontos com sucesso!');

        } catch (Exception $e) {
            return false;
        }

        $dots = json_decode($result, true);

        return $dots;

    }

}
