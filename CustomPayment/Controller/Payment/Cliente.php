<?php

namespace Devhooks\CustomPayment\Controller\Payment;

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

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Curl $curl,
        RestRequest $restRequest,
        LoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->curl = $curl;
        $this->restRequest = $restRequest;
        $this->logger = $logger;
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

            $params = $this->restRequest->getBodyParams(); // Get request parameters
            $this->logger->info('Received payment data', ['params' => $params]);

            $apiToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIyIiwianRpIjoiNGJhZDU1M2JhYmQ5NzYwNDQzZmYzZWVhYTJhZDgwZTNlZjdmNzY1N2Q5NDYyYTI5NmM2YWIwMjA1NTlhZDQ1NTk4MTgxN2IwNTFmYTA5M2IiLCJpYXQiOjE3MjQ0MTg5NTAuMTgyMjA5LCJuYmYiOjE3MjQ0MTg5NTAuMTgyMjE4LCJleHAiOjE3ODc0OTA5NTAuMTczMDk5LCJzdWIiOiIxMjMiLCJzY29wZXMiOlsiY2xpZW50ZS10YXJqZXRhcyIsImNsaWVudGUtdHJhbnNhY2Npb25lcyIsImNsaWVudGUtY2xpZW50ZXMiLCJjbGllbnRlLXN1c2NyaXBjaW9uZXMiLCJjbGllbnRlLXBsYW5lcyIsImNsaWVudGUtYW50aWZyYXVkZSIsImNsaWVudGUtd2ViaG9va3MiLCJjbGllbnRlLWNvbmNpbGlhY2lvbiIsImNsaWVudGUtdnRleCJdfQ.JlpM3jVY9ofjyyiSrLdFymTJXUxgo6_n0v-FulmLrhWQL9F-1M7v1ZT9K0TYvJIrPERAha-QpfwsNOQt49n2ertUYk5qXAw5FJKAKkbmx9pvlhqZEYi6YPVEN91gGpV3S4mXCK4BGaQSgx6aoSc6zVqaEab0fGpFiV65ecn9G8HYWv4Wfnbmk364jF-ZFBD68i4BGd5bICWYFAomXXCjrlW7uTwVq67BnSXjkwOpzTp2uN9GCF59V_SX87lexhCJ-cNnRDOigPnaYZqBNlDLoetxkRnxHMyM4lXAzjnS3Qei0GbCYFE_etTfNzKEO7JoOdieFUOrRfdbSA3J8RcA3A6J7psn75_4UGLW6M91agCnKgmOqymBOn9COR8mi78S9MJZyS_4C6ePPJXTl7scSAZkz1eysXD93Fi9-MBLLbYRpVxEk0lJOgZYJ1TrmoQWLmvYNtKyp8xGRFLOu_7tX4qHDTY_ZXi5Y8CCfNYeb_5a3KbtQxQKI67UaN4L9bu9Bnc_Hdzn4gwlB_zcsVWPFZhwDLww0ckAmV6oXVUS0lrPWeErfaOFay-WQEQyHRLmCn4nwVIqFt6Xuzx9WV3uDZUeZO4OJ85ASiRXOit8bQO81gn2nQRXSOPx_Bf-BWvAQb0dMO3JwuDjRqu-KBMt6L1Wu3qmWZmhht9o7oO5Ql4';
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
            $this->logger->info('Processing payment', ['pedidoData' => $params['pedido'], 'clienteId' => $clienteId]);
            $paymentSuccess = $this->processPayment($token, $params['pedido'], $clienteId);
            if ($paymentSuccess) {
                $this->logger->info('Payment processed successfully', ['pedidoData' => $params['pedido']]);
                return $result->setData(['success' => true, 'message' => 'Payment processed successfully.']);
            } else {
                $this->logger->error('Failed to process payment', ['pedidoData' => $params['pedido']]);
                return $result->setData(['success' => false, 'message' => 'Failed to process payment.']);
            }

        } else {
            // The request is not from the internal Magento application
            $this->logger->error('Unauthorized access attempt detected', ['referer' => $referer, 'origin' => $origin]);
            return $result->setData(['error' => true, 'message' => 'Unauthorized access: Invalid Referer or Origin']);
        }
    }

    protected function createOrGetCustomer($clienteData)
    {
        // Attempt to retrieve the customer by email
        $apiUrl = 'https://api.sandbox.claropagos.com/v1/cliente/email/' . $clienteData['email'];
        $this->curl->get($apiUrl);
        $response = json_decode($this->curl->getBody(), true);

        if ($response['status'] === 'success' && isset($response['data']['cliente']['id'])) {
            $this->logger->info('Customer found', ['email' => $clienteData['email']]);
            return $response['data']['cliente']['id'];
        }

        // If customer does not exist, create a new one
        $apiUrl = 'https://api.sandbox.claropagos.com/v1/cliente';
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
        // Check if the card exists for the customer
        $apiUrl = 'https://api.sandbox.claropagos.com/v1/cliente/' . $clienteId . '/tarjeta';
        $this->curl->get($apiUrl);
        $response = json_decode($this->curl->getBody(), true);

        if ($response['status'] === 'success' && count($response['data']['tarjetas']['data']) > 0) {
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
        // Update the CVV for the existing card
        $apiUrl = 'https://api.sandbox.claropagos.com/v1/tarjeta/' . $token;
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
        // Create a new card for the customer
        $tarjetaData['cliente_id'] = $clienteId;
        $apiUrl = 'https://api.sandbox.claropagos.com/v1/tarjeta';
        $this->curl->post($apiUrl, json_encode($tarjetaData));
        $response = json_decode($this->curl->getBody(), true);

        if ($response['status'] === 'success') {
            $this->logger->info('Card created successfully', ['clienteId' => $clienteId]);
            return $response['data']['tarjeta']['token'];
        }

        $this->logger->error('Failed to create card', ['clienteId' => $clienteId, 'response' => $response]);
        return null;
    }

    protected function processPayment($token, $pedidoData, $clienteId)
    {
        // Process the payment using the card token
        $paymentData = [
            'monto' => $pedidoData['total_monto'],
            'moneda' => $pedidoData['moneda'],
            'metodo_pago' => 'tarjeta',
            'tarjeta' => [
                'token' => $token,
            ],
            'pedido' => $pedidoData,
            'cliente' => [
                'id' => $clienteId,
            ],
        ];

        $apiUrl = 'https://api.sandbox.claropagos.com/v1/cargo';
        $this->curl->post($apiUrl, json_encode($paymentData));
        $response = json_decode($this->curl->getBody(), true);

        if ($response['status'] === 'success') {
            $this->logger->info('Payment processed successfully', ['clienteId' => $clienteId, 'pedidoId' => $pedidoData['id_externo']]);
            return true;
        }

        $this->logger->error('Failed to process payment', ['clienteId' => $clienteId, 'response' => $response]);
        return false;
    }
}
