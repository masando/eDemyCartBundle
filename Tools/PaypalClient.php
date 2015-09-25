<?php
namespace eDemy\CartBundle\Tools;

use Zend\Http\Client;
use BeSimple\SoapBundle\Soap\SoapRequest;

class PaypalClient extends Client {
    private $parameters = array();

    function __construct($uri = null, $options = array()) {
        parent::__construct($uri, $options);
        $this->parameters = array_merge($this->parameters, $options);
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
        foreach($items->getItems() as $item) {
            $this->parameters = array_merge($this->parameters, array(
                'L_PAYMENTREQUEST_0_NAME' . $i => $item->getProduct()->getName(),
                'L_PAYMENTREQUEST_0_DESC' . $i => 'description',//$item->getProduct()->getDescription(),
                'L_PAYMENTREQUEST_0_AMT' . $i => $item->getProduct()->getPrice(),
                'L_PAYMENTREQUEST_0_QTY' . $i => $item->getQuantity()
            ));
            $i++;
        }
        $this->parameters = array_merge($this->parameters, array(
            'PAYMENTREQUEST_0_CURRENCYCODE' => $currencyID,
            'PAYMENTREQUEST_0_SHIPPINGAMT' => urlencode(4.95),
            'PAYMENTREQUEST_0_ITEMAMT' => urlencode($paymentAmount),
            'PAYMENTREQUEST_0_AMT' => urlencode($paymentAmount + 4.95),
            'ALLOWNOTE' => 1
        ));
        $this->setParameterGet($this->parameters);

        return $this->send();
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
    function ecDoExpressCheckout($token, $payer_id, $payment_amount, $currency_code, $payment_action = 'Authorization') {
        $this->parameters = array_merge($this->parameters, array(
            'METHOD' => 'DoExpressCheckoutPayment',
            'PAYMENTREQUEST_0_AMT' => $payment_amount,
            'PAYMENTREQUEST_0_CURRENCYCODE' => 'EUR',
            'PAYMENTREQUEST_0_PAYMENTACTION' => $payment_action,
            'TOKEN' => $token,
            'PAYERID' => $payer_id,
            'PAYMENTACTION' => $payment_action,
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