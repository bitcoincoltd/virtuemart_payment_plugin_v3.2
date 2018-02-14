<?php

class PaymentDetailsRequest
{
    public $callback;
    public $amount;
    public $currency;
    public $currency_to;
    public $order_label;

    /**
     * PaymentDetailsRequest constructor.
     * @param string $callback
     * @param float $amount
     * @param string $currency
     * @param string $currency_to
     * @param string $order_label
     */
    public function __construct($callback, $amount, $currency, $currency_to, $order_label) // if you are changing parameters - don't forget about hash()! it must include all of them!
    {
        $this->callback    = $callback;
        $this->amount      = $amount;
        $this->currency    = $currency;
        $this->currency_to    = $currency_to;
        $this->order_label = $order_label;
    }

    /**
     * @return string
     */
    public function hash()
    {
        return md5(
            $this->callback
            . $this->amount
            . $this->currency
            . $this->currency_to
            . $this->order_label
        );
    }
}
?>
