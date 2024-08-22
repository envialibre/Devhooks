<?php

namespace Devhooks\CustomPayment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ConfigProvider implements ConfigProviderInterface
{
    const XML_PATH_ACTIVE = 'payment/custompayment/active';
    const XML_PATH_API_URL = 'payment/custompayment/api_url';
    const XML_PATH_API_TOKEN = 'payment/custompayment/token';

    protected $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function getConfig()
    {
        $config = [
            'payment' => [
                'custompayment' => [
                    'api_token' => $this->scopeConfig->getValue(self::XML_PATH_API_TOKEN, \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                    'api_url' => $this->scopeConfig->getValue(self::XML_PATH_API_URL, \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                ],
            ],
        ];

        return $config;
    }
}