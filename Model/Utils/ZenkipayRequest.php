<?php
namespace Zenki\Zenkipay\Model\Utils;

class ZenkipayRequest {
    
    protected $logger;
    /**
     * 
     * @param Context $context
     * @param ZenkiPayment $payment
     * @param  $logger_interface
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }
    
    public function make($path, $country, $is_sandbox, $method = 'GET', $data = null, $auth = null) {
        $country = strtolower($country);
        $url =  'https://dev-gateway.zenki.fi/';
        $sandbox_url = 'https://dev-gateway.zenki.fi/';
    
        $absUrl = $is_sandbox ? $sandbox_url : $url;
        $absUrl .= $path;
    
        $ch = curl_init();
        if ($method != 'GET' && $data) {
            $payload = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        }

        if ($auth != null) {
            curl_setopt($ch, CURLOPT_USERPWD, $auth['sk'].':'.'');
        }
        curl_setopt($ch, CURLOPT_URL, $absUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        $result = curl_exec($ch);
        $response = null;
        if ($result === false) {
            $this->logger->error("Curl error", array("curl_errno" => curl_errno($ch), "curl_error" => curl_error($ch)));
        } else {
            $info = curl_getinfo($ch);
            $response = json_decode($result);
            $response->http_code = $info['http_code'];
            $this->logger->debug("request", array("HTTP code " => $info['http_code'], "on request to" => $info['url']));
        }
    
        curl_close($ch);
        $this->logger->debug('#request response', [json_encode($response)]);
        return $response;
    }
}