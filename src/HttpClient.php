<?php
/**
 * Created by PhpStorm.
 * User: dai
 * Date: 2018/12/11
 * Time: 14:48
 */

namespace Vin7ent\Kj1688;

use Carbon\Carbon;
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
        if(!filter_var(config('alikj.host'), FILTER_VALIDATE_URL))
            return [
                'success' => false,
                'code' => -1001,
                'message' => 'alikj未正确配置',
            ];

        $client = new Client([
            'base_uri' => config('alikj.host'),
            'timeout'  => 5.0
        ]);

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept'       => 'application/json'
        ];
        $url .= '/'. config('alikj.key');
        $parameters['access_token'] = config('alikj.token');
        $parameters['_aop_signature'] = self::signature($url, $parameters, config('alikj.secret'));

        $retries = 3;
        while ($retries > 0) {
            $retries -= 1;
            try {
                $response = $client->request('POST', $url,
                    [
                        'headers' => $headers,
                        'form_params' => $parameters
                    ]
                );

                return json_decode($response->getBody()->getContents(), true);
            } catch (\Exception $e) {
                \Log::info('ali http request error code: '. $e->getCode(). ' , message: '. $e->getMessage());
                if($retries > 1)
                    sleep(1);
                else {
                    return [
                        'success' => false,
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                    ];
                }
            }
        }
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
            array_push( $paramsToSign, $paramToSign );
        }
        sort ( $paramsToSign );
        $implodeParams = implode ( $paramsToSign );
        $pathAndParams = $path . $implodeParams;
        $sign = hash_hmac ( "sha1", $pathAndParams, $secret, true );
        $signHexWithLowcase = bin2hex ( $sign );
        $signHexUppercase = strtoupper ( $signHexWithLowcase );
        return $signHexUppercase;
    }

    public static function aliTime($time)
    {
        $index = 0;
        $year = substr($time, $index, 4);
        $index += 4;
        $month = substr($time, $index, 2);
        $index += 2;
        $day = substr($time, $index, 2);
        $index += 2;
        $hour = substr($time, $index, 2);
        $index += 2;
        $minute = substr($time, $index, 2);
        $index += 2;
        $second = substr($time, $index, 2);

        $time = Carbon::create($year, $month, $day, $hour, $minute, $second)->toDateTimeString();

        return $time;
    }
}