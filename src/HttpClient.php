<?php
/**
 * Created by PhpStorm.
 * User: dai
 * Date: 2018/12/11
 * Time: 14:48
 */

namespace Vin7ent\Kj1688;

use GuzzleHttp\Client;

class HttpClient
{
    /**
     * 发送请求
     * @param $url
     * @param $parameters
     * @return string
     */
    public static function sendRequest($url, $parameters)
    {
        $client = new Client([
            'base_uri' => config('alikj.host'),
            'timeout'  => 2.0
        ]);

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept'       => 'application/json'
        ];
        $url .= '/'. config('alikj.key');
        $parameters['access_token'] = config('alikj.token');
        $parameters['_aop_signature'] = self::signature($url, $parameters, config('alikj.secret'));

        $response = $client->request('POST', $url,
            [
                'headers' => $headers,
                'form_params' => $parameters
            ]
        );

        return (array)json_decode($response->getBody()->getContents(), true);
    }

    /**
     * 计算signature
     * @param $path
     * @param array $parameters
     * @param$ $secret
     * @return string
     */
    public static function signature($path, array $parameters, $secret) {
        $paramsToSign = array ();
        foreach ( $parameters as $k => $v ) {
            $paramToSign = $k . $v;
            Array_push ( $paramsToSign, $paramToSign );
        }
        sort ( $paramsToSign );
        $implodeParams = implode ( $paramsToSign );
        $pathAndParams = $path . $implodeParams;
        $sign = hash_hmac ( "sha1", $pathAndParams, $secret, true );
        $signHexWithLowcase = bin2hex ( $sign );
        $signHexUppercase = strtoupper ( $signHexWithLowcase );
        return $signHexUppercase;
    }
}