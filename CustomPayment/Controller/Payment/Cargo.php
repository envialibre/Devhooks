<?php

namespace Devhooks\CustomPayment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\Framework\HTTP\Client\Curl;

class Cargo extends Action
{
    protected $resultJsonFactory;
    protected $curl;
    protected $restRequest;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Curl $curl,
        RestRequest $restRequest
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->curl = $curl;
        $this->restRequest = $restRequest;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        // Get the base URL of your Magento store
        $storeManager = $this->_objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
        $baseUrl = $storeManager->getStore()->getBaseUrl();

        // Check the Referer or Origin header to verify the request origin
        $referer = $this->getRequest()->getHeader('Referer');
        $origin = $this->getRequest()->getHeader('Origin');

        // Determine if the request is from the internal application
        if (($referer && strpos($referer, $baseUrl) === 0) || ($origin && strpos($origin, $baseUrl) === 0)) {
            // The request is from the internal Magento application
            $data = $this->restRequest->getBodyParams();

            $apiUrl = 'https://api.sandbox.claropagos.com/v1/cargo';
            $apiToken = 'jdhfjdsf';

            $this->curl->addHeader("Authorization", "Bearer " . $apiToken);
            $this->curl->addHeader("Content-Type", "application/json");
            $this->curl->post($apiUrl, json_encode($data));

            $response = $this->curl->getBody();
            return $result->setData(json_decode($response, true));
        } else {
            // The request is not from the internal Magento application
            return $result->setData(['error' => true, 'message' => 'Unauthorized access: Invalid Referer or Origin']);
        }
    }
}
