<?php
namespace eDemy\CartBundle\Tools;

use Zend_Http_Client as Client;
use BeSimple\SoapBundle\Soap\SoapRequest;

class PaypalClient extends Client {
    private $_api_sandbox_version = '78';
    private $_api_sandbox_username = 'pedidos-facilitator_api1.cosmix.es';
    private $_api_sandbox_password = 'AGTBMMXR4CSS32AP';
    private $_api_sandbox_signature = 'ABX3xD-3o7C.MOLRDjHtxjljh8iVAivFi4rLNbfeNgM3oZe.tWH-COUa';
    public $api_sandbox_expresscheckout_uri = 'https://api-3t.sandbox.paypal.com/nvp';

    private $_api_version = '';
    private $_api_username = '';
    private $_api_password = '';
    private $_api_signature = '';
    public $api_expresscheckout_uri = '';

    private $parameters = array();

    function __construct($env = 'prod', $uri = null, $options = null) {
        parent::__construct($uri, $options);
        if($env == 'dev') {
            $this->parameters = array_merge($this->parameters, array(
                'USER' => urlencode($this->_api_sandbox_username),
                'PWD'=> urlencode($this->_api_sandbox_password),
                'SIGNATURE' => urlencode($this->_api_sandbox_signature),
                'VERSION' => urlencode($this->_api_sandbox_version)
            ));
        } else {
            $this->parameters = array_merge($this->parameters, array(
                'USER' => urlencode($this->_api_username),
                'PWD' => urlencode($this->_api_password),
                'SIGNATURE' => urlencode($this->_api_signature),
                'VERSION' => urlencode($this->_api_version)
            ));
        }
    }

    /**
     * Request an authorization token.
     *
     * @param float $paymentAmount
     * @param string $returnURL
     * @param string $cancelURL
     * @param string $currencyID
     * @param string $payment_action Can be 'Authorization', 'Sale', or 'Order'. Default is 'Authorization'
     * @return Zend_Http_Response
     */
    function ecSetExpressCheckout($paymentAmount, $returnURL, $cancelURL, $currencyID, $items, $landing = 'paypal', $payment_action = 'Authorization') {

        $this->parameters = array_merge($this->parameters, array(
            'METHOD' => 'SetExpressCheckout',
            'RETURNURL' => $returnURL,
            'CANCELURL' => $cancelURL,
            'PAYMENTREQUEST_0_PAYMENTACTION' => $payment_action
        )); // Can be 'Authorization', 'Sale', or 'Order'
        if($landing == 'card') $this->parameters = array_merge($this->parameters, array('LANDINGPAGE' => 'Billing'));

        $i = 0;
// die(var_dump($items->getItems()));
// echo($paymentAmount);
// die();
        foreach($items->getItems() as $item) {
            $this->parameters = array_merge($this->parameters, array(
                'L_PAYMENTREQUEST_0_NAME'.$i => $item->getProduct()->getName(),
//'L_PAYMENTREQUEST_0_NUMBER0' => '123',
                'L_PAYMENTREQUEST_0_DESC'.$i => $item->getProduct()->getDescription(),
                'L_PAYMENTREQUEST_0_AMT'.$i => $item->getProduct()->getPrecio(),
                'L_PAYMENTREQUEST_0_QTY'.$i => $item->getQuantity()
            ));
// &PAYMENTREQUEST_0_ITEMAMT=99.30
// &PAYMENTREQUEST_0_TAXAMT=2.58
// &PAYMENTREQUEST_0_SHIPPINGAMT=3.00
// &PAYMENTREQUEST_0_HANDLINGAMT=2.99
// &PAYMENTREQUEST_0_SHIPDISCAMT=-3.00
// &PAYMENTREQUEST_0_INSURANCEAMT=1.00
            $i++;
        }
        $this->parameters = array_merge($this->parameters, array(
            'PAYMENTREQUEST_0_AMT' => urlencode($paymentAmount),
            'PAYMENTREQUEST_0_CURRENCYCODE' => $currencyID,
            'ALLOWNOTE' => 1
        ));
        $this->setParameterGet($this->parameters);

// die($this->request(\Zend\Http\Client::GET));

// &ALLOWNOTE=1
        return $this->send();
// return $this->send(\Zend\Http\Client::METHOD_GET);
    }

    /**
     *
     * Calls the 'ECDoExpressCheckout' API call. Requires a token that can
     * be obtained using the 'SetExpressCheckout' API call. The payer_id is
     * obtained from the 'SetExpressCheckout' or 'GetExpressCheckoutDetails' API call.
     *
     * @param string $token
     * @param string $payer_id
     * @param float  $payment_amount
     * @param string $currency_code
     * @param string $payment_action Can be 'Authorization', 'Sale', or 'Order'
     *
     * @return Zend_Http_Response
     * @throws Zend_Http_Client_Exception
     */
    function ecDoExpressCheckout($token, $payer_id, $payment_amount, $currency_code, $payment_action = 'Sale') {
        $this->parameters = array_merge($this->parameters, array(
            'METHOD' => 'DoExpressCheckoutPayment',
            'PAYMENTREQUEST_0_AMT' => $payment_amount,
            'PAYMENTREQUEST_0_CURRENCYCODE' => 'EUR',
            'TOKEN' => $token,
            'PAYERID' => $payer_id,
            'PAYMENTACTION' => $payment_action
        )); // Can be 'Authorization', 'Sale', or 'Order'
        $this->setParameterGet($this->parameters);
        return $this->send();
// return $this->request(\Zend\Http\Client::GET);
    }

    function ecGetExpressCheckoutDetails($token) {
        $this->parameters = array_merge($this->parameters, array(
            'METHOD' => 'GetExpressCheckoutDetails',
            'TOKEN' => $token
        ));
        $this->setParameterGet($this->parameters);
        return $this->send();
    }


    /**
     * Parse a Name-Value Pair response into an object.
     * @param string $response
     * @return object Returns an object representation of the fresponse.
     */
    public static function parse($response) {

        $responseArray = explode("&amp;", $response);
        $responseArray = explode("&", $response);

        $result = array();

        if (count($responseArray) > 0) {
            foreach ($responseArray as $i => $value) {

                $keyValuePair = explode("=", $value);

                if(sizeof($keyValuePair) > 1) {
                    $result[$keyValuePair[0]] = urldecode($keyValuePair[1]);
                }
            }
        }

        if (empty($result)) {
            $result = null;
        } else {
            $result = (object) $result;
        }

        return $result;
    }
}