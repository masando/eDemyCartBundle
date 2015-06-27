<?php
namespace eDemy\CartBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;
use JMS\SecurityExtraBundle\Annotation\Secure;

use eDemy\CartBundle\Entity\Cart;
use eDemy\CartBundle\Form\CartType;

use Zend\Http\Client;
use BeSimple\SoapBundle\Soap\SoapRequest;

/**
 * @Route("/paypal")
 */
class PaypalController extends Controller {
    private $order;
    /**
     * @Secure(roles="IS_AUTHENTICATED_ANONYMOUSLY")
     * @Route("/")
     * @Template()
     */
    public function indexAction() {
        $order = $this->getOrder();

        return array(
            'cart' => $order
        );
    }
    public function getOrder(){
        $em = $this->get('doctrine')->getEntityManager();
        $cart = $this->get('request')->getSession()->get('cart');
        $order = null;
        if($cart == null) {
            $cart = new Cart($this->container->get('request')->getSession());
            $this->container->get('request')->getSession()->set('cart', $cart);
        } else {
            $total = (float) 0.00;
            $discount = 0.00;
            $order = $this->container->get('edemy_order.manager.order')->create();
            foreach($cart->getItems() as $cartItem) {
                $product = $em->getRepository('eDemyProductBundle:Product')->findOneBy(
                    array('id' => $cartItem->getProductId())
                );
                $item = new \eDemy\OrderBundle\Entity\OrderItem();
                $item->setProduct($product);
                $item->setQuantity($cartItem->getQuantity());
                $item->setPrice($product->getPrecio());
                $item->setTotal($cartItem->getTotal());
                $item->setDiscount($cartItem->getDiscount());
                $total += $item->getTotal();
                $discount += $item->getDiscount();
                $order->addItem($item);
            }
            $order->setTotal($total);
            $order->setDiscount($discount);
        }
        return $order;
    }

    public function setOrder($replyData){
        // $countrycode = $replyData->COUNTRYCODE;
        //$business = $replyData->BUSINESS;
        //$shiptostate = $replyData->PAYMENTREQUEST_0_SHIPTOSTATE;
        //$shiptocountrycode = $replyData->PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE;
        //$shiptocountryname = $replyData->PAYMENTREQUEST_0_SHIPTOCOUNTRYNAME;
        //$shiptozip = $replyData->PAYMENTREQUEST_0_SHIPTOZIP;
        //$addressid = $replyData->PAYMENTREQUEST_0_ADDRESSID;
        //$addressstatus = $replyData->PAYMENTREQUEST_0_ADDRESSSTATUS;
        //$em = $this->get('doctrine')->getEntityManager();
        $order = $this->getOrder();
        if($order != null){
            $order->setStatus(0);
            $order->setName('a');

            $customer = $this->container->get('edemy_customer.manager.customer')->create();
            $customer->setName($replyData->PAYMENTREQUEST_0_SHIPTONAME.' '.$replyData->FIRSTNAME.' '.$replyData->LASTNAME);
            $customer->setStreet($replyData->PAYMENTREQUEST_0_SHIPTOSTREET);
            $customer->setCity($replyData->PAYMENTREQUEST_0_SHIPTOCITY);
            $customer->setEmail($replyData->EMAIL);
            $customer->setPhone(" ");
            $this->container->get('edemy_customer.manager.customer')->add($customer);
            $order->setCustomer($customer);

            foreach($order->getItems() as $item){

                $product = $item->getProduct();
                $stock = $product->getStock();
                $product->setStock($stock - $item->getQuantity());
            }
            $amount = $order->getTotal();
            $currency_code = 'EUR';

            $this->container->get('edemy_order.manager.order')->add($order);
            $message = \Swift_Message::newInstance()
                ->setSubject('Nuevo pedido')
                ->setFrom($customer->getEmail())
                ->setTo('info@be-deco.com')
                ->setBcc('manuel@edemy.es')
                ->setBody('Nuevo pedido');
            //->setBody($this->renderView('HelloBundle:Hello:email.txt.twig', array('name' => $name)))
            ;
            $this->get('mailer')->send($message);

        }
        return $order;
    }
    /**
     * @Secure(roles="IS_AUTHENTICATED_ANONYMOUSLY")
     */
    public function cartCountAction() {
        $session = $this->get('request')->getSession();
        $cart = $session->get('cart');
        $count = 0;
        if($cart != null){
            foreach($cart->getItems() as $item) {
                $count += $item->getQuantity();
            }
        }
        return new Response($count);
    }
    /**
     * @Secure(roles="IS_AUTHENTICATED_ANONYMOUSLY")
     * @Route("/add/{id}/")
     * @Template()
     */
    public function addAction($id=null) {
        if ($this->container->get('request')->isXmlHttpRequest()) {
            $session = $this->get('request')->getSession();
            $quantity = $this->get('request')->query->get('quantity');
            $response = new Response();
            $em = $this->get('doctrine')->getEntityManager();
            $cart = $session->get('cart');
            if(!isset($cart)){
                $cart = new Cart($this->container->get('request')->getSession());
                $session->set('cart', $cart);
            }
            if (isset($id)) {
                $cartItem = new \eDemy\CartBundle\Entity\CartItem();
                $product = $em->getRepository('eDemyProductBundle:Product')->findOneBy(
                    array('id' => $id)
                );
                //TODO comprobardev que la cantidad solicitada es menor o igual que el stock
                //TODO comprobar que la cantidad solicitada es menor o igual que el stock
                //comprobar si ya existe en el carrito y en ese caso sumar la cantidad (si menor o igual que stock)
                if($this->container->getParameter('kernel.environment') == 'dev') {
                    //TODO
                }

                $cartItem->setProductId($product->getId());
                $cartItem->setQuantity($quantity);
                $cartItem->setTotal($quantity * $product->getPrecio());
                $cartItem->setDiscount($quantity * ($product->getPrecioInicial() - $product->getPrecio()));
                $cart->addItem($cartItem);

                $session->set('cart',$cart);
                $response->headers->set('Content-Type', 'text/plain');
                $response->setContent("ok");
            }
        }
        return $response;
    }
    /**
     * @Secure(roles="IS_AUTHENTICATED_ANONYMOUSLY")
     * @Route("/remove/{id}")
     * @Template()
     */
    public function removeAction($id) {
        $session = $this->get('request')->getSession();
        $cart = $session->get('cart');
        if($cart != null){
            if (isset($id)) {
                $cart->removeItem($id);
            }
        }
        return $this->redirect($this->generateUrl('edemy_cart_cart_index'));
    }
    /**
     * @Secure(roles="IS_AUTHENTICATED_ANONYMOUSLY")
     * @Route("/soap")
     */
    public function soapAction() {
        $soaprequest = new SoapRequest();

        return $this->redirect($this->generateUrl('edemy_cart_cart_index'));
    }


