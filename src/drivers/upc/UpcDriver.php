<?php namespace professionalweb\payment\drivers\upc;

use Illuminate\Http\Response;
use professionalweb\payment\contracts\Receipt;
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

    public function __construct(?array $config = [])
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
     * @param string     $paymentType
     * @param string     $successReturnUrl
     * @param string     $failReturnUrl
     * @param string     $description
     * @param array      $extraParams
     * @param Receipt    $receipt
     *
     * @return string
     * @throws \Exception
     */
    public function getPaymentLink($orderId,
                                   $paymentId,
                                   float $amount,
                                   string $currency = self::CURRENCY_UAH_ISO,
                                   string $paymentType = self::PAYMENT_TYPE_CARD,
                                   string $successReturnUrl = '',
                                   string $failReturnUrl = '',
                                   string $description = '',
                                   array $extraParams = [],
                                   Receipt $receipt = null): string
    {
        throw new \Exception('Driver needs form');
    }

    /**
     * Generate payment form
     *
     * @param int     $orderId
     * @param int     $paymentId
     * @param float   $amount
     * @param string  $currency
     * @param string  $paymentType
     * @param string  $successReturnUrl
     * @param string  $failReturnUrl
     * @param string  $description
     * @param array   $extraParams
     * @param Receipt $receipt
     *
     * @return IForm
     */
    public function getPaymentForm($orderId,
                                   $paymentId,
                                   float $amount,
                                   string $currency = self::CURRENCY_RUR,
                                   string $paymentType = self::PAYMENT_TYPE_CARD,
                                   string $successReturnUrl = '',
                                   string $failReturnUrl = '',
                                   string $description = '',
                                   array $extraParams = [],
                                   Receipt $receipt = null): IForm
    {
        $form = new Form($this->getTransport()->getPaymentUrl([]));
        $form->setField($this->getTransport()->prepareParams(array_merge([
            'Version'      => 1,
            'OrderID'      => $orderId,
            'Currency'     => $currency,
            'TotalAmount'  => $amount * 100,
            'SD'           => $paymentId,
            'PurchaseTime' => date('ymdHis'),
        ], $extraParams)));

        return $form;
    }

    /**
     * Validate request
     *
     * @param array $data
     *
     * @return bool
     */
    public function validate(array $data): bool
    {
        return $this->getTransport()->validate($data);
    }

    /**
     * Get configuration
     *
     * @return array
     */
    public function getConfig(): array
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
    public function setConfig(?array $config): self
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
    public function setResponse(array $data): PayService
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
    public function getResponseParam(string $name, $default = '')
    {
        return $this->response[$name] ?? $default;
    }

    /**
     * Get order ID
     *
     * @return string
     */
    public function getOrderId(): string
    {
        return $this->getResponseParam('OrderID');
    }

    /**
     * Get operation status
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->isSuccess() ? 'success' : 'failed';
    }

    /**
     * Is payment succeed
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->getErrorCode() === 0;
    }

    /**
     * Get transaction ID
     *
     * @return string
     */
    public function getTransactionId(): string
    {
        return $this->getResponseParam('Rrn');
    }

    /**
     * Get transaction amount
     *
     * @return float
     */
    public function getAmount(): float
    {
        return (float)$this->getResponseParam('TotalAmount');
    }

    /**
     * Get error code
     *
     * @return string
     */
    public function getErrorCode(): string
    {
        return (int)$this->getResponseParam('TranCode', '');
    }

    /**
     * Get payment provider
     *
     * @return string
     */
    public function getProvider(): string
    {
        return self::PAYMENT_UPC;
    }

    /**
     * Get PAn
     *
     * @return string
     */
    public function getPan(): string
    {
        return $this->getResponseParam('ProxyPan');
    }

    /**
     * Get payment datetime
     *
     * @return string
     */
    public function getDateTime(): string
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
    public function setTransport(PayProtocol $protocol): PayService
    {
        $this->transport = $protocol;

        return $this;
    }

    /**
     * Get transport
     *
     * @return PayProtocol
     */
    public function getTransport(): PayProtocol
    {
        return $this->transport;
    }

    /**
     * Prepare response on notification request
     *
     * @param int $errorCode
     *
     * @return Response
     */
    public function getNotificationResponse(int $errorCode = null): Response
    {
        return response($this->getTransport()->getNotificationResponse($this->response, $errorCode ?? $this->getErrorCode()));
    }

    /**
     * Prepare response on check request
     *
     * @param int $errorCode
     *
     * @return Response
     */
    public function getCheckResponse(int $errorCode = null): Response
    {
        return response($this->getTransport()->getNotificationResponse($this->response, $errorCode ?? $this->getErrorCode()));
    }

    /**
     * Get last error code
     *
     * @return int
     */
    public function getLastError(): int
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
    public function getParam(string $name)
    {
        return $this->getResponseParam($name);
    }

    /**
     * Get name of payment service
     *
     * @return string
     */
    public function getName(): string
    {
        return self::PAYMENT_UPC;
    }

    /**
     * Get payment id
     *
     * @return string
     */
    public function getPaymentId(): string
    {
        return $this->getResponseParam('SD');
    }

    /**
     * Payment system need form
     * You can not get url for redirect
     *
     * @return bool
     */
    public function needForm(): bool
    {
        return true;
    }

    /**
     * Get pay service options
     *
     * @return array
     */
    public function getOptions(): array
    {
        // TODO: Implement getOptions() method.
    }
}