<?php namespace professionalweb\payment\drivers\upc;

use professionalweb\payment\contracts\PayProtocol;

/**
 * Wrapper for UPC protocol
 * @package professionalweb\payment\drivers\upc
 */
class UpcProtocol implements PayProtocol
{
    /**
     * Test payment gate URL
     */
    const URL_PAYMENT_TEST = 'https://ecg.test.upc.ua/go/enter';

    /**
     * Payment gate URL
     */
    const URL_PAYMENT = 'https://secure.upc.ua/go/enter';

    /**
     * Current payment URL
     *
     * @var string
     */
    private $paymentUrl = self::URL_PAYMENT;

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

    public function __construct($merchantId = '', $terminalId = '', $pathToLocalKey = '',
                                $pathToPaymentGateKey = '', $isTest = false)
    {
        $this
            ->setMerchantId($merchantId)
            ->setTerminalId($terminalId)
            ->setPathToLocalKey($pathToLocalKey)
            ->setPathToPaymentGateKey($pathToPaymentGateKey)
            ->setIsTest($isTest);
    }

    /**
     * Send POST request to Yandex.Kassa
     *
     * @param string $url
     * @param array  $params
     *
     * @return string
     */
    protected function sendPostRequest($url, array $params)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_USERAGENT, 'upc.ua.SDK/PHP');
        curl_setopt($curl, CURLOPT_POST, 1);
        $query = http_build_query($params);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Expect:']);
        $body = curl_exec($curl);

        return $body;
    }

    /**
     * Parse string with headers
     *
     * @param string $headersStr
     *
     * @return array
     */
    protected function parseHeaders($headersStr)
    {
        $result = [];
        $arrRequests = explode('\r\n\r\n', $headersStr);
        for ($index = 0; $index < count($arrRequests) - 1; $index++) {
            foreach (explode('\r\n', $arrRequests[$index]) as $i => $line) {
                if ($i === 0)
                    $result[$index]['http_code'] = $line;
                else {
                    list ($key, $value) = explode(': ', $line);
                    $result[$index][mb_strtolower($key)] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Get payment URL
     *
     * @param mixed $params
     *
     * @return string
     */
    public function getPaymentUrl($params)
    {
        $params = array_merge([
            'MerchantID' => $this->getMerchantId(),
            'TerminalId' => $this->getTerminalId(),
        ], $params);
        $params['Signature'] = $this->getSignature($params);
        $response = $this->sendPostRequest($this->getPaymentGateUrl(), $params);
        $headers = $this->parseHeaders($response);

        return isset($headers[0]) && isset($headers[0]['location']) ? $headers[0]['location'] : null;
    }

    /**
     * Create signature
     *
     * @param array $params
     *
     * @return string
     */
    protected function getSignature(array $params)
    {
        $purchaseTime = isset($params['PurchaseTime']) ? $params['PurchaseTime'] : '';
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
    public function validate($params)
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
    private function checkSign($request)
    {
        $signature = $request['Signature'];
        $signature = base64_decode($signature);
        $fp = fopen($this->getPathToPaymentGateKey(), 'r');
        $cert = fread($fp, 8192);
        fclose($fp);

        $data = '';
        $dataMask = ['MerchantID', 'TerminalID', 'PurchaseTime',
            'OrderID,Delay', 'XID', 'Currency', 'TotalAmount', 'SD', 'TranCode', 'ApprovalCode'];
        $collectDataVals = [];
        foreach ($dataMask as $dataItem) {
            if (isset($request[$dataItem]) && !empty($request[$dataItem])) {
                $collectDataVals[] = $request[$dataItem];
            }
        }
        $data .= implode(';', $collectDataVals);

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
    public function getPaymentId()
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
    public function getNotificationResponse($requestData, $errorCode)
    {
        $responseString = '';
        $responseString .= 'MerchantID=' . $this->getMerchantId() . '\n';
        $responseString .= 'TerminalID=' . $this->getTerminalId() . '\n';
        $responseString .= 'OrderID=' . $requestData['OrderId'] . '\n';
        $responseString .= 'Currency=' . $requestData['Currency'] . '\n';
        $responseString .= 'TotalAmount=' . $requestData['TotalAmount'] . '\n';
        $responseString .= 'XID=' . $requestData['XID'] . '\n';
        $responseString .= 'PurchaseTime=' . $requestData['PurchaseTime'] . '\n';
        $responseString .= 'Response.action=' . ($errorCode === 0 ? 'approve' : 'reverse') . '\n';
        $responseString .= 'Response.reason=\n';
        $responseString .= 'Response.forwardUrl=\n';

        return response($responseString);
    }

    /**
     * Prepare response on check request
     *
     * @param array $requestData
     * @param int   $errorCode
     *
     * @return string
     */
    public function getCheckResponse($requestData, $errorCode)
    {
        return $this->getNotificationResponse($requestData, $errorCode);
    }

    /**
     * Set current payment gate
     *
     * @param bool $flag
     *
     * @return $this
     */
    public function setIsTest($flag = true)
    {
        $this->paymentUrl = $flag ? self::URL_PAYMENT_TEST : self::URL_PAYMENT;

        return $this;
    }

    /**
     * Get payment gate URL
     *
     * @return string
     */
    public function getPaymentGateUrl()
    {
        return $this->paymentUrl;
    }

    /**
     * @return string
     */
    public function getPathToLocalKey()
    {
        return $this->pathToLocalKey;
    }

    /**
     * @param string $pathToLocalKey
     *
     * @return $this
     */
    public function setPathToLocalKey($pathToLocalKey)
    {
        $this->pathToLocalKey = $pathToLocalKey;

        return $this;
    }

    /**
     * @return string
     */
    public function getPathToPaymentGateKey()
    {
        return $this->pathToPaymentGateKey;
    }

    /**
     * @param string $pathToPaymentGateKey
     *
     * @return $this
     */
    public function setPathToPaymentGateKey($pathToPaymentGateKey)
    {
        $this->pathToPaymentGateKey = $pathToPaymentGateKey;

        return $this;
    }

    /**
     * @return string
     */
    public function getMerchantId()
    {
        return $this->merchantId;
    }

    /**
     * @param string $merchantId
     *
     * @return $this
     */
    public function setMerchantId($merchantId)
    {
        $this->merchantId = $merchantId;

        return $this;
    }

    /**
     * @return string
     */
    public function getTerminalId()
    {
        return $this->terminalId;
    }

    /**
     * @param string $terminalId
     *
     * @return $this
     */
    public function setTerminalId($terminalId)
    {
        $this->terminalId = $terminalId;

        return $this;
    }
}