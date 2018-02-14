<?php
defined('_JEXEC') or die('Restricted access');
define('BXCOINPAY_VIRTUEMART_EXTENSION_VERSION', '2.0.0');

if (!class_exists('vmPSPlugin'))
    require(VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php');


require_once('files/includes/coinpay_api_client.php');

class plgVmPaymentBxcoinpay extends vmPSPlugin
{
  public static $_this = false;
  private $api;
  private $api_id;
  private $currency_to;
  var $method;

  function __construct(&$subject, $config)
  {
    parent::__construct($subject, $config);
    $this->_loggable = true;
    $this->tableFields = array_keys($this->getTableSQLFields());
    $this->callback = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived');
    $this->_debug = true;
    $this->_tablepkey = 'id';
    $this->_tableId = 'id';
    $this->api_id = $this->getId($config['name']);
    $this->api = new CoinpayApiClient($this->api_id);
    $varsToPush = array(
      'bxcoinpay_api_id' => array('','varchar(64)'),
      'status_pending' => array('','char'),
      'status_success' => array('', 'char'),
      'currency_to' => array('','varchar(64)'),
      'currency_from' => array(0,'char')
    );

    $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

  }

  public function getVmPluginCreateTableSQL()
  {
    return $this->crateTableSQL("Payment BX CoinPay Table");
  }

  private function getId($name) {
    $db = JFactory::getDBO();
    $q = "SELECT payment_params FROM #__virtuemart_paymentmethods WHERE payment_element='".$name."'";
    $db->setQuery($q);
    if(!($payment_table = $db->loadObject())) {
      return '';
    }
    preg_match('/(?<=api_id=\")[0-9a-z]+/',$payment_table->payment_params, $api_id);
    return $api_id[0];
  }

  function getTableSQLFields()
  {
    $SQLfields = array(
      'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
      'virtuemart_order_id' => 'int(1) UNSIGNED',
      'order_number'                => 'char(64)',
      'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
      'payment_name'                => 'varchar(5000)',
      'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
      'payment_currency'            => 'char(3)',
      'logo'                        => 'varchar(5000)'
    );
    return $SQLfields;
  }
  function getCosts(VirtueMartCart $cart, $method, $cart_prices)
  {
    return 0;
  }

  /**
    * plgVmDisplayListFEPayment
    * This event is fired to display    the pluginmethods in the cart (edit shipment/payment) for exampel
    *
    * @param object  $cart Cart object
    * @param integer $selected ID of  the method selected
    *  @return boolean True on success, false on failures, null when this plugin was not selected.
    * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
    *
    * @author Valerie Isaksen
    * @author Max Milbers
    **/
  public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn)
  {
    $_SESSION['cart_price'] = $cart->getCartPrices()['billTotal'];
    return $this->displayListFE($cart, $selected, $htmlIn);
  }

  protected function checkConditions($cart, $method, $cart_prices)
  {

    $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
    $this->method = $method;
    $_SESSION['plugin_name'] = $method->virtuemart_paymentmethod_id;

    //preg_match('/(?<=api_id=\")[0-9a-z]+/',$this->method->payment_params, $api_id);
    preg_match('/(?<=currency_to=\")([a-z0-9A-Z,.\s]+)/',$this->method->payment_params, $currency_to);
    //if( !$api_id ) {
      //echo "<p style='color:red;font-size:20px;'>Error! Please add API ID from https://bx.in.th</p>";
    //}

    //$this->api = new CoinpayApiClient($api_id[0]);

    if( $currency_to ) {
      $this->currency_to = $currency_to[0];
    }else{
      $this->currency_to = '';
    }

    return true;
  }

  protected function renderPluginName($plugin)
  {
    $return = '';
    $plugin_name = $this->_psType . '_name';
    $plugin_desc = $this->_psType . '_desc';
    $description = '';

    if (!empty($plugin->$plugin_desc)) {
      $description = '<span class="$description' . $this->_type . '_description">' . $plugin->$plugin_desc . '</span>';
    }
    $pluginName = $return . '<span class="'.$this->_type.'_name">'.$plugin->$plugin_name.'</span>'.$description;

    $request = new PaymentDetailsRequest(
      $this->callback,
      $_SESSION['cart_price'],
      $this->getCurrency(),
      $this->currency_to,
      'virtuemart shop'
    );

    // Refresh payment details if has in session
    if($this->paymentDetailsMustBeRefreshed($request)) {
      $payment_details = $this->api->getPaymentDetails($request);
      $_SESSION['payment_details'] = $payment_details;
      $_SESSION['payment_details_hash'] = $request->hash();
    }else{
      $payment_details = $_SESSION['payment_details'];
    }

    if( !$payment_details ) {
      $this->getPaymentDetailsFailed();
    }

    // Loop addresses
    $addresses_arr = array();
    foreach( $payment_details as $key => $value ) {
      foreach( $value as $key => $item ) {
        array_push($addresses_arr, $item->address);
      }
    }

    $_SESSION['bx_payment_addresses'] = $addresses_arr;

    $fields = '';
    include_once(VMPATH_PLUGINS . DS . 'vmpayment/bxcoinpay/files/includes/payment_fields.php');
    $pluginName .= $fields;
    return $pluginName;
  }

  function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
  {
    return $this->onStoreInstallPluginTable($jplugin_id);
  }

  public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
  {
    //die('calculate price');
    return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
  }

  function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
  {
    if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id)))
    {
      return NULL; // Another method was selected, do nothig
    }

    if (!$this->selectedThisElement($method->payment_element))
    {
      return false;
    }

    $this->getPaymentCurrency($method);

    $paymentCurrencyId = $method->payment_currency;
  }

  // on cart update show selected payment
  function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
  {
      return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
  }

  public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
  {
    //die(' show order' );
      $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
  }

  function plgVmonShowOrderPrintPayment($order_number, $method_id)
  {
    //die(' pring payment' );
      return $this->onShowOrderPrint($order_number, $method_id);
  }

  function plgVmDeclarePluginParamsPayment($name, $id, &$data)
  {
    //die( 'params payemnt' );
      return $this->declarePluginParams('payment', $name, $id, $data);
  }

  function plgVmDeclarePluginParamsPaymentVM3( &$data) {

    // on install payment
      return $this->declarePluginParams('payment', $data);
  }

  function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
  {

    // on install payment
      return $this->setOnTablePluginParams($name, $id, $table);
  }

  function plgVmOnPaymentNotification()
  {
    //print( 'plgVmOnPaymentNotification' );
    //die();
  }

  public function plgVmOnPaymentResponseReceived(&$html)
  {

    $modelOrder = VmModel::getModel ('orders');
    $input = json_decode(file_get_contents('php://input'),true);

    $order_id = $input['order_id'];
    $method = $this->getVmPluginMethod($order_id);
    $order = $modelOrder->getOrder($order_id);

    if( $this->api->validIPN($input) ) {
      //var_dump( $order['details']['BT']->virtuemart_order_id);

      if( $input['confirmed_in_full'] ) {
        $str = "[BX CoinPay: IPN: ".$input['message'];
        $order['order_status'] = "U";
        $order['customer_notified'] = 0;
        $order['comments'] = '[BX CoinPay:  '.$str;
        $modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, TRUE);
      }

      echo "IPN Success";
      exit();
    }else{
      header("HTTP/1.0 403 Forbiden");
      echo "IPN Failed";
      exit();
    }

    return TRUE;
  }


  public function getGMTTimeStamp()
  {
    die( 'get gmt time' );

    $tz_minutes = date('Z') / 60;

    if ($tz_minutes >= 0)
      $tz_minutes = '+' . sprintf("%03d",$tz_minutes);

    $stamp = date('YdmHis000000') . $tz_minutes;

    return $stamp;
  }

  function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id) {
    //die('on show order BE payemnt');
  }

  function plgVmConfirmedOrder($cart, $order)
  {

    if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id)))
        return NULL;

    if (!$this->selectedThisElement($method->payment_element))
        return false;

    if (!class_exists('VirtueMartModelOrders'))
      require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');

    if (!class_exists('VirtueMartModelCurrency'))
      require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');

    VmConfig::loadJLang('com_virtuemart', true);
    VmConfig::loadJLang('com_virtuemart_orders', true);

    $orderID = $order['details']['BT']->virtuemart_order_id;
    $paymentMethodID = $order['details']['BT']->virtuemart_paymentmethod_id;

    $currency_code_3 = shopFunctions::getCurrencyByID($method->currency_id, 'currency_code_3');

    $paymentCurrency = CurrencyDisplay::getInstance($method->currency_id);
    $totalInCurrency = round($paymentCurrency->convertCurrencyTo($method->currency_id, $order['details']['BT']->order_total, false), 2);

    $description = array();
    foreach ($order['items'] as $item) {
      $description[] = $item->product_quantity . ' × ' . $item->order_item_name;
    }

    $modelOrder = VmModel::getModel('orders');
    $mainframe = JFactory::getApplication();

    $result = $this->api->checkPaymentReceived(
      $_SESSION['bx_payment_addresses']
    );

    if( !$result ) {
      $this->getPaymentDetailsFailed();
      $modelOrder->remove(array('virtuemart_order_id' => $order['details']['BT']->virtuemart_order_id));
      $mainframe = JFactory::getApplication ();
      $mainframe->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart'), "Payment erorr!");
      return;
    }

    if( isset($result->payment_received) && $result->payment_received === false ) {
      $html = "Did you already pay it? We still did not see your payment!
        It can take a few seconds for your payment to appear.
        If you alreayd paid - press button again.";
      $modelOrder->remove(array('virtuemart_order_id' => $order['details']['BT']->virtuemart_order_id));
      $mainframe->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart&task=cart', FALSE), vmText::_($html));
    }

    if( $result->is_enough === false ) {
      $html = "Payment amount is not enough. Got: ";
      foreach( $result->paid as $key => $value ) {
        $html .= " ".$value->amount." in ".$value->cryptocurrency."; ";
      }
      $mainframe->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart&task=cart', FALSE), vmText::_($html));
      return;
    }

    // Save order id
    $order_saved = $this->api->saveOrderId(
      $_SESSION['bx_payment_addresses'],
      $order['details']['BT']->virtuemart_order_id
    );


    if( $order_saved === false ) {
      $error = "Something went wrong! Order ID can't be saved: ".$order_saved->error;
      $mainframe->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart&task=cart', FALSE), vmText::_($error));
    }
    $paid = $result->paid_by;
    $str = " ".$paid->amount." in ".$paid->name." to ".$paid->address." proof link: ".$paid->proof_link." ";
    $order['order_status'] = "P";
    $order['customer_notified'] = 0;
    $order['comments'] = '[BX CoinPay: Awaiting confirmation. Paid: '.$str;
    $modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, TRUE);

      $cart->emptyCart();
    //vRequest::setVar('html', $html);
      //header('Location: ' . $cgOrder->payment_url);
      //exit;
  }

  private function getCurrency()
  {
    $currency = CurrencyDisplay::getInstance();
    return $currency->ensureUsingCurrencyCode($currency->getId());
  }

  private function paymentDetailsMustBeRefreshed($request)
  {
    return $_SESSION['payment_details_hash'] != $request->hash()
      OR !$_SESSION['payment_details'];
  }

  private function getPaymentDetailsFailed()
  {
    echo "<p>Sorry, BX Coinpay payment are currently unavailable</p>";
  }

  private function save_order_comment($order, $status, $comment) {
    $modelOrder = VmModel::getModel('orders');
    $order['order_status'] = $status;
    $order['customer_notified'] = 1;
    $order['comments'] = $comment;
    $modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, TRUE);
  }

  private function expected_amount($order_id, $order)
  {
    $str = "Expecting ";
    if( isset($_SESSION['payment_details']) ) {
      foreach( $_SESSION['payment_details']->addresses as $key => $value ) {
        if( $value->available ) {
          $str .= " ".$value->amount." in ".$key." to ".$value->address."; ";
        }
      }
    }
    $modelOrder = VmModel::getModel('orders');
    $order['order_status'] = "Pending";
    $order['customer_notified'] = 0;
    $order['comments'] = $str;
    $modelOrder->updateStatusForOneOrder($order_id, $order, TRUE);
  }

}

defined('_JEXEC') or die('Restricted access');

if (!class_exists( 'VmConfig' ))
  require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart'.DS.'helpers'.DS.'config.php');

if (!class_exists('ShopFunctions'))
  require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'shopfunctions.php');

defined('JPATH_BASE') or die();

