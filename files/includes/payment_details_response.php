<?php

// this class is not used. it's just for type hinting.

class PaymentDetailsResponse
{
    /** @var float */
    public $amount;
    /** @var float */
    public $exchange_rate;
    /** @var string */
    public $address;
    /** @var string */
    public $payment_url;
    /** @var string */
    public $qr_code_base64;

}
