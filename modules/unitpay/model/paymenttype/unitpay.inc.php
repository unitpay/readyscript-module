<?php
namespace Unitpay\Model\PaymentType;

use RS\Config\Loader;
use \RS\Orm\Type;
use \Shop\Model\Orm\Transaction;

/**
* Способ оплаты - PayAnyWay
*/
class Unitpay extends \Shop\Model\PaymentType\AbstractType
{
    /**
    * Возвращает название расчетного модуля (типа доставки)
    * 
    * @return string
    */
    function getTitle()
    {
        return t('Unitpay');
    }
    
    /**
    * Возвращает описание типа оплаты. Возможен HTML
    * 
    * @return string
    */
    function getDescription()
    {
        return t('Приём платежей через Unitpay');
    }
    
    /**
    * Возвращает идентификатор данного типа оплаты. (только англ. буквы)
    * 
    * @return string
    */
    function getShortName()
    {
        return 'unitpay';
    }
    
    /**
    * Отправка данных с помощью POST?
    * 
    */
    function isPostQuery()
    {
        return true;
    }
    
    /**
    * Возвращает ORM объект для генерации формы или null
    * 
    * @return \RS\Orm\FormObject | null
    */
    function getFormObject()
    {
        $properties = new \RS\Orm\PropertyIterator(array(
            'domain' => new Type\Varchar(array(
                'maxLength' => 255,
                'description' => t('DOMAIN'),

            )),
            'public_key' => new Type\Varchar(array(
                'maxLength' => 255,
                'description' => t('PUBLIC KEY'),

            )),
            'secret_key' => new Type\Varchar(array(
                'maxLength' => 255,
                'description' => t('SECRET KEY'),

            )),
            'language' => new Type\Varchar(array(
                'maxLength' => 5,
                'description' => t('Язык интерфейса'),
                'listFromArray' => array(array(
                    'ru' => t('Русский'),
                    'en' => t('Английский'),
                ))
            )),
			'__help__' => new Type\MixedType(array(
                'description' => t(''),
                'visible' => true,  
                'template' => '%unitpay%/form/payment/unitpay/help.tpl'
            ))
        ));

        return new \RS\Orm\FormObject($properties);
    }
    
    
    /**
    * Возвращает true, если данный тип поддерживает проведение платежа через интернет
    * 
    * @return bool
    */
    function canOnlinePay()
    {
        return true;
    }
    
    /**
    * Возвращает URL для перехода на сайт сервиса оплаты
    * 
    * @param Transaction $transaction - ORM объект транзакции
    * @return string
    */
    function getPayUrl(\Shop\Model\Orm\Transaction $transaction)
    {

        $order      = $transaction->getOrder();     // Данные о заказе

        $inv_id     = $transaction->id;
        $out_summ   = $this->priceFormat($transaction->cost);
        $in_cur     = $this->getPaymentCurrency();
		$desc = t("Оплата заказа №") . $inv_id;
		
        if ($in_cur == 'RUR') {
            $in_cur = 'RUB';
        }
		
		//Данные плательщика
        $user_id  = $transaction->user_id;
        $user     = new \Users\Model\Orm\User();
        
        $user->load($user_id);
        $cps_email = $user['e_mail'];
		$cps_phone = $user['phone'];
		
        $params = array();
		
        $params['sum']                      = $out_summ;
        $params['currency']                 = $in_cur;
        $params['account']                  = $inv_id;
		$params['signature'] 				= $this->generateSignature($inv_id, $in_cur, $desc, $out_summ, $this->getOption('secret_key'));
        $params['desc']                     = $desc;
        $params['locale'] 					= $this->getOption('language', 'ru');
		$params['customerPhone']            = $this->phoneFormat($cps_phone);
		$params['customerEmail']            = $cps_email;
		$params['cashItems']                = $this->getParamsForFZ54Check($transaction);
		
        $this->addPostParams($params); // Добавляем параметры для POST запроса

        $domain = $this->getOption('domain');
        $public_key = $this->getOption('public_key');
		
        $url = 'https://' . $domain . '/pay/' . $public_key;

        return $url;
    }

