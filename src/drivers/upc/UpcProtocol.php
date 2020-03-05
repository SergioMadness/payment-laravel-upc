<?php namespace professionalweb\payment\drivers\upc;

use professionalweb\payment\contracts\PayProtocol;

/**
 * Wrapper for UPC protocol
 * @package professionalweb\payment\drivers\upc
 */
class UpcProtocol implements PayProtocol
{
    /**
     * Current payment URL
     *
     * @var string
     */
    private $paymentUrl;

    /**
     * Path to PEM file with client keys
     *
     * @var string
     */
    private $pathToLocalKey;

    /**
     * Path to file with payment gate key
     *
     * @var string
     */
    private $pathToPaymentGateKey;

    /**
     * @var string
     */
    private $merchantId;

    /**
     * @var string
     */
    private $terminalId;

    public function __construct(string $url = '', string $merchantId = '', string $terminalId = '', string $pathToLocalKey = '',
                                string $pathToPaymentGateKey = '')
    {
        $this
            ->setPaymentGateUrl($url)
            ->setMerchantId($merchantId)
            ->setTerminalId($terminalId)
            ->setPathToLocalKey($pathToLocalKey)
            ->setPathToPaymentGateKey($pathToPaymentGateKey);
    }

    /**
     * Get payment URL
     *
     * @param mixed $params
     *
     * @return string
     */
    public function getPaymentUrl(array $params): string
    {
        return $this->getPaymentGateUrl();
    }

    /**
     * Prepare parameters
     *
     * @param array $params
     *
     * @return array
     * @throws \Exception
     */
    public function prepareParams(array $params): array
    {
        $params = array_merge([
            'MerchantID' => $this->getMerchantId(),
            'TerminalId' => $this->getTerminalId(),
        ], $params);
        $params['Signature'] = $this->getSignature($params);

        return $params;
    }

    /**
     * Create signature
     *
     * @param array $params
     *
     * @return string
     * @throws \Exception
     */
    protected function getSignature(array $params): string
    {
        if (empty($this->getPathToLocalKey())) {
            throw new \Exception('UPC need key for signature');
        }
        $purchaseTime = $params['PurchaseTime'] ?? '';
        $data = $this->getMerchantId() . ';' . $this->getTerminalId() . ';' . $purchaseTime . ';' . $params['OrderID'] . ';' . $params['Currency'] . ';' . $params['TotalAmount'] . ';' . $params['SD'] . ';';
        $fp = fopen($this->getPathToLocalKey(), 'r');
        $privateKey = fread($fp, 8192);
        fclose($fp);
        $keyId = openssl_pkey_get_private($privateKey);
        openssl_sign($data, $signature, $keyId);
        openssl_free_key($keyId);

        return base64_encode($signature);
    }

    /**
     * Validate params
     *
     * @param mixed $params
     *
     * @return bool
     */
    public function validate(array $params): bool
    {
        return $this->checkSign($params);
    }

    /**
     * Checking the MD5 sign.
     *
     * @param  array $request payment parameters
     *
     * @return int true if MD5 hash is correct
     */
    private function checkSign(array $request): int
    {
        $signature = $request['Signature'];
        $signature = base64_decode($signature);
        $fp = fopen($this->getPathToPaymentGateKey(), 'r');
        $cert = fread($fp, 8192);
        fclose($fp);

        $data = '';
        $dataMask = ['MerchantID', 'TerminalID', 'PurchaseTime',
            'OrderID', 'XID', 'Currency', 'TotalAmount', 'SD', 'TranCode', 'ApprovalCode'];
        $collectDataVals = [];
        foreach ($dataMask as $dataItem) {
            if (isset($request[$dataItem]) && !empty($request[$dataItem])) {
                $collectDataVals[] = $request[$dataItem];
            }
        }
        $data .= implode(';', $collectDataVals) . ';';

        $pubKeyId = openssl_get_publickey($cert);
        $checkResult = openssl_verify($data, $signature, $pubKeyId);
        openssl_free_key($pubKeyId);

        return $checkResult;
    }

    /**
     * Get payment ID
     *
     * @return mixed
     */
    public function getPaymentId(): string
    {
        // TODO: Implement getPaymentId() method.
    }

    /**
     * Prepare response on notification request
     *
     * @param mixed $requestData
     * @param int   $errorCode
     *
     * @return string
     */
    public function getNotificationResponse($requestData, $errorCode): string
    {
        $responseString = '';
        $responseString .= 'MerchantID=' . $this->getMerchantId() . "\n";
        $responseString .= 'TerminalID=' . $this->getTerminalId() . "\n";
        $responseString .= 'OrderID=' . $requestData['OrderID'] . "\n";
        $responseString .= 'Currency=' . $requestData['Currency'] . "\n";
        $responseString .= 'TotalAmount=' . $requestData['TotalAmount'] . "\n";
        $responseString .= 'XID=' . $requestData['XID'] . "\n";
        $responseString .= 'PurchaseTime=' . $requestData['PurchaseTime'] . "\n";
        $responseString .= 'Response.action=' . ($errorCode === 0 ? 'approve' : 'reverse') . "\n";
        $responseString .= 'Response.reason=' . "\n";
        $responseString .= 'Response.forwardUrl=' . "\n";

        return $responseString;
    }

    /**
     * Prepare response on check request
     *
     * @param array $requestData
     * @param int   $errorCode
     *
     * @return string
     */
    public function getCheckResponse($requestData, $errorCode): string
    {
        return $this->getNotificationResponse($requestData, $errorCode);
    }

    /**
     * Set payment url
     *
     * @param string $url
     *
     * @return $this
     */
    public function setPaymentGateUrl(string $url): self
    {
        $this->paymentUrl = $url;

        return $this;
    }

    /**
     * Get payment gate URL
     *
     * @return string
     */
    public function getPaymentGateUrl(): string
    {
        return $this->paymentUrl;
    }

    /**
     * @return string
     */
    public function getPathToLocalKey(): string
    {
        return $this->pathToLocalKey;
    }

    /**
     * @param string $pathToLocalKey
     *
     * @return $this
     */
    public function setPathToLocalKey(string $pathToLocalKey): self
    {
        $this->pathToLocalKey = $pathToLocalKey;

        return $this;
    }

    /**
     * @return string
     */
    public function getPathToPaymentGateKey(): string
    {
        return $this->pathToPaymentGateKey;
    }

    /**
     * @param string $pathToPaymentGateKey
     *
     * @return $this
     */
    public function setPathToPaymentGateKey(string $pathToPaymentGateKey): self
    {
        $this->pathToPaymentGateKey = $pathToPaymentGateKey;

        return $this;
    }

    /**
     * @return string
     */
    public function getMerchantId(): string
    {
        return $this->merchantId;
    }

    /**
     * @param string $merchantId
     *
     * @return $this
     */
    public function setMerchantId(string $merchantId): self
    {
        $this->merchantId = $merchantId;

        return $this;
    }

    /**
     * @return string
     */
    public function getTerminalId(): string
    {
        return $this->terminalId;
    }

    /**
     * @param string $terminalId
     *
     * @return $this
     */
    public function setTerminalId(string $terminalId): self
    {
        $this->terminalId = $terminalId;

        return $this;
    }
}