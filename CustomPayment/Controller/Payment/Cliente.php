<?php

namespace Devhooks\CustomPayment\Controller\Payment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class Cliente extends Action
{
    protected $resultJsonFactory;
    protected $curl;
    protected $restRequest;
    protected $logger;
    protected $storeManager;
    protected $scopeConfig;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Curl $curl,
        RestRequest $restRequest,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->curl = $curl;
        $this->restRequest = $restRequest;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
    }

    protected function getApiCredentials()
    {
        $apiUrl = $this->scopeConfig->getValue('payment/custompayment/api_url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $apiToken = $this->scopeConfig->getValue('payment/custompayment/api_token', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    
        return ['api_url' => $apiUrl, 'api_token' => $apiToken];
    }

    public function execute()
    {
        // Initialize the result object
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
    
            $params = $this->restRequest->getBodyParams(); // Get request parameters
            $this->logger->info('Received payment data', ['params' => $params]);
    
            // Get the API credentials from the configuration
            $credentials = $this->getApiCredentials();
            $apiToken = $credentials['api_token']; // Retrieve api_token from configuration
            $apiUrl = $credentials['api_url']; // Retrieve api_url from configuration

            $this->curl->addHeader("Authorization", "Bearer " . $apiToken);
            $this->curl->addHeader("Content-Type", "application/json");
    
            // Step 1: Create or Get Customer by Email
            $this->logger->info('Attempting to create or retrieve customer');
            $clienteId = $this->createOrGetCustomer($params['cliente']);
            if (!$clienteId) {
                $this->logger->error('Failed to create or retrieve cliente', ['clienteData' => $params['cliente']]);
                return $result->setData(['success' => false, 'message' => 'Failed to create or retrieve cliente.']);
            }
            $this->logger->info('Successfully retrieved customer', ['clienteId' => $clienteId]);
    
            // Step 2: Check if Card Exists
            $this->logger->info('Checking if card exists for customer', ['clienteId' => $clienteId]);
            $existingCard = $this->checkIfCardExists($clienteId, $params['tarjeta']['pan']);
            if ($existingCard) {
                // Card exists, update CVV and proceed with payment
                $this->logger->info('Card exists, updating CVV', ['token' => $existingCard['token']]);
                $token = $this->updateCardCvv($existingCard['token'], $params['tarjeta']['cvv2']);
            } else {
                // Card does not exist, create a new card
                $this->logger->info('Card does not exist, creating new card');
                $token = $this->createCard($clienteId, $params['tarjeta']);
            }
    
            if (!$token) {
                $this->logger->error('Failed to process card information', ['clienteId' => $clienteId]);
                return $result->setData(['success' => false, 'message' => 'Failed to process card information.']);
            }
            $this->logger->info('Successfully processed card', ['token' => $token]);
    
            // Step 3: Process the Payment
            return $this->processPayment($token, $params['pedido'], $clienteId, $result);
        } else {
            // The request is not from the internal Magento application
            $this->logger->error('Unauthorized access attempt detected', ['referer' => $referer, 'origin' => $origin]);
            return $result->setData(['error' => true, 'message' => 'Unauthorized access: Invalid Referer or Origin']);
        }
    }
    

    protected function createOrGetCustomer($clienteData)
    {
        // Retrieve the base API URL from the configuration
        $credentials = $this->getApiCredentials();
        $baseApiUrl = $credentials['api_url'];
    
        // Attempt to retrieve the customer by email
        $apiUrl = $baseApiUrl . '/cliente/email/' . $clienteData['email'];
        $this->curl->get($apiUrl);
        $response = json_decode($this->curl->getBody(), true);
    
        if ($response['status'] === 'success' && isset($response['data']['cliente']['id'])) {
            $this->logger->info('Customer found', ['email' => $clienteData['email']]);
            return $response['data']['cliente']['id'];
        }
    
        // If customer does not exist, create a new one
        $apiUrl = $baseApiUrl . '/cliente';
        $this->curl->post($apiUrl, json_encode($clienteData));
        $response = json_decode($this->curl->getBody(), true);
    
        if ($response['status'] === 'success' && isset($response['data']['cliente']['id'])) {
            $this->logger->info('Customer created successfully', ['clienteData' => $clienteData]);
            return $response['data']['cliente']['id'];
        }
    
        $this->logger->error('Failed to create customer', ['clienteData' => $clienteData, 'response' => $response]);
        return null;
    }
    

    protected function checkIfCardExists($clienteId, $creditCardNumber)
    {
        // Retrieve the base API URL from the configuration
        $credentials = $this->getApiCredentials();
        $baseApiUrl = rtrim($credentials['api_url'], '/');  // Ensure no trailing slash
    
        // Construct the full API URL
        $apiUrl = $baseApiUrl . '/cliente/' . $clienteId . '/tarjeta';
        
        // Make the API request
        $this->curl->get($apiUrl);
        $response = json_decode($this->curl->getBody(), true);
    
        if ($response['status'] === 'success' && !empty($response['data']['tarjetas']['data'])) {
            $maskedPan = preg_replace('/(\d{6})(\d{6})(\d{4})/', '$1******$3', $creditCardNumber);
    
            foreach ($response['data']['tarjetas']['data'] as $card) {
                if ($card['pan'] === $maskedPan) {
                    $this->logger->info('Card found for customer', ['clienteId' => $clienteId, 'maskedPan' => $maskedPan]);
                    return $card;
                }
            }
        }
    
        $this->logger->info('No card found for customer', ['clienteId' => $clienteId]);
        return null;
    }
    

    protected function updateCardCvv($token, $cvv)
    {
        // Retrieve the base API URL from the configuration
        $credentials = $this->getApiCredentials();
        $baseApiUrl = $credentials['api_url'];

        // Update the CVV for the existing card
        $apiUrl = $baseApiUrl.'/tarjeta/'. $token;
        $this->curl->setOption(CURLOPT_CUSTOMREQUEST, 'PUT');
        $this->curl->addHeader("Content-Type", "application/json");
        $this->curl->post($apiUrl, json_encode(['cvv2' => $cvv]));
        $response = json_decode($this->curl->getBody(), true);
    
        if ($response['status'] === 'success') {
            $this->logger->info('CVV updated successfully', ['token' => $token]);
            return $response['data']['tarjeta']['token'];
        }
    
        $this->logger->error('Failed to update CVV', ['token' => $token, 'response' => $response]);
        return null;
    }
    

    protected function createCard($clienteId, $tarjetaData)
    {
        // Retrieve the base API URL from the configuration
        $credentials = $this->getApiCredentials();
        $baseApiUrl = $credentials['api_url'];

        // Create a new card for the customer
        $tarjetaData['cliente_id'] = $clienteId;
        $apiUrl = $baseApiUrl.'/tarjeta';
        $this->curl->post($apiUrl, json_encode($tarjetaData));
        $response = json_decode($this->curl->getBody(), true);

        if ($response['status'] === 'success') {
            $this->logger->info('Card created successfully', ['clienteId' => $clienteId]);
            return $response['data']['tarjeta']['token'];
        }

        $this->logger->error('Failed to create card', ['clienteId' => $clienteId, 'response' => $response]);
        return null;
    }

    protected function processPayment($token, $pedidoData, $clienteId, $result)
    {

        // Get API credentials from configuration
        $credentials = $this->getApiCredentials();
        $apiUrl = $credentials['api_url'];
        $apiToken = $credentials['api_token'];

        // Get the currency code from Magento settings
        $currencyCode = $this->storeManager->getStore()->getCurrentCurrencyCode();
    
        // Process the payment using the card token
        $paymentData = [
            'monto' => $pedidoData['total_monto'] ?? 0,  // Ensure there's a fallback in case 'total_monto' is missing
            'moneda' => $currencyCode,  // Use the extracted currency code
            'metodo_pago' => 'tarjeta',
            'tarjeta' => [
                'token' => $token,
            ],
            'pedido' => $pedidoData,
            'cliente' => [
                'id' => $clienteId,
            ],
        ];
    
        // Ensure the method is POST
        $this->curl->setOption(CURLOPT_CUSTOMREQUEST, 'POST'); // Explicitly set to POST, if needed
        $apiUrl = $apiUrl.'/cargo';
        $this->curl->post($apiUrl, json_encode($paymentData));
        $response = json_decode($this->curl->getBody(), true);
    
        if ($response['status'] === 'success') {
            $this->logger->info('Payment processed successfully', ['clienteId' => $clienteId, 'pedidoId' => $pedidoData['id_externo']]);
            return $result->setData(['success' => true, 'message' => 'Payment processed successfully.', 'response' => $response]);
        }
    
        $this->logger->error('Failed to process payment', ['clienteId' => $clienteId, 'response' => $response]);
        return $result->setData(['success' => false, 'message' => 'Failed to process payment.', 'response' => $response]);
    }
    
    
}