	/**
    * Возвращает дополнительные параметры для печати чека по ФЗ-54
    * 
    * @param \Shop\Model\Orm\Transaction $transaction
    * @return array|false
    */
    protected function getParamsForFZ54Check($transaction)
    {
        $in_cur     = $this->getPaymentCurrency();
		
        if ($in_cur == 'RUR') {
            $in_cur = 'RUB';
        }
		
		$currency = \Catalog\Model\CurrencyApi::getByUid($in_cur);
        $currencyRatio = $currency->ratio;
		
        $items = array();
		
        if ($transaction->order_id) {
            //Оплата заказа
            $order = $transaction->getOrder();
            $cart = $order->getCart();
			
            if ($cart) {
                $address = $order->getAddress();
                $tax_api = new \Shop\Model\TaxApi();
                $products = $cart->getProductItems();
				
                foreach ($products as $product) {
                    $taxes = $tax_api->getProductTaxes($product['product'], $this->transaction->getUser(), $address);
					
                    $items[] = array(
                        'name' => $this->itemName($product),
                        'count' => $product['cartitem']['amount'],
						//'currency' => $product['cartitem']->getEntity()->getCurrencyCode(),
						'currency' => $in_cur,
						'nds' => $this->getTaxRates($this->getNdsCode($taxes, $address)),
                        'price' => $this->priceFormat(($product['cartitem']['price'] - $product['cartitem']['discount']) / round($product['cartitem']['amount'])),
						'type' => 'commodity',
                    );
                }
				
                $delivery = $cart->getCartItemsByType(\Shop\Model\Cart::TYPE_DELIVERY);
				
                foreach ($delivery as $delivery_item) {
					$deliveryPrice = $delivery_item['price'] - $delivery_item['discount'];
					
					if(intval($deliveryPrice) > 0) {
						$taxes = $tax_api->getDeliveryTaxes($order->getDelivery(), $this->transaction->getUser(), $address);
						
						$items[] = array(
							'name' => mb_substr($delivery_item['title'], 0 , 50),
							'count' => 1,
							'currency' => $in_cur,
							'nds' => $this->getTaxRates($this->getNdsCode($taxes, $address)),
							'price' => $this->priceFormat($delivery_item['price'] - $delivery_item['discount']),
							'type' => 'service',
						);
					}
                }
            }
        } else {
            //Пополнение лицевого счета
			$shop_config = Loader::byModule($this);

            $items[] = array(
                'name' => $transaction->reason,
                'count' => 1,
                'price' => $this->priceFormat($transaction->cost),
				'currency' => $in_cur,
				'type' => 'service',
            );
        }
		
        return $this->cashItems($items);
    }
	
	function itemName($product)
    {
        if ($product['product']['barcode']){
            $result = $product['product']['barcode'];
        }
		
        $result = $result.' '.$product['product']['title'];
		
        if (iconv_strlen($result)>64){
            $result = iconv_substr($result, 0 , 61 , 'UTF-8' );
            $result = $result.'...';
        }
		
		return $result;
    }

    /**
    * Получает трех символьный код базовой валюты в которой ведётся оплата
    * 
    */
    private function getPaymentCurrency()
    {
       /**
       * @var \Catalog\Model\Orm\Currency
       */
       $currency = \RS\Orm\Request::make()
                        ->from(new \Catalog\Model\Orm\Currency())
                        ->where(array(
                           'public'  => 1,
                           'is_base'  => 1,
                        ))
                        ->object(); 
       return $currency ? $currency->title : false;
    }
    

    /**
    * Обработка запросов от moneta.ru
    * 
    * @param \Shop\Model\Orm\Transaction $transaction - объект транзакции
    * @param \RS\Http\Request $request - объект запросов
    * @return string
    */
    // function onResult(\Shop\Model\Orm\Transaction $transaction, \RS\Http\Request $request)
    function onResult(\Shop\Model\Orm\Transaction $transaction, \RS\Http\Request $request)
    {

        $data = $_GET;

        $noUpdate = false;
        $method = '';
        $params = array();
        if ((isset($data['params'])) && (isset($data['method'])) && (isset($data['params']['signature']))) {
            $params = $data['params'];
            $method = $data['method'];
            $signature = $params['signature'];
            $secret_key = $this->getOption('secret_key');
            if (empty($signature)) {
                $status_sign = false;
            } else {
                $status_sign = $this->verifySignature($params, $method, $secret_key);
            }
        } else {
            $status_sign = false;
        }
//    $status_sign = true;
        if ($status_sign) {
            switch ($method) {
                case 'check':
                    $result = $this->check($params, $transaction);
                    $noUpdate = true;
                    break;
                case 'pay':
                    $result = $this->pay($params, $transaction);
                    break;
                case 'error':
                    $result = $this->error($params, $transaction);
                    break;
                default:
                    $result = array('error' =>
                        array('message' => 'неверный метод')
                    );
                    break;
            }
        } else {
            $result = array('error' =>
                array('message' => 'неверная сигнатура')
            );
        }

        return $this->hardReturnJson($result, $noUpdate);
    }
    
