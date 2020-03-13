<?php
namespace Unitpay\Model\PaymentType;
use \RS\Orm\Type;
use \Shop\Model\Orm\Transaction;

/**
* Способ оплаты - PayAnyWay
*/
class Unitpay extends \Shop\Model\PaymentType\AbstractType
{
    const
        PAYMENT_URL = "https://www.payanyway.ru/assistant.htm",                        // Assistant link
        DEMO_URL    = "https://demo.moneta.ru/assistant.htm";

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
        /*$properties = new \RS\Orm\PropertyIterator(array(
            'account_id' => new Type\Varchar(array(
                'maxLength' => 20,
                'description' => t('Account ID - номер расширенного счёта магазина'),

            )),
            'account_code' => new Type\Varchar(array(
                'maxLength' => 255,
                'description' => t('Account code - код проверки целостности данных'),

            )),
            'is_demo' => new Type\Varchar(array(
                'maxLength' => 5,
                'description' => t('Демо режим?'),
                'listFromArray' => array(array(
                    '0' => t('Выключено'),
                    '1' => t('Да, демо режим'),
                ))
            )),
            'is_test' => new Type\Varchar(array(
                'maxLength' => 5,
                'description' => t('Тестовый режим?'),
                'listFromArray' => array(array(
                    '0' => t('Выключено'),
                    '1' => t('Да, тестовый режим'),
                ))
            )),
            'language' => new Type\Varchar(array(
                'maxLength' => 5,
                'description' => t('Язык интерфейса'),
                'listFromArray' => array(array(
                    0    => t('Определяется PayAnyWay'),
                    'ru' => t('Русский'),
                    'ua' => t('Украинский'),
                    'en' => t('Английский'),
                ))
            )),
            '__help__' => new Type\Mixed(array(
                'description' => t(''),
                'visible' => true,
                'template' => '%payanyway%/form/payment/payanyway/help.tpl'
            )),
        ));*/
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
            '__help__' => new Type\Mixed(array(
                'description' => t(''),
                'visible' => true,
                'template' => '%unitpay%/form/payment/unitpay/help.tpl'
            )),
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
        $out_summ   = number_format($transaction->cost, 2, '.', '');
        $in_cur     = $this->getPaymentCurrency();
        if ($in_cur == 'RUR') {
            $in_cur = 'RUB';
        }

        $params = array();

        $params['sum']                      = $out_summ;
        $params['currency']                 = $in_cur;
        $params['account']                  = $inv_id;
        $params['desc']                     = t("Оплата заказа №").$order['order_num'];
        $params['locale'] = $this->getOption('language', 'ru');

        $this->addPostParams($params); // Добавляем параметры для POST запроса

        $domain = $this->getOption('domain');
        $public_key = $this->getOption('public_key');
        $url = 'https://' . $domain . '/pay/' . $public_key;

        return $url;    // url пост запроса
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
        $sum = $order->getTotalPrice(false, true);
        $ISOCode = $order->getMyCurrency()->title;

        if ((float)$sum != (float)$params['orderSum']) {
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
        $sum = $order->getTotalPrice(false, true);
        $ISOCode = $order->getMyCurrency()->title;

        if ((float)$sum != (float)$params['orderSum']) {
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
    function getSignature($method, array $params, $secretKey)
    {
        ksort($params);
        unset($params['sign']);
        unset($params['signature']);
        array_push($params, $secretKey);
        array_unshift($params, $method);
        return hash('sha256', join('{up}', $params));
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
