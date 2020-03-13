<h3>Настройка аккаунта Unitpay</h3>

<p>Укажите этот url как обработчик платежей в личном кабинете UnitPay:</p><br>
<a target="_blank" href="{$router->getUrl('shop-front-onlinepay', [Act=>result, PaymentType=>$payment_type->getShortName()], true)}">
    {$router->getUrl('shop-front-onlinepay', [Act=>result, PaymentType=>$payment_type->getShortName()], true)}
</a>
