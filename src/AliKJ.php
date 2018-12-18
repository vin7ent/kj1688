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
    const VERSION = 'param2/1/';
    private $product;
    private $trade;
    private $logistics;

    function __construct()
    {
        $this->product = self::VERSION. 'com.alibaba.product/';
        $this->trade = self::VERSION. 'com.alibaba.trade/';
        $this->logistics = self::VERSION. 'com.alibaba.logistics/';
    }

    /*
     * 跨境场景获取商品详情
     * 查询商品详情之前要把产品先push到铺货计划中
     * alibaba.cross.productInfo
     * */
    public function productInfo($product)
    {
        $uri = $this->product.'alibaba.cross.productInfo';
        $parameters = [
            'productId' => $product,
        ];

        return HttpClient::sendRequest($uri, $parameters);
    }
    /*
     * 跨境场景下将商品加入铺货列表
     * alibaba.cross.syncProductListPushed
     * $products 1688产品ID列表，max: 20, products array
     * */
    public function sysncProductListPushed($products)
    {
        $uri = self::VERSION. 'com.alibaba.product.push/'. 'alibaba.cross.syncProductListPushed';
        $parameters = [
            'productIdList' => json_encode($products),
        ];

        return HttpClient::sendRequest($uri, $parameters);
    }

    /*
     * 订单列表查看(买家视角)
     * alibaba.trade.getBuyerOrderList
     * 该接口仅仅返回订单基本信息，不会返回订单的物流信息和发票信息；如果需要获取物流信息，请调用获取订单详情接口；如果需要获取发票信息，请调用获取发票信息的API
     * */

    public function getBuyerOrderList()
    {
        return HttpClient::sendRequest($this->trade.'alibaba.trade.getBuyerOrderList', []);
    }

    /*
     * 订单详情查看(买家视角)
     * alibaba.trade.getBuyerOrderList
     * 获取单个交易明细信息
     * */

    public function getBuyerOrder($orderId, $website = '1688', $include = 'GuaranteesTerms,NativeLogistics,RateDetail,OrderInvoice', $attributeKeys = [])
    {
        return HttpClient::sendRequest($this->trade.'alibaba.trade.get.buyerView', [
            'orderId' => $orderId,
            'webSite' => $website,
            'includeFields' => $include,
            'attributeKeys' => $attributeKeys
        ]);
    }

    /*
     * 获取交易订单的物流信息(买家视角)
     * alibaba.trade.getLogisticsInfos.buyerView
     * orderId 订单号
     * website 1688 alibaba
     * fields company,name,sender,receiver,sendgood
     * 获取买家的订单的物流详情，在采购或者分销场景中，作为买家也有获取物流详情的需求。该接口能查能根据订单号查看物流详情，包括发件人，收件人，所发货物明细等。
     * */

    public function getLogistics($orderId, $website = '1688', $fields = 'GuaranteesTerms,NativeLogistics,RateDetail,OrderInvoice')
    {
        return HttpClient::sendRequest($this->logistics.'alibaba.trade.getLogisticsInfos.buyerView', [
            'orderId' => $orderId,
            'webSite' => $website,
            'fields' => $fields,
        ]);
    }

    /*
     * 获取交易订单的物流跟踪信息(买家视角)
     * alibaba.trade.getLogisticsTraceInfo.buyerView
     * orderId 订单号
     * logisticsId 物流单号，非必填
     * fields company,name,sender,receiver,sendgood
     * 作为买家也有获取物流详情的需求。该接口能查能根据物流单号查看物流单跟踪信息
     * */

    public function getLogisticsDetail($orderId, $website = '1688', $logisticsId = '')
    {
        return HttpClient::sendRequest($this->logistics.'alibaba.trade.getLogisticsTraceInfo.buyerView', [
            'orderId' => $orderId,
            'webSite' => $website,
            'logisticsId' => $logisticsId,
        ]);
    }

    /*
     * 创建订单
     * alibaba.trade.createCrossOrder
     * flow取值范围 general, saleproxy
     * addressParam 地址信息
     * cargoParamList 商品信息
     * 创建跨境订单
     * */

    public function createOrder($flow, array $addressParam, array $cargoParamList, $message = '', $invoiceParam = [], $tradeType = 'fxassure', $shopPromotionId = '')
    {
        $data['flow'] = $flow;
        $data['addressParam'] = $addressParam;
        $data['cargoParamList'] = $cargoParamList;
        $allAddressParams = [
            'addressId'     => '收货地址id',
            'fullName'      => '收货人姓名',
            'mobile'        => '手机',
            'phone'         => '电话',
            'postCode'      => '邮编',
            'cityText'      => '市文本',
            'provinceText'  => '省份文本',
            'areaText'      => '区文本',
            'townText'      => '镇文本',
            'address'       => '街道地址',
            'districtCode'  => '地址编码'
        ];

        foreach ($allAddressParams as $key => $allAddressParam) {
            if(!isset($data['addressParam'][$key]))
                return '缺少参数-addressParam-'.$allAddressParam.'-'.$key;
        }

        $allCargoParams = [
            'offerId'   => '商品对应的offer id',
            'specId'    => '商品规格id',
            'quantity'  => '商品数量(计算金额用)'
        ];
        $data['cargoParamList'] = $cargoParamList;

        foreach ($allCargoParams as $key => $item) {
            if(!isset($data['cargoParamList'][$key]))
                return '缺少参数-cargoParamList-'.$item.'-'.$key;
        }

        if(!empty($message))
            $data['message'] = $message;

        if(!empty($invoiceParam)) {
            $data['invoiceParam'] = $invoiceParam;
            //TODO: 发票信息
        }
        $data['addressParam'] = json_encode($data['addressParam']);
        $data['cargoParamList'] = json_encode($data['cargoParamList']);

        $data['tradeType'] = $tradeType;

        return HttpClient::sendRequest($this->trade.'alibaba.trade.createCrossOrder', $data);
    }

    /*
     * alibaba.trade.receiveAddress.get
     * 买家获取保存的收货地址信息列表
     * */

    public function getAddress()
    {
        $addresses = HttpClient::sendRequest($this->trade.'alibaba.trade.receiveAddress.get', []);
        return $addresses;
    }

    /*
     * alibaba.alipay.url.get
     * 获取支付宝支付链接
     * */

    public function getAlipayUrl(array $orderIdList)
    {
        $urls = HttpClient::sendRequest($this->trade.'alibaba.alipay.url.get', [
            'orderIdList' => json_encode($orderIdList)
        ]);
        return $urls;
    }

    /*
     * alibaba.trade.cancel
     * website： alibaba 1688
     * tradeID: 订单号
     * cancelReason: buyerCancel, sellerGoodsLack, other
     * remark: 备注，非必填
     * 取消订单
     * */

    public function cancelOrder($tradeID, $cancelReason = 'buyerCancel', $webSite = '1688', $remark = '')
    {
        $result = HttpClient::sendRequest($this->trade.'alibaba.trade.cancel', [
            'webSite' => $webSite,
            'tradeID' => $tradeID,
            'cancelReason' => $cancelReason,
            'remark'    => $remark
        ]);
        return $result;
    }


    



}