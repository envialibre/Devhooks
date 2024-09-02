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

            $params = $this->restRequest->getBodyParams(); // Get request parameters
            $apiToken = 'jdhfjdsf';
            $this->curl->addHeader("Authorization", "Bearer " . $apiToken);
            $this->curl->addHeader("Content-Type", "application/json");

            // Step 1: Create or Get Customer by Email
            $clienteId = $this->createOrGetCustomer($params['cliente']);
            if (!$clienteId) {
                return $result->setData(['success' => false, 'message' => 'Failed to create or retrieve cliente.']);
            }

            // Step 2: Check if Card Exists
            $existingCard = $this->checkIfCardExists($clienteId, $params['tarjeta']['pan']);
            if ($existingCard) {
                // Card exists, update CVV and proceed with payment
                $token = $this->updateCardCvv($existingCard['token'], $params['tarjeta']['cvv2']);
            } else {
                // Card does not exist, create a new card
                $token = $this->createCard($clienteId, $params['tarjeta']);
            }

            if (!$token) {
                return $result->setData(['success' => false, 'message' => 'Failed to process card information.']);
            }

            // Step 3: Process the Payment
            $paymentSuccess = $this->processPayment($token, $params['pedido'], $clienteId);
            if ($paymentSuccess) {
                return $result->setData(['success' => true, 'message' => 'Payment processed successfully.']);
            } else {
                return $result->setData(['success' => false, 'message' => 'Failed to process payment.']);
            }

        } else {
            // The request is not from the internal Magento application
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
            return $response['data']['cliente']['id'];
        }

        // If customer does not exist, create a new one
        $apiUrl = 'https://api.sandbox.claropagos.com/v1/cliente';
        $this->curl->post($apiUrl, json_encode($clienteData));
        $response = json_decode($this->curl->getBody(), true);

        if ($response['status'] === 'success' && isset($response['data']['cliente']['id'])) {
            return $response['data']['cliente']['id'];
        }

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
                    return $card;
                }
            }
        }

        return null;
    }

    protected function updateCardCvv($token, $cvv)
    {
        // Update the CVV for the existing card
        $apiUrl = 'https://api.sandbox.claropagos.com/v1/tarjeta/' . $token;
        $this->curl->put($apiUrl, json_encode(['cvv2' => $cvv]));
        $response = json_decode($this->curl->getBody(), true);

        if ($response['status'] === 'success') {
            return $response['data']['tarjeta']['token'];
        }

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
            return $response['data']['tarjeta']['token'];
        }

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

        return $response['status'] === 'success';
    }
}
