<?php
namespace Unitpay\Config;
use \RS\Orm\Type;

/**
* Конфигурационный файл модуля
*/
class File extends \RS\Orm\ConfigObject
{ 
    /**
    * Возвращает значения свойств по-умолчанию
    * 
    * @return array
    */
    public static function getDefaultValues()
    {
        return array(
            'name' => t('Unitpay'),
            'description' => t('Приём платежей через Unitpay'),
            'version' => '1.0.0.0',
            'author' => 'Unitpay',
        );
    }     
    
}