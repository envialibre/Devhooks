<?php

namespace Vendor\Module\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\App\Config\ScopeConfigInterface;

class CustomPayment extends Template
{
    protected $scopeConfig;

    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    public function getApiUrl()
    {
        return $this->scopeConfig->getValue('payment/custompayment/api_url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getApiToken()
    {
        return $this->scopeConfig->getValue('payment/custompayment/api_token', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
}