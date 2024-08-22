<?php

namespace Devhooks\CustomPayment\Model;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data as PaymentData;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Payment\Model\Method\Logger;

class CustomPayment extends AbstractMethod
{
    const PAYMENT_METHOD_CUSTOM_INVOICE_CODE = 'custompayment';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CUSTOM_INVOICE_CODE;

    protected $_scopeConfig;
    protected $charge;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        PaymentData $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        RequestInterface $request,
        EncryptorInterface $encryptor,
        Charge $charge,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->_scopeConfig = $scopeConfig;
        $this->charge = $charge;
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        $billingAddress = $order->getBillingAddress();
        
        // Build request payload
        $payload = [
            "monto" => $amount,
            "pais" => "MEX",
            "moneda" => "MXN",
            "descripcion" => "Order #{$order->getIncrementId()}",
            "capturar" => true,
            "incluir_riesgo" => true,
            "metodo_pago" => "tarjeta",
            "tarjeta" => [
                "token" => $payment->getAdditionalInformation('token')
            ],
            "cliente" => [
                "id" => $billingAddress->getCustomerId(),
                "nombre" => $billingAddress->getFirstname(),
                "apellido_paterno" => $billingAddress->getLastname(),
                "email" => $billingAddress->getEmail(),
                "telefono" => $billingAddress->getTelephone(),
                "direccion" => $billingAddress->getStreetLine(1)
            ]
        ];

        $result = $this->charge->createCharge($payload);

        if ($result['status'] !== 'success') {
            throw new LocalizedException(__('Payment authorization failed.'));
        }

        return $this;
    }
}
