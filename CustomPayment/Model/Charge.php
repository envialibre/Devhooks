<?php

namespace Devhooks\CustomPayment\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Charge
{
    protected $curl;
    protected $scopeConfig;

    public function __construct(
        Curl $curl,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
    }

    public function createCharge($data)
    {
        $url = $this->scopeConfig->getValue('payment/claropago/api_url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $authToken = $this->scopeConfig->getValue('payment/claropago/token', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $authToken
        ];

        try {
            $this->curl->setHeaders($headers);
            $this->curl->post($url, json_encode($data));
            $response = $this->curl->getBody();
            
            return json_decode($response, true);
        } catch (\Exception $e) {
            throw new LocalizedException(__('Unable to process request: %1', $e->getMessage()));
        }
    }
}