    /**
     * @Route("/paypal/{landing}", defaults={"landing" = "paypal"})
     */
    public function paypalAction($landing = 'paypal') {
        //TODO comprobar que no se ha producido ninguna

        if($this->container->getParameter('kernel.environment') == 'dev') {
            $uri = 'https://api-3t.sandbox.paypal.com/nvp';
            $returnURL = 'http://www.be-deco.com/app_dev.php/cart/paymentcompleted';
            $cancelURL = 'http://www.be-deco.com/app_dev.php/cart';
        } else {
            $uri = 'https://api-3t.paypal.com/nvp';
            $returnURL = 'http://www.be-deco.com/cart/paymentcompleted';
            $cancelURL = 'http://www.be-deco.com/cart';
        }

        $order = $this->getOrder();

        // $em = $this->get('doctrine')->getEntityManager();
        // $session = $this->get('request')->getSession();
        // $cart = $session->get('cart');
        // $total = (float) 0.00;
        // $discount = 0.00;
        // if($cart != null){
        // $items = new \eDemy\CartBundle\Entity\CartItems();
        // foreach($cart->getItems() as $cartItem){
        // $product = $em->getRepository('eDemyProductBundle:Product')->findOneBy(
        // array('id' => $cartItem->getProductId())
        // );
        // $item = new \eDemy\CartBundle\Entity\CartItem();
        // $item->setProduct($product);
        // $item->setQuantity($cartItem->getQuantity());
        // $item->setTotal($cartItem->getTotal());
        // $item->setDiscount($cartItem->getDiscount());
        // $total += $cartItem->getTotal();
        // $discount += $cartItem->getDiscount();
        // $items->addItem($item);

        // }
        // }

        $amount = $order->getTotal();

        $currency_code = 'EUR';

        $adapter = new Paypal_Client($this->container->getParameter('kernel.environment'), $uri);
        // $paymentAmount,
        // $returnURL,
        // $cancelURL,
        // $currencyID,
        // $items,
        // $landing = 'paypal',
        // $payment_action = 'Authorization')
        $reply = $adapter->ecSetExpressCheckout(
            $amount,
            $returnURL,
            $cancelURL,
            $currency_code,
            $order,
            $landing
        );
        // die(var_dump($reply));

        if ($reply->isSuccess()) {
            $replyData = $adapter->parse($reply->getBody());
            //echo(var_dump($reply));
            //echo($replyData->TOKEN);
            //die();
            if ($replyData->ACK == 'Success' || $replyData->ACK == 'SUCCESSWITHWARNING') {
                //die("dentro");
                $token = $replyData->TOKEN; // ...It's already URL encoded for us.
                // Save the amount total... We must use this when we capture the funds.
                $_SESSION['CHECKOUT_AMOUNT'] = $amount;
                // Redirect to the PayPal express checkout page, using the token.

                if($this->container->getParameter('kernel.environment') == 'dev') {
                    //die();
                    header(
                        'Location: ' . $adapter->api_sandbox_expresscheckout_uri . '?&cmd=_express-checkout&token=' . $token
                    );
                } else {

                    header(
                        'Location: ' . $adapter->api_expresscheckout_uri . '?&cmd=_express-checkout&token=' . $token
                    );
                }
            }
        } else {
            throw new Exception('ECSetExpressCheckout: We failed to get a successfull response from PayPal.');
        }
        die("a");
    }