    /**
    * Вызывается при открытии страницы неуспешного проведения платежа 
    * Используется только для Online-платежей
    * 
    * @param \Shop\Model\Orm\Transaction $transaction
    * @param \RS\Http\Request $request
    * @return void 
    */
    function onFail(\Shop\Model\Orm\Transaction $transaction, \RS\Http\Request $request)
    {
        $transaction['status'] = $transaction::STATUS_FAIL;
        $transaction->update();
    }

    /**
     * @param \RS\Http\Request $request
     * @return bool
     */
    function getTransactionIdFromRequest(\RS\Http\Request $request)
    {
        $data = $_GET;
        $params = $data['params'];
        $account = $params['account'];
        return $account;
    }

    function check( $params, \Shop\Model\Orm\Transaction $transaction )
    {
        $order = $transaction->getOrder();
        $ISOCode = $order->getMyCurrency()->title;
		$sum   = $this->priceFormat($transaction->cost);
		
        if ((float) $this->priceFormat($sum) != (float) $this->priceFormat($params['orderSum'])) {
            $result = array('error' =>
                array('message' => 'не совпадает сумма заказа')
            );
        }elseif ($ISOCode != $params['orderCurrency']) {
            $result = array('error' =>
                array('message' => 'не совпадает валюта заказа')
            );
        }
        else{
            $result = array('result' =>
                array('message' => 'Запрос успешно обработан')
            );
        }
        return $result;
    }

    function pay( $params, \Shop\Model\Orm\Transaction $transaction )
    {
        $order = $transaction->getOrder();
        $ISOCode = $order->getMyCurrency()->title;
		$sum   = $this->priceFormat($transaction->cost);
		
        if ((float) $this->priceFormat($sum) != (float) $this->priceFormat($params['orderSum'])) {
            $result = array('error' =>
                array('message' => 'не совпадает сумма заказа')
            );
        }elseif ($ISOCode != $params['orderCurrency']) {
            $result = array('error' =>
                array('message' => 'не совпадает валюта заказа')
            );
        }
        else{

            $result = array('result' =>
                array('message' => 'Запрос успешно обработан')
            );
        }

        return $result;
    }
    function error( $params, $transaction )
    {
        $result = array('result' =>
            array('message' => 'Запрос успешно обработан')
        );
        return $result;
    }
	
    /**
     * @param $items
     * @return string
     */
    public function cashItems($items) {
        return base64_encode(json_encode($items));
    }

    /**
     * @param $rate
     * @return string
     */
    function getTaxRates($code){
        switch ($code){
            case 'nds_110':
                $vat = 'vat10';
                break;
            case 'nds_120':
                $vat = 'vat20';
                break;
            case 'nds_0':
                $vat = 'vat0';
                break;
            default:
                $vat = 'none';
        }

        return $vat;
    }

    /**
     * @param $value
     * @return string
     */
    public function priceFormat($value) {
        return number_format($value, 2, '.', '');
    }

    /**
     * @param $value
     * @return string
     */
    public function phoneFormat($value) {
        return  preg_replace('/\D/', '', $value);
    }
	
	
    function getSignature($method, array $params, $secretKey)
    {
        ksort($params);
        unset($params['sign']);
        unset($params['signature']);
        array_push($params, $secretKey);
        array_unshift($params, $method);
        return hash('sha256', join('{up}', $params));
    }
	
	/**
     * @param $order_id
     * @param $currency
     * @param $desc
     * @param $sum
     * @return string
     */
    public function generateSignature($order_id, $currency, $desc, $sum, $secretKey) {
        return hash('sha256', join('{up}', array(
            $order_id,
            $currency,
            $desc,
            $sum ,
            $secretKey
        )));
    }
	
    function verifySignature($params, $method, $secret)
    {
        return $params['signature'] == $this->getSignature($method, $params, $secret);
    }

    function hardReturnJson( $arr, $noUpdate = false )
    {
        header('Content-Type: application/json');
        $result = json_encode($arr);

        if (isset($arr['error']) || $noUpdate){
            $exception = new \Shop\Model\PaymentType\ResultException($result);
            $exception->setUpdateTransaction(false);
            $exception->setResponse($result); // Строка направится как ответ серверу
            throw $exception;
        }else{
            return $result;
        }

    }

}
