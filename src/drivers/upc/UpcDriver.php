<?php namespace professionalweb\payment\drivers\upc;

use professionalweb\payment\Form;
use Illuminate\Contracts\Support\Arrayable;
use professionalweb\payment\contracts\PayService;
use professionalweb\payment\contracts\PayProtocol;
use professionalweb\payment\contracts\Form as IForm;
use professionalweb\payment\interfaces\upc\UpcService;

/**
 * Payment service. Pay, Check, etc
 * @package professionalweb\payment\drivers\upc
 */
class UpcDriver implements PayService, UpcService
{
    /**
     * UPC protocol object
     *
     * @var PayProtocol
     */
    private $transport;

    /**
     * Module config
     *
     * @var array
     */
    private $config;

    /**
     * Notification info
     *
     * @var array
     */
    protected $response;

    public function __construct($config)
    {
        $this->setConfig($config);
    }

    /**
     * Pay
     *
     * @param int        $orderId
     * @param int        $paymentId
     * @param float      $amount
     * @param int|string $currency
     * @param string     $successReturnUrl
     * @param string     $failReturnUrl
     * @param string     $description
     * @param array      $extraParams
     * @param Arrayable  $receipt
     *
     * @return string
     * @throws \Exception
     */
    public function getPaymentLink($orderId,
                                   $paymentId,
                                   $amount,
                                   $currency = self::CURRENCY_UAH_ISO,
                                   $paymentType = self::PAYMENT_TYPE_CARD,
                                   $successReturnUrl = '',
                                   $failReturnUrl = '',
                                   $description = '',
                                   $extraParams = [],
                                   $receipt = null)
    {
        throw new \Exception('Driver needs form');
    }

    /**
     * Generate payment form
     *
     * @param int       $orderId
     * @param int       $paymentId
     * @param float     $amount
     * @param string    $currency
     * @param string    $paymentType
     * @param string    $successReturnUrl
     * @param string    $failReturnUrl
     * @param string    $description
     * @param array     $extraParams
     * @param Arrayable $receipt
     *
     * @return IForm
     */
    public function getPaymentForm($orderId,
                                   $paymentId,
                                   $amount,
                                   $currency = self::CURRENCY_RUR,
                                   $paymentType = self::PAYMENT_TYPE_CARD,
                                   $successReturnUrl = '',
                                   $failReturnUrl = '',
                                   $description = '',
                                   $extraParams = [],
                                   $receipt = null)
    {
        $form = new Form($this->getTransport()->getPaymentUrl([]));
        $form->setField(array_merge([
            'OrderID'      => $orderId,
            'Currency'     => $currency,
            'TotalAmount'  => $amount * 100,
            'SD'           => $paymentId,
            'PurchaseTime' => date('ymdHis'),
        ], $extraParams));

        return $form;
    }

    /**
     * Validate request
     *
     * @param array $data
     *
     * @return bool
     */
    public function validate($data)
    {
        return $this->getTransport()->validate($data);
    }

    /**
     * Get configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Set driver configuration
     *
     * @param array $config
     *
     * @return $this
     */
    public function setConfig($config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Parse notification
     *
     * @param array $data
     *
     * @return mixed
     */
    public function setResponse($data)
    {
        $this->response = $data;

        return $this;
    }

    /**
     * Get response param by name
     *
     * @param string $name
     * @param string $default
     *
     * @return mixed|string
     */
    public function getResponseParam($name, $default = '')
    {
        return isset($this->response[$name]) ? $this->response[$name] : $default;
    }

    /**
     * Get order ID
     *
     * @return string
     */
    public function getOrderId()
    {
        return $this->getResponseParam('OrderID');
    }

    /**
     * Get operation status
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->isSuccess() ? 'success' : 'failed';
    }

    /**
     * Is payment succeed
     *
     * @return bool
     */
    public function isSuccess()
    {
        return $this->getErrorCode() === 0;
    }

    /**
     * Get transaction ID
     *
     * @return string
     */
    public function getTransactionId()
    {
        return $this->getResponseParam('Rrn');
    }

    /**
     * Get transaction amount
     *
     * @return float
     */
    public function getAmount()
    {
        return $this->getResponseParam('TotalAmount');
    }

    /**
     * Get error code
     *
     * @return int
     */
    public function getErrorCode()
    {
        return (int)$this->getResponseParam('TranCode', 0);
    }

    /**
     * Get payment provider
     *
     * @return string
     */
    public function getProvider()
    {
        return 'tinkoff';
    }

    /**
     * Get PAn
     *
     * @return string
     */
    public function getPan()
    {
        return $this->getResponseParam('ProxyPan');
    }

    /**
     * Get payment datetime
     *
     * @return string
     */
    public function getDateTime()
    {
        $purchaseTime = $this->getResponseParam('PurchaseTime');
        $result = '';
        if (!empty($purchaseTime) && ($dateTime = \DateTime::createFromFormat('ymdHis', $purchaseTime)) !== false) {
            $result = $dateTime->format('Y-m-d H:i:s');
        }

        return $result;
    }

    /**
     * Set transport/protocol wrapper
     *
     * @param PayProtocol $protocol
     *
     * @return $this
     */
    public function setTransport(PayProtocol $protocol)
    {
        $this->transport = $protocol;

        return $this;
    }

    /**
     * Get transport
     *
     * @return PayProtocol
     */
    public function getTransport()
    {
        return $this->transport;
    }

    /**
     * Prepare response on notification request
     *
     * @param int $errorCode
     *
     * @return string
     */
    public function getNotificationResponse($errorCode = null)
    {
        return $this->getTransport()->getNotificationResponse($this->response, $errorCode);
    }

    /**
     * Prepare response on check request
     *
     * @param int $errorCode
     *
     * @return string
     */
    public function getCheckResponse($errorCode = null)
    {
        return $this->getTransport()->getNotificationResponse($this->response, $errorCode);
    }

    /**
     * Get last error code
     *
     * @return int
     */
    public function getLastError()
    {
        return 0;
    }

    /**
     * Get param by name
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getParam($name)
    {
        return $this->getResponseParam($name);
    }

    /**
     * Get name of payment service
     *
     * @return string
     */
    public function getName()
    {
        return 'upc';
    }

    /**
     * Get payment id
     *
     * @return string
     */
    public function getPaymentId()
    {
        return $this->getResponseParam('SD');
    }

    /**
     * Payment system need form
     * You can not get url for redirect
     *
     * @return bool
     */
    public function needForm()
    {
        return true;
    }
}