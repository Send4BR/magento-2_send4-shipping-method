<?php

namespace Send4\Shipping\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Psr\Log\LoggerInterface as Logger;
use Send4\Shipping\Model\Carrier\Send4Shipping;

class SalesOrderAfterSave implements ObserverInterface
{
    /**
     * @var ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Send4Shipping
     */
    protected $send4Shipping;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param Logger $logger
     * @param Send4Shipping $send4Shipping
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        Logger $logger,
        Send4Shipping $send4Shipping
    )
    {
        $this->_objectManager = $objectManager;
        $this->logger = $logger;
        $this->send4Shipping = $send4Shipping;
    }

    /**
     * Order status change event
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /**
         * Get order
         * @var Order
         */
        $order = $observer->getEvent()->getOrder();

        /**
         * Get shipping method
         */
        $shipping = $order->getShippingMethod();

        /**
         * Check if ordered using send4
         * shipping method must start with send4
         */
        $regex = "/(^send4)/";
        if (preg_match($regex, $shipping)) {

            $this->logger->info('@send4 - Shipping method is Send4.');

            /**
             * Listen only when new order status is complete
             */
            if ($order instanceof \Magento\Framework\Model\AbstractModel) {
                if ($order->getState() == 'complete') {
                    $this->logger->info('@send4 - Order status is complete.');

                    /**
                     * Create order on Send4
                     */
                    $this->_createOrderSend4($order);
                }
            }
        }

    }

    /**
     * Create order on Send4
     * @param Order $order
     * @return mixed
     */
    public function _createOrderSend4($order)
    {

        /**
         * Get token
         */
        $token = $this->send4Shipping->_ecommerceAuth();

        /**
         * Prepare API URL
         */
        $APIBaseUrl = $this->send4Shipping->getConfigData('url');
        $APIOrderUrl = $APIBaseUrl . 'orders';

        /**
         * Get dot number
         */
        $regex = '/(?<=#).*$/';
        preg_match($regex, $order->getShippingMethod(), $dot, PREG_OFFSET_CAPTURE);

        if (!isset($dot) || !isset($dot[0]) || !isset($dot[0][0])){
            $this->logger->error('@send4 - Can not get dot number.');
            return false;
        }

        /**
         * Prepare data
         */
        $data = [
            'order' => [
                'dot'               => $dot[0][0],
                'customer' => [
                    'name'          => $order->getBillingAddress()->getFirstname() . ' ' . $order->getBillingAddress()->getLastname(),
                    'email'         => $order->getCustomerEmail(),
                    'nin'           => '00000000000',
                    'phone'         => $order->getBillingAddress()->getTelephone()
                ],
                'invoice_number'    => $order->getRealOrderId(),
                'shipping_company'  => null,
                'value'             => $order->getTotalInvoiced(),
                'insurance_value'   => null,
                'volumetry'         => [
                    'width'         => 0,
                    'weight'         => 0,
                    'length'         => 0,
                    'height'         => 0,
                ],
                'photo'             => null,
                'signature'         => null,
                'ordered_at'        => $order->getCreatedAt(),
                'products'          => []
            ]
        ];

        /** @var Item $item */
        foreach ($order->getItems() as $item) {
            $itemData = [
                'name'          => $item->getName(),
                'id_code'       => $item->getId(),
                'quantity'      => $item->getQtyInvoiced(),
                'volumetry'     => [
                    'width'         => 0,
                    'weight'         => 0,
                    'length'         => 0,
                    'height'         => 0,
                ]
            ];
            array_push($data['order']['products'], $itemData);
        }

        $this->logger->info('@send4 - Order information prepared.');

        /**
         * Start curl
         */
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $APIOrderUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);

        /**
         * Post fields
         */
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        /**
         * Header and token
         */
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Authorization: Bearer " . $token
        ));

        $responseJson = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($responseJson, true);

        $this->logger->info('@send4 - API Response -> ', $response);

        if (isset($response['status']) && $response['status'] == 'error') {
            $this->logger->error(
                sprintf('@send4 - API Return Message -> %s | Order number %s of customer %s  ',
                    $response['message'],
                    $order->getRealOrderId(),
                    $order->getCustomerEmail()
                )
            );
        }

    }

}