<?php

defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

/**
 *  Bitcoin.in.th module for VirtueMart
 *  Main payment class
 *
 *  @version 1.0.2
 *  @author David B
 *  @copyright Copyright (c) 2013 Bitcoin.in.th
 *  bitcoin.in.th
 *
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */
if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVMPaymentBitcointhai extends vmPSPlugin {

    // instance of class
    public static $_this = false;
    private $_bitcointhai;
    private $_version = "1.0.2";
	var $method;

    function __construct(& $subject, $config) {
	//if (self::$_this)
	 //   return self::$_this;
	parent::__construct($subject, $config);
	
	$this->_loggable = true;
	$this->tableFields = array_keys($this->getTableSQLFields());

        /* stored to database based on settings */
	$varsToPush = array(
        'bitcointhai_api_id'    => array('', 'char'),
	    'bitcointhai_api_key'    => array('', 'char'),
	    'status_pending'            => array('', 'char'),
	    'status_success'            => array('', 'char'),
	    'payment_logos'             => array('', 'char'),
	    'payment_currency'          => array(0, 'int'),
	    'countries'                 => array(0, 'char'),
	    'min_amount'                => array(0, 'int'),
	    'max_amount'                => array(0, 'int')
	);

	$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

    }

    private function _getVersion(){
        return intval(str_replace(".", "", vmVersion::$RELEASE));
    }

    protected function getVmPluginCreateTableSQL() {
		return $this->createTableSQL('Payment Bitcointhai Table');
    }

    private function bitcointhai(){
        return $this->_bitcointhai;
    }

    function getTableSQLFields() {

        $SQLfields = array(
			'id' => 'tinyint(1) unsigned NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id' => 'int(11) UNSIGNED DEFAULT NULL',
			'order_number' => 'int(11) DEFAULT NULL',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL',
			'payment_name' => 'char(255) NOT NULL DEFAULT \'\' ',
			'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
			'payment_currency' => 'char(3) ',
			'cost_per_transaction' => ' decimal(10,2) DEFAULT NULL ',
			'cost_percent_total' => ' decimal(10,2) DEFAULT NULL ',
			'tax_id' => 'smallint(11) DEFAULT NULL'
	);

	return $SQLfields;
    }

    private function _getLangISO(){
		$lang = &JFactory::getLanguage();
		$arr = explode("-",$lang->get('tag'));
		return strtoupper($arr[0]);
    }

    function plgVmConfirmedOrder($cart, $order) {

		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		
		$lang = JFactory::getLanguage();
		$filename = 'com_virtuemart';
		$lang->load($filename, JPATH_ADMINISTRATOR);
		
		
		$modelOrder = VmModel::getModel ('orders');
		
		$result = $this->_bitcointhai->checkorder($_SESSION['bitcoin_order_id'],$order['details']['BT']->virtuemart_order_id);
		if(!$result || $result->error != ''){
		  if(!$result){
			  $e = JText::_('MODULE_PAYMENT_BITCOINTHAI_TEXT_ERROR');
		  }else{
			  $e = $result->error;
			  if(isset($result->order_id)){
				  $_SESSION['bitcoin_order_id'] = $result->order_id;
			  }
		  }
			$modelOrder->remove (array('virtuemart_order_id' => $order['details']['BT']->virtuemart_order_id));
			// error while processing the payment
			$mainframe = JFactory::getApplication ();
			$mainframe->redirect (JRoute::_ ('index.php?option=com_virtuemart&view=cart'), $e);
			return;
		}
		
		$html = JText::_('MODULE_PAYMENT_BITCOINTHAI_SUCCESS');
		
		$response_fields = array('virtuemart_order_id' => $order['details']['BT']->virtuemart_order_id,
								 'order_number' => $_SESSION['bitcoin_order_id']);
		
		$this->storePSPluginInternalData ($response_fields, 'virtuemart_order_id', TRUE);
		
		$order['order_status'] = $method->status_pending;
		$order['customer_notified'] = 1;
		$order['comments'] = JText::_('MODULE_PAYMENT_BITCOINTHAI_PENDING_NOTE');
		$modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

		//We delete the old stuff
		$cart->emptyCart ();
		unset($_SESSION['bitcoin_order_id']);
		JRequest::setVar ('html', $html);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {
	
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		 $this->getPaymentCurrency($method);
		$paymentCurrencyId = $method->payment_currency;
    }


    /* Function to handle all responses */
    public function plgVmOnPaymentResponseReceived(  &$html) {
		$modelOrder = VmModel::getModel ('orders');
		
		include_once('includes/bitcointhai.php');
		
		$api = new bitcointhaiAPI;
		
		$data = $_POST;
		
		if($ipn = $api->verifyIPN($data)){
			$method = $this->getVmPluginMethod($data['reference_id']);
			$order = $modelOrder->getOrder($data['reference_id']);
			if(!empty($order)){
				$order['order_status'] = $method->status_success;
				$order['customer_notified'] = 1;
				$order['comments'] = 'Bitcoin IPN: '.$data['message'];
				$modelOrder->updateStatusForOneOrder ($data['reference_id'], $order, TRUE);
			}
			echo 'IPN Success';
		}else{
			header("HTTP/1.0 403 Forbidden");
			echo 'IPN Failed';
		}
		exit();
    }
    
    public function getVersions(){
        return sprintf(t("Version %s using PHP API 2 version %s"),$this->_version,$this->_bitcointhai->getVersion());
    }
    
    
    private function outputVersion($extended = false){
        $dump = array(
            "module" => $this->getVersions(),
            "notice" => "Checksum validation passed!"
        );
        if ($extended){
            $dump["additional"] = array(
                "joomla" => JVERSION,
                "virtuemart" => vmVersion::$RELEASE
            );
        } else {
            $dump["notice"] = "Checksum failed! Merchant ID and Secret code probably incorrect.";
        }
        var_dump($dump);
        exit();
    }

    function plgVmOnUserPaymentCancel() {
    }

    function plgVmOnPaymentNotification() {
    }

    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id) {

		if (!$this->selectedThisByMethodId($payment_method_id)) {
			return null; // Another method was selected, do nothing
		}

		return;
    }


    function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
		return 0;
    }
	
    protected function checkConditions($cart, $method, $cart_prices) {

		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
		
		$this->method = $method;
	
		$amount = $cart_prices['salesPrice'];
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
		($method->min_amount <= $amount AND ($method->max_amount == 0) ));

		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array($method->countries)) {
			$countries[0] = $method->countries;
			} else {
			$countries = $method->countries;
			}
		}
		// probably did not gave his BT:ST address
		if (!is_array($address)) {
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}
		
		include_once(dirname(__FILE__).'/includes/bitcointhai.php');
		
		$currency = CurrencyDisplay::getInstance();
		
		$this->_bitcointhai = new bitcointhaiAPI;
		if(!$this->_bitcointhai->init($method->bitcointhai_api_id, $method->bitcointhai_api_key)){
			return false;
		}

	
		if (!isset($address['virtuemart_country_id']))
			$address['virtuemart_country_id'] = 0;
		if (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
			if ($amount_cond) {
			return true;
			}
		}
	
		return false;
    }
	
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
		return $this->onStoreInstallPluginTable($jplugin_id);
    }
	
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
		return $this->OnSelectCheck($cart);
    }

    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
		return $this->displayListFE($cart, $selected, $htmlIn);
    }
	
	
	protected function renderPluginName ($plugin) {

		$return = '';
		$plugin_name = $this->_psType . '_name';
		$plugin_desc = $this->_psType . '_desc';
		$description = '';
		
		$logosFieldName = $this->_psType . '_logos';
		$logos = $plugin->$logosFieldName;
		if (!empty($logos)) {
			$return = $this->displayLogos ($logos) . ' ';
		}
		if (!empty($plugin->$plugin_desc)) {
			$description = '<span class="' . $this->_type . '_description">' . $plugin->$plugin_desc . '</span>';
		}
		
		$pluginName = $return . '<span class="' . $this->_type . '_name">' . $plugin->$plugin_name . '</span>' . $description;
		
		if (!class_exists( 'VmModel' )) require(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_virtuemart'.DS.'helpers'.DS.'vmmodel.php');
		if (!class_exists( 'VmConfig' )) require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart'.DS.'helpers'.DS.'config.php');
		if(!class_exists('VirtueMartCart')) require(JPATH_VM_SITE.DS.'helpers'.DS.'cart.php');
		
		if($_REQUEST['task'] != '' && $_POST['task'] != 'setpayment'){
			return $pluginName;
		}
		
		$total = $this->getOrderTotal();
		
		JFactory::getLanguage ()->load ('com_virtuemart');
		
		$this->_bitcointhai->order_id = $_SESSION['bitcoin_order_id'];
		$data = array('amount' => $total,
					  'currency' => $this->getCurrencyCode(),
					  'ipn' => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=bitcointhairesponse&task=result'));
		if(!$paybox = $this->_bitcointhai->paybox($data)){
			return JText::_ ('MODULE_PAYMENT_BITCOINTHAI_TITLE_ERROR');
		}elseif(!$paybox->success){
			return JText::_ ('MODULE_PAYMENT_BITCOINTHAI_TITLE_ERROR'.': '.$paybox->error);
		}
		$_SESSION['bitcoin_order_id'] = $this->_bitcointhai->order_id;
		$btc_url = 'bitcoin:'.$paybox->address.'?amount='.$paybox->btc_amount.'&label='.urlencode(STORE_NAME);
		
      	$pluginName .=  '<div><div style="float:left; margin:10px;"><a href="'.$btc_url.'"><img src="data:image/png;base64,'.$paybox->qr_data.'" width="200" alt="Send to '.$paybox->address.'" border="0"></a></div><p style="margin:10px 0px;">'.sprintf(JText::_ ('MODULE_PAYMENT_BITCOINTHAI_TEXT_PAYMSG'),$paybox->btc_amount,$paybox->address).'</p><p style="margin:10px 0px;">'.JText::_ ('MODULE_PAYMENT_BITCOINTHAI_TEXT_AFTERPAY').'</p>'.$this->_bitcointhai->countDown($paybox->expire,'td',JText::_ ('MODULE_PAYMENT_BITCOINTHAI_TEXT_COUNTDOWN'),JText::_ ('MODULE_PAYMENT_BITCOINTHAI_TEXT_COUNTDOWN_EXP')).'</div>';
		return $pluginName;
	}
	
	private function getCurrencyCode(){
		$currency = CurrencyDisplay::getInstance();
		return $currency->ensureUsingCurrencyCode($currency->getId());
	}
	
	private function getOrderTotal(){
		$cart = VirtueMartCart::getCart();
		$c = $cart->getCartPrices();
		$currency = CurrencyDisplay::getInstance();
		
		return round($c['salesPriceShipment'] + $c['salesPricePayment'] + $c['withTax'] - $c['salesPriceCoupon'],$currency->getNbrDecimals());
	}

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
	return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array()) {
		return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
	  $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }
	
    function plgVmonShowOrderPrintPayment($order_number, $method_id) {
		return $this->onShowOrderPrint($order_number, $method_id);
    }
	
    function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
		return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
		return $this->setOnTablePluginParams($name, $id, $table);
    }

}

// No closing tag
