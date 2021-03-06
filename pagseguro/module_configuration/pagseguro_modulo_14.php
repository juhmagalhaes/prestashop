<?php

/*
 * 2007-2013 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author PrestaShop SA <contact@prestashop.com>
 *  @copyright  2007-2013 PrestaShop SA
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

include_once(dirname(__FILE__).'/../../../config/config.inc.php');
include_once(dirname(__FILE__) . '/../PagSeguroLibrary/PagSeguroLibrary.php');


if (! defined('_PS_VERSION_')) {
    exit();
}

class PagSeguroModulo14 extends PaymentModule
{

    public $context;

    public function __construct()
    {
		//Se deixar o include sem essa verificação ele bloqueia a aba payment no admin
		//Há comparação de versão pq algumas versões do 1.5 adotam algumas funcionalidade da versão 1.4
		if(! $_REQUEST['tab'] && version_compare(_PS_VERSION_, '1.5', '<')) {
    		include_once(dirname(__FILE__).'/../../../init.php');
		}
		
        parent::__construct();
        $this->initContext();
    }

    /**
     * Perform instalation of PagSeguro module
     *
     * @return boolean
     */
    public function install()
    {
        include_once(dirname(__FILE__).'/../../../init.php');
		
        /* For 1.4.3 and less compatibility */
        $updateConfig = array(
            'PS_OS_CHEQUE' => 1,
            'PS_OS_PAYMENT' => 2,
            'PS_OS_PREPARATION' => 3,
            'PS_OS_SHIPPING' => 4,
            'PS_OS_DELIVERED' => 5,
            'PS_OS_CANCELED' => 6,
            'PS_OS_REFUND' => 7,
            'PS_OS_ERROR' => 8,
            'PS_OS_OUTOFSTOCK' => 9,
            'PS_OS_BANKWIRE' => 10,
            'PS_OS_PAYPAL' => 11,
            'PS_OS_WS_PAYMENT' => 12
        );
        foreach ($updateConfig as $u => $v) {
            if (! Configuration::get($u) || (int) Configuration::get($u) < 1) {
                if (defined('_' . $u . '_') && (int) constant('_' . $u . '_') > 0) {
                    Configuration::updateValue($u, constant('_' . $u . '_'));
                } else {
                    Configuration::updateValue($u, $v);
                }
            }
        }
        
        return true;
    }

    /**
     * Perform uninstalation of PagSeguro module
     *
     * @return boolean
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * Perform Payment hook
     *
     * @param array $params            
     * @return string
     */
    public function hookPayment($params)
    {
        global $smarty;
        
        $smarty->assign(
            array(
                'version_module' => _PS_VERSION_,
                'action_url' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/pagseguro/payment.php',
                'image' => __PS_BASE_URI__ . 'modules/pagseguro/assets/images/logops_86x49.png',
                'this_path' => __PS_BASE_URI__ . 'modules/pagseguro/',
                'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/pagseguro/')
        );
        
        return $this->display(__PS_BASE_URI__ . 'modules/pagseguro', '/views/templates/hook/payment.tpl');
    }

    /**
     * Perform Payment Return hook
     *
     * @param array $params            
     * @return string
     */
    public function hookPaymentReturn($params)
    {
        global $smarty;
        
        if (! Tools::isEmpty($params['objOrder']) && $params['objOrder']->module === 'pagseguro') {
            
            $smarty->assign(
                array(
                    'total_to_pay' => Tools::displayPrice(
                        $params['objOrder']->total_paid_real,
                        $this->context->currency->id,
                        false
                    ),
                    'status' => 'ok',
                    'id_order' => (int) $params['objOrder']->id
                )
            );
            
            if (isset($params['objOrder']->reference) && ! empty($params['objOrder']->reference)) {
                $smarty->assign('reference', $params['objOrder']->reference);
            }
        } else {
            $smarty->assign('status', 'failed');
        }
        return $this->display(__PS_BASE_URI__ . 'modules/pagseguro', '/views/templates/hook/payment_return.tpl');
    }
    
    // Retrocompatibility 1.4/1.5
    private function initContext()
    {
        if (class_exists('Context')) {
            $this->context = Context::getContext();
        } else {
            global $smarty, $cookie;
            $this->context = new StdClass();
            $this->context->smarty = $smarty;
            $this->context->cookie = $cookie;
        }
    }

    public function execPagError()
    {
        $this->display_column_left = false;
        
        return $this->display(__FILE__, 'views/templates/front/error.tpl');
    }

    public function execPayment()
    {
	 include_once(dirname(__FILE__) . '/../module_configuration/module_payment_pagseguro.php');
        $payment = new ModulePaymentPagSeguro();
        $payment->setVariablesPaymentExecutionView($this->context);

        return $this->display(__PS_BASE_URI__ . 'modules/pagseguro', '/views/templates/front/payment_execution.tpl');
    }

    private function notificationURL()
    {
        return _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/pagseguro/controllers/front/notification.php';
    }

    private function redirectURL()
    {
        return _PS_BASE_URL_ . __PS_BASE_URI__;
    }

    /**
     *
     *
     * Notification Url
     * 
     * @return type
     */
    public function getNotificationUrl()
    {
        $url_notification = Configuration::get('PAGSEGURO_NOTIFICATION_URL');
        return empty($url_notification) ? $this->notificationURL() : $url_notification;
    }

    /**
     * Gets a default redirection url
     * 
     * @return string
     */
    public function getDefaultRedirectionUrl()
    {
        $url_redirect = Configuration::get('PAGSEGURO_URL_REDIRECT');
        return empty($url_redirect) ? $this->redirectURL() : $url_redirect;
    }

    public function getJsBehavior()
    {
        return __PS_BASE_URI__ . 'modules/pagseguro/assets/js/behaviors-version-14.js';
    }

    public function getCssDisplay()
    {
        return __PS_BASE_URI__ . 'modules/pagseguro/assets/css/styles-version-14.css';
    }
}
