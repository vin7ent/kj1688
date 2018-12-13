<?php
/**
 * Created by PhpStorm.
 * User: dai
 * Date: 2018/12/11
 * Time: 14:13
 */

namespace Vin7ent\Kj1688;


class AliKJ
{
    /*
     * 跨境场景获取商品详情
     * 查询商品详情之前要把产品先push到铺货计划中
     * alibaba.cross.productInfo
     * */
    public function productInfo($product)
    {
        $uri = 'param2/1/com.alibaba.product/alibaba.cross.productInfo';
        $parameters = [
            'productId' => $product,
        ];

        $result = HttpClient::sendRequest($uri, $parameters);

        return $result;
    }
}