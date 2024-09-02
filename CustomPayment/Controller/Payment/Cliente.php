<?php

namespace Devhooks\CustomPayment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\Framework\HTTP\Client\Curl;

class Cliente extends Action
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
            $params = $this->getRequest()->getParams(); // Get request parameters
            $method = $this->getRequest()->getMethod(); // Get HTTP method
            $apiToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIyIiwianRpIjoiNGJhZDU1M2JhYmQ5NzYwNDQzZmYzZWVhYTJhZDgwZTNlZjdmNzY1N2Q5NDYyYTI5NmM2YWIwMjA1NTlhZDQ1NTk4MTgxN2IwNTFmYTA5M2IiLCJpYXQiOjE3MjQ0MTg5NTAuMTgyMjA5LCJuYmYiOjE3MjQ0MTg5NTAuMTgyMjE4LCJleHAiOjE3ODc0OTA5NTAuMTczMDk5LCJzdWIiOiIxMjMiLCJzY29wZXMiOlsiY2xpZW50ZS10YXJqZXRhcyIsImNsaWVudGUtdHJhbnNhY2Npb25lcyIsImNsaWVudGUtY2xpZW50ZXMiLCJjbGllbnRlLXN1c2NyaXBjaW9uZXMiLCJjbGllbnRlLXBsYW5lcyIsImNsaWVudGUtYW50aWZyYXVkZSIsImNsaWVudGUtd2ViaG9va3MiLCJjbGllbnRlLWNvbmNpbGlhY2lvbiIsImNsaWVudGUtdnRleCJdfQ.JlpM3jVY9ofjyyiSrLdFymTJXUxgo6_n0v-FulmLrhWQL9F-1M7v1ZT9K0TYvJIrPERAha-QpfwsNOQt49n2ertUYk5qXAw5FJKAKkbmx9pvlhqZEYi6YPVEN91gGpV3S4mXCK4BGaQSgx6aoSc6zVqaEab0fGpFiV65ecn9G8HYWv4Wfnbmk364jF-ZFBD68i4BGd5bICWYFAomXXCjrlW7uTwVq67BnSXjkwOpzTp2uN9GCF59V_SX87lexhCJ-cNnRDOigPnaYZqBNlDLoetxkRnxHMyM4lXAzjnS3Qei0GbCYFE_etTfNzKEO7JoOdieFUOrRfdbSA3J8RcA3A6J7psn75_4UGLW6M91agCnKgmOqymBOn9COR8mi78S9MJZyS_4C6ePPJXTl7scSAZkz1eysXD93Fi9-MBLLbYRpVxEk0lJOgZYJ1TrmoQWLmvYNtKyp8xGRFLOu_7tX4qHDTY_ZXi5Y8CCfNYeb_5a3KbtQxQKI67UaN4L9bu9Bnc_Hdzn4gwlB_zcsVWPFZhwDLww0ckAmV6oXVUS0lrPWeErfaOFay-WQEQyHRLmCn4nwVIqFt6Xuzx9WV3uDZUeZO4OJ85ASiRXOit8bQO81gn2nQRXSOPx_Bf-BWvAQb0dMO3JwuDjRqu-KBMt6L1Wu3qmWZmhht9o7oO5Ql4';
            $this->curl->addHeader("Authorization", "Bearer " . $apiToken);
            $this->curl->addHeader("Content-Type", "application/json");
    
            // Route the request to the correct API endpoint based on the action
            if ($method == 'GET') {
                if (isset($params['email'])) {
                    // Get customer by email
                    $apiUrl = 'https://api.sandbox.claropagos.com/v1/cliente/email/' . $params['email'];
                    $this->curl->get($apiUrl);
                } elseif (isset($params['cliente_id']) && isset($params['tarjeta'])) {
                    // Get cards by customer ID
                    $apiUrl = 'https://api.sandbox.claropagos.com/v1/cliente/' . $params['cliente_id'] . '/tarjeta';
                    $this->curl->get($apiUrl);
                }
            } elseif ($method == 'POST') {
                // Create a new customer or perform other POST actions
                $data = $this->restRequest->getBodyParams();
                $apiUrl = 'https://api.sandbox.claropagos.com/v1/cliente';
                $this->curl->post($apiUrl, json_encode($data));
            }
    
            // Process the response from the external API
            $response = $this->curl->getBody();
            return $result->setData(json_decode($response, true));
            
        } else {
            // The request is not from the internal Magento application
            return $result->setData(['error' => true, 'message' => 'Unauthorized access: Invalid Referer or Origin']);
        }
    }
    
    
}
