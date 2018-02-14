<?php

/**
 *  Bitcoin Thai module for VirtueMart
 *  Controller for the payment response
 *
 *  @version 1.0.0
 *  @author David B
 *  @copyright Copyright (c) 2012 ICEPAY B.V.
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
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

// Load the controller framework
jimport('joomla.application.component.controller');

require_once(dirname(__FILE__) . '/pluginresponse.php');

/**
 * Controller for the payment response view
 *
 * @package VirtueMart
 * @subpackage paymentResponse
 * @author Valérie Isaksen
 *
 */
class VirtueMartControllerbitcointhairesponse extends VirtueMartControllerPluginresponse {

    /**
     * Construct the cart
     *
     * @access public
     */
    public function __construct() {
        parent::__construct();
    }
    function result() {
        if (!class_exists('vmPSPlugin'))
            require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php'); JPluginHelper::importPlugin('vmpayment');
		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger('plgVmOnPaymentResponseReceived', array('html' => &$html));
		
    }

}

//pure php no Tag
