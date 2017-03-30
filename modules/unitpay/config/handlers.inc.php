<?php
namespace Unitpay\Config;
use \RS\Orm\Type as OrmType;

/**
* Класс предназначен для объявления событий, которые будет прослушивать данный модуль и обработчиков этих событий.
*/
class Handlers extends \RS\Event\HandlerAbstract
{
    function init()
    {
        $this
            ->bind('payment.gettypes');
    }
    
    /**
    * Добавляем новый вид оплаты - PayAnyWay
    * 
    * @param array $list - массив уже существующих типов оплаты
    * @return array
    */
    public static function paymentGetTypes($list)
    {
        $list[] = new \Unitpay\Model\PaymentType\Unitpay(); // PayAnyWay
        return $list;
    }
}