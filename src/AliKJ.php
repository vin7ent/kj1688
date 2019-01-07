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

    public function productInfoAll($url, array $address,
                                   array $invoice = [
                                       'invoiceType'   => '0',
                                       'fullName'      => '张三',
                                       'mobile'        => '15251667788',
                                       'phone'         => '0517-88990077',
                                       'postCode'      => '邮编',
                                       'cityText'      => '杭州市',
                                       'provinceText'  => '浙江省',
                                       'areaText'      => '滨江区',
                                       'townText'      => '长河镇',
                                       'address'       => '网商路699号',
                                       'companyName'   => '测试公司',
                                       'taxpayerIdentifier' => '123455',
                                       'bankAndAccount'=> '网商银行',
                                       'localInvoiceId'=> '121231']
    )
    {
        $url = parse_url($url);
        if(!isset($url['path'])) {
            return [
                'success' => false,
                'code'    => -1001,
                'message' => '链接地址无效'
            ];
        }
        preg_match('/offer\/(\d+)\.html/', $url['path'], $match);
        $productId = $match[1] ?? '';
        if($productId == '') {
            return [
                'success' => false,
                'code'    => -1001,
                'message' => '链接地址无效'
            ];
        }
        else {
            $sync_retires = 0;
            $result = $this->sysncProductListPushed([$productId]);
            while (true) {
                if (isset($result['result'])) {
                    break;
                }
                else {
                    $sync_retires ++;
                    if ($sync_retires > 3)
                        return [
                            'success' => false,
                            'code' => 404,
                            'message' => '推送到铺货链接失效'
                        ];
                    else {
                        $result = $this->sysncProductListPushed([$productId]);
                    }
                    sleep(1);
                }
            }

            if($result['result']['success']) {
                $product = $this->productInfo($productId);
                if ($product['success']) {
                    $product = $product['productInfo'];
                    if(in_array($product['status'],[
                        'member expired',
                        'auto expired',
                        'expired',
                        'member deleted',
                        'deleted',
                        'TBD'
                    ]))
                    {
                        return [
                            'success' => false,
                            'code'    => -1002,
                            'message' => '产品下架'
                        ];
                    }
                    if(isset($product['skuInfos'])) {
                        $skuInfos = $product['skuInfos'];
                        $skus = [];
                        $specId = '';
                        foreach ($skuInfos as $skuInfo) {
                            $temp = [];
                            $temp_attributes = $skuInfo['attributes'];
                            $temp['attributes'] = '';
                            foreach ($temp_attributes as $temp_attribute) {
                                $temp['attributes'] .= ' [' . $temp_attribute['attributeValue'] . '] ';
                            }
                            $temp['amountOnSale'] = $skuInfo['amountOnSale'];
                            if (isset($skuInfo['price'])) {
                                $temp['price'] = $skuInfo['price'];
                            } elseif (isset($skuInfo['consignPrice'])) {
                                $temp['price'] = $skuInfo['consignPrice'];
                            } else $temp['price'] = 0.0;
                            $temp['specId'] = $skuInfo['specId'];
                            $temp['skuId'] = $skuInfo['skuId'];
                            $temp['enable'] = false;
                            if ($skuInfo['amountOnSale'] >= $product['saleInfo']['minOrderQuantity']) {
                                $specId = $skuInfo['specId'];
                                $temp['enable'] = true;
                            }
                            $skus[] = $temp;
                        }
                        if ($specId == '') {
                            return [
                                'success' => false,
                                'code' => -1002,
                                'message' => '所有产品都库存不足'
                            ];
                        }
                        $product['skuInfos'] = $skus;
                        $cargo = [
                            'offerId' => $productId,
                            'specId' => $specId,
                            'quantity' => $product['saleInfo']['minOrderQuantity']

                        ];
                    }
                    else {
                        $specId = '';
                        $product['skuInfos'] = [
                            [
                                'attributes' => '',
                                'specId'     => '',
                                'amountOnSale' => $product['saleInfo']['amountOnSale'],
                                'price'     => $product['saleInfo']['priceRanges'][0]['price'],
                                'enable'    => $product['saleInfo']['amountOnSale'] >= $product['saleInfo']['minOrderQuantity']
                            ]
                        ];
                        $cargo = [
                            'offerId' => $productId,
                            'specId' => $specId,
                            'quantity' => $product['saleInfo']['minOrderQuantity']

                        ];
                    }
                    if(isset($product['shippingInfo']['sendGoodsAddressText'])) {
                        $area = $product['shippingInfo']['sendGoodsAddressText'];
                        $area = explode(' ', $area);
                        $product['province'] = $area[0];
                        $product['city']    = $area[1];
                    }
                    else {
                        $product['province'] = '未知';
                        $product['city']    = '未知';
                    }
                    $preview = $this->previewOrder('general', $address, [$cargo], $invoice);
                    $product['credit'] = false;
                    $product['freight'] = 0;
                    $product['lastUpdateTime'] = HttpClient::aliTime($product['lastUpdateTime']);
                    if($preview['success']) {
                        $preview = $preview['orderPreviewResuslt'][0];
                        if(in_array('creditBuy', $preview['tradeModeNameList'])) {
                            $product['credit'] = true;  //是否支持诚e赊
                        }
                        $product['freight'] = number_format($preview['sumCarriage'] / 100.0, 2, '.', ''); //运费强制两位小数

                    }
                    return [
                        'success' => true,
                        'data' => $product
                    ];
                }
                else {
                    return [
                        'success' => false,
                        'code'    => -1002,
                        'message' => $product['message']
                    ];
                }
            }
        }

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
     * 获取物流信息和详情信息
     * 把两个接口一起用
     * */
    public function getLogisticsAll($orderId, $website = '1688', $fields = 'GuaranteesTerms,NativeLogistics,RateDetail,OrderInvoice')
    {
        $result = $this->getLogistics($orderId, $website, $fields);
        if($result['success']) {
            $data = [];
            $result = $result['result'][0];
            $data['orderEntryIds'] = $result['orderEntryIds'];
            $data['logisticsId'] = $result['logisticsId'];
            $data['logisticsCompanyName'] = $result['logisticsCompanyName'];
            $data['logisticsCompanyId'] = $result['logisticsCompanyId'];
            $data['status'] = $result['status'];
            $data['sendGoods'] = $result['sendGoods'];

            $result = $this->getLogisticsDetail($orderId);
            if($result['success']) {
                if(isset($result['logisticsTrace'])) {
                    $result = $result['logisticsTrace'][0];
                    $data['details'] = $result['logisticsSteps'];
                }
                else {
                    $data['details'] = '未查询到物流信息。';
                }
            }

            return [
                'success' => true,
                'message' => '物流信息获取成功',
                'data'    => $data
            ];

        }
        else {
            if($result['errorCode'] == 'success') {
                return [
                    'success' => false,
                    'message' => '卖家还未录入物流信息'
                ];
            }
            else {
                return [
                    'success' => false,
                    'message' => '物流接口请求失败, 原因:' . $result['errorMessage']
                ];
            }
        }
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
                return [
                    'success' => false,
                    'message' => '缺少参数-addressParam-'.$allAddressParam.'-'.$key
                ];

        }

        $allCargoParams = [
            'offerId'   => '商品对应的offer id',
            'specId'    => '商品规格id',
            'quantity'  => '商品数量(计算金额用)'
        ];
        $data['cargoParamList'] = $cargoParamList;

        foreach ($allCargoParams as $key => $item) {
            if(is_array($data['cargoParamList'][0])) {
                foreach ($data['cargoParamList'] as $cParam) {
                    if (!isset($cParam[$key]))
                        return [
                            'success' => false,
                            'message' => '缺少参数-cargoParamList-' . $item . '-' . $key
                        ];
                }
            }
            else {
                if (!isset($data['cargoParamList'][$key]))
                    return [
                        'success' => false,
                        'message' => '缺少参数-cargoParamList-' . $item . '-' . $key
                    ];
            }
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
     * 创建订单
     * alibaba.trade.createCrossOrder
     * flow取值范围 general, saleproxy
     * addressParam 地址信息
     * cargoParamList 商品信息
     * 创建跨境订单
     * */

    public function previewOrder($flow, array $addressParam, array $cargoParamList, $invoiceParam, $message = '')
    {
        $data['flow'] = $flow;
        $data['addressParam'] = $addressParam;
        $data['cargoParamList'] = $cargoParamList;
        $data['invoiceParam'] = $invoiceParam;
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
                return [
                    'success' => false,
                    'message' => '缺少参数-addressParam-'.$allAddressParam.'-'.$key
                ];

        }

        $allCargoParams = [
            'offerId'   => '商品对应的offer id',
            'specId'    => '商品规格id',
            'quantity'  => '商品数量(计算金额用)'
        ];
        $data['cargoParamList'] = $cargoParamList;

        foreach ($allCargoParams as $key => $item) {
            if(is_array($data['cargoParamList'][0])) {
                foreach ($data['cargoParamList'] as $cParam) {
                    if (!isset($cParam[$key]))
                        return [
                            'success' => false,
                            'message' => '缺少参数-cargoParamList-' . $item . '-' . $key
                        ];
                }
            }
            else {
                if (!isset($data['cargoParamList'][$key]))
                    return [
                        'success' => false,
                        'message' => '缺少参数-cargoParamList-' . $item . '-' . $key
                    ];
            }
        }

        if(!empty($message))
            $data['message'] = $message;

        $allInvoiceParams = [
            'invoiceType'   => '发票类型',
            'fullName'      => '收货人姓名',
            'mobile'        => '手机',
            'phone'         => '电话',
            'postCode'      => '邮编',
            'cityText'      => '市文本',
            'provinceText'  => '省份文本',
            'areaText'      => '区文本',
            'townText'      => '镇文本',
            'address'       => '街道地址',
            'companyName'   => '发票抬头',
            'taxpayerIdentifier' => '纳税识别码',
            'bankAndAccount'=> '开户行及帐号',
            'localInvoiceId'=> '增值税本地发票号'
        ];

        foreach ($allInvoiceParams as $key => $allInvoiceParam) {
            if(!isset($data['invoiceParam'][$key]))
                return '缺少参数-addressParam-'.$allInvoiceParam.'-'.$key;
        }

        $data['addressParam'] = json_encode($data['addressParam']);
        $data['cargoParamList'] = json_encode($data['cargoParamList']);
        $data['invoiceParam'] = json_encode($data['invoiceParam']);

        return HttpClient::sendRequest($this->trade.'alibaba.createOrder.preview', $data);
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

    /*
     * alibaba.trade.get.buyerView
     * website： alibaba 1688
     * orderId: 订单号
     * 获取订单详情
     * */

    public function orderInfo($orderId, $webSite = '1688', $includeFields = 'NativeLogistics')
    {
        $result = HttpClient::sendRequest($this->trade.'alibaba.trade.get.buyerView', [
            'webSite' => $webSite,
            'orderId' => $orderId,
            'includeFields' => $includeFields,
        ]);
        return $result;
    }


    



}