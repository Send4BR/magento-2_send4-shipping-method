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
            'name' =>  str_replace("  ", " ", $orderData['customer_firstname'] . " " . $orderData['customer_middlename'] . " " . $orderData['customer_lastname']),
            'email' => $orderData['customer_email'],
            'nin' => $orderData['customer_taxvat']
        ];

        $customer = [
            'invoice_number' => $order->getId(),
            'value' => $orderData['total_invoiced'],
            'insurance_value' => null,
            'shipping_company' => null,
            'customer' => $customer,
            'products' => []
        ];


        #if ($shipping == 'send4_shipping_send4_shipping') {

        $this->_logger->debug('$itens: ' . $itens);

        #}

        return $result;
    }
}