    /**
     * @Route("/paymentcompleted")
     */
    public function paymentCompleteAction() {
        $request = $this->getRequest();
        $token = $request->query->get('token');
        $payerid = $request->query->get('PayerID');

        $data = array();

        if($this->container->getParameter('kernel.environment') == 'dev') {
            $uri = 'https://api-3t.sandbox.paypal.com/nvp';
            $returnURL = 'http://www.be-deco.com/app_dev.php/cart/paymentcomplete';
            $cancelURL = 'http://www.be-deco.com/app_dev.php/cart/paymentcancelled';
        } else {
            $uri = 'https://api-3t.paypal.com/nvp';
            $returnURL = 'http://www.be-deco.com/cart/paymentcomplete';
            $cancelURL = 'http://www.be-deco.com/cart/cart';
        }
        $currency_code = 'EUR';

        $adapter = new Paypal_Client($this->container->getParameter('kernel.environment'), $uri);

        $reply = $adapter->ecGetExpressCheckoutDetails(
            $token
        );
        $this->order = null;
        if ($reply->isSuccess()) {
            $replyData = $adapter->parse($reply->getBody());
            if ($replyData->ACK == 'Success' || $replyData->ACK == 'SUCCESSWITHWARNING') {
                $payer_id = $replyData->PAYERID;
                $payerstatus = $replyData->PAYERSTATUS;

                $this->order = $this->setOrder($replyData);
                if($this->order){
                    $amount = $this->order->getTotal();
                }
            }
        } else {
            throw new Exception('No hemos obtenido una respuesta de Paypal.');
        }
        if($amount!=null){
            $this->confirmPayAction($token, $payer_id, $amount, $currency_code);
        }
        return $this->render('eDemyCartBundle:Cart:confirm.html.twig', array(
            'order' => $this->order
        ));
    }

    /**
     * @Route("/paymentconfirmed")
     */
    public function confirmPayAction($token, $payer_id, $payment_amount, $currency_code) {
        if($this->container->getParameter('kernel.environment') == 'dev') {
            $uri = 'https://api-3t.sandbox.paypal.com/nvp';
        } else {
            $uri = 'https://api-3t.paypal.com/nvp';
        }
        $adapter = new Paypal_Client($this->container->getParameter('kernel.environment'), $uri);
        $reply = $adapter->ecDoExpressCheckout(
            $token,
            $payer_id,
            $payment_amount,
            $currency_code
        );
        if ($reply->isSuccess()) {
            $replyData = $adapter->parse($reply->getBody());
            //echo(var_dump($reply));
            //echo($replyData->TOKEN);
            //die();
            if ($replyData->ACK == 'Success' || $replyData->ACK == 'SUCCESSWITHWARNING') {
                //die("dentro");
                $transactionid = $replyData->PAYMENTINFO_0_TRANSACTIONID; // ...It's already URL encoded for us.
                $paymenttype = $replyData->PAYMENTINFO_0_TRANSACTIONTYPE;
                $ordertime = $replyData->PAYMENTINFO_0_ORDERTIME;
                $AMT = $replyData->PAYMENTINFO_0_AMT;
                $currencycode = $replyData->PAYMENTINFO_0_CURRENCYCODE;
                $taxamt = $replyData->PAYMENTINFO_0_TAXAMT;
                $paymentstatus = $replyData->PAYMENTINFO_0_PAYMENTSTATUS;
                $pendingreason = $replyData->PAYMENTINFO_0_PENDINGREASON;
                $reasoncode = $replyData->PAYMENTINFO_0_REASONCODE;

                $cart = $this->get('request')->getSession()->get('cart');
                $cart = null;
                $this->get('request')->getSession()->set('cart',$cart);
            }
        } else {
            throw new Exception('ECSetExpressCheckout: We failed to get a successfull response from PayPal.');
        }
        return new Response("ok");
    }
}

class Paypal_Client extends Client {
    private $_api_sandbox_version = '70.0';
    private $_api_sandbox_username = 'vende_1312624782_biz_api1.edemy.es';
    private $_api_sandbox_password = '1312624828';
    private $_api_sandbox_signature = 'AiZwnDKNL6i3uz1MQrLk3eOUmQTnASOmM4mjVIkKs8oM42VXkNfL04c9';
    public $api_sandbox_expresscheckout_uri = 'https://www.sandbox.paypal.com/webscr';

    private $_api_version = '70.0';
    private $_api_username = 'paypal_api1.be-deco.com';
    private $_api_password = 'MV8UZ2SJDCZDUKPQ';
    private $_api_signature = 'AhVPp0TCPuiakC1aCWCeiJlc.2d0AXqBwR8xoMjvX1H5J6X-BLQ5ptLR';
    public $api_expresscheckout_uri = 'https://www.paypal.com/webscr';

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
