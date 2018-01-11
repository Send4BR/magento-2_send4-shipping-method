<?php

namespace Send4\Shipping\Model\Plugin;

class OrderStatePlugin
{

    protected $_logger;

    public function __construct(
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_logger = $logger;
    }

    /**
     * @param \Magento\Sales\Api\OrderRepositoryInterface $subject
     * @param \Magento\Sales\Api\Data\OrderInterface $result
     * @return mixed
     * @throws \Exception
     */
    public function afterSave(
        \Magento\Sales\Api\OrderRepositoryInterface $subject,
        $result
    ) {

        $order = $result;


        $firstname = $order->getCustomerFirstname();
        $lastname = $order->getCustomerLastname();
        $taxvat = $order->getCustomerTaxvat();
        $shipping = $order->getShippingMethod();
        $shippingName = $order->getMethod();

        $shippingAddress = $order->getShippingAddress();
        $city = $shippingAddress->getCity();
        $postcode = $shippingAddress->getPostcode();

        $data = $order->getData();
        $orderData = json_encode($data);

        $itens = json_encode($order->getItems()->getData());

        $customer = [
            'name' =>  trim(str_replace("  ", " ", $orderData['customer_firstname'] . " " . $orderData['customer_middlename'] . " " . $orderData['customer_lastname'])),
            'email' => trim(strtolower($orderData['customer_email'])),
            'nin' => '08518970962',
            'phone' => '41995405366'
        ];

        $payload = [
            'order' => [
                'invoice_number' => $order->getId(),
                'value' => $orderData['total_invoiced'],
                'customer' => $customer,
                'is_reverse' => 0,
                'insurance_value' => 0.0,
                'products' => [],
                'volumetry' => [
                    'width' => 1.0,
                    'weight' => 1.0,
                    'length' => 1.0,
                    'height' => 1.0
                ]
            ]
        ];


        $url = "https://staging-api.send4.com.br/v1/";

        $payload = json_encode($payload);

        $token = $this->_ecommerceAuth();

        $ch = curl_init( $url . "orders");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer {$token}"
            ]
        );
        $result = curl_exec($ch);
        curl_close($ch);
        $retorno = json_decode($result, true);

        return $result;
    }

    protected function _ecommerceAuth()
    {
        #$url = $this->getConfigData('url');
        $clientId = 6;
        $key = "1fbbf309195b6b40aa842705f61a8b04";
        $grant_type = "client_credentials";

        $data = [
            "grant_type" => $grant_type,
            "client_id" => $clientId,
            "client_secret" => $key
        ];

        $payload = json_encode($data);

        $ch = curl_init( "https://staging-api.send4.com.br/v1/" . "auth/connect");

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
}
