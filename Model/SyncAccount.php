<?php

namespace Zenki\Zenkipay\Model;

class SyncAccount
{
    public const API_URL = 'https://api.zenki.fi';

    public function sync($sync_code, $urlStore)
    {
        $url = self::API_URL . '/public/v1/pay/plugins/synchronize';
        $method = 'POST';
        $data = ['pluginUrl' => $urlStore, 'pluginVersion' => 'v1.0.0', 'synchronizationCode' => $sync_code];
        return $this->customRequest($url, $method, $data);
    }

    public function customRequest($url, $method, $data = [])
    {
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        $agent = 'Zenkipay-PHP/1.0';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true, // return the transfer as a string of the return value
            CURLOPT_TIMEOUT => 30, // The maximum number of seconds to allow cURL functions to execute.
            CURLOPT_USERAGENT => $agent,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($data), // Data that will send
        ]);

        $result = curl_exec($ch);
        if ($result === false) {
            throw new \Exception('Error with the ' . $method . ' request ' . $url);
        }

        curl_close($ch);
        return json_decode($result, true);
    }
}
