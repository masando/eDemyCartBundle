<?php

namespace eDemy\CartBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;

use eDemy\MainBundle\Controller\BaseController;
use eDemy\MainBundle\Event\ContentEvent;
use Symfony\Component\EventDispatcher\GenericEvent;
use eDemy\MainBundle\Entity\Param;
use eDemy\CartBundle\Entity\Cart;
use eDemy\CartBundle\Entity\CartItem;
use eDemy\CartBundle\Tools\PaypalClient;

class CartController extends BaseController
{
    public static function getSubscribedEvents()
    {
        return self::getSubscriptions('cart', ['cart'], array(
            'edemy_header_module_'              => array('onHeaderModuleTray', 0),
            //'edemy_cart_cart_index'           => array('onCartIndex', 0),
            'edemy_cart_cart_customercreate'    => array('onCartCustomerCreate', 0),
            'edemy_cart_cart_customernotify'    => array('onCartCustomeNotify', 0),
            'edemy_cart_cart_creditcard'        => array('onCartCreditCard', 0),
            'edemy_cart_cart_cashondelivery'    => array('onCartCashOnDelivery', 0),
            'edemy_cart_cart_paypal'            => array('onCartPaypal', 0),
            'edemy_cart_cart_add'               => array('onCartAdd', 0),
            'edemy_cart_cart_remove'            => array('onCartRemove', 0),
            'edemy_cart_cart_notify'            => array('onCartNotify', 0),
            'edemy_cart_cart_success'           => array('onCartSuccess', 0),
            'edemy_cart_cart_paypallanding'         => array('onPaypalAction', 0),
            'edemy_cart_cart_paypalpaymentcompleted'=> array('onPaypalPaymentCompletedAction', 0),
        ));
    }

    public function onFrontpage(ContentEvent $event)
    {
//        die(var_dump($this->getSessionId()));
        $this->addEventModule($event, 'templates/cart/frontpage', array(
            'cart'                => $this->getCart(),
            //'items'             => $this->get('edemy.cart')->getItems(),
            //'locale'            => $request = $this->requestStack->getCurrentRequest()->attributes->get('_locale'),
        ));

        return true;
    }

    public function getCart()
    {
        $cart = $this->getRepository()->findBySession($this->getSessionId());
        if($cart) {
            $cart->setEntityManager($this->get('doctrine.orm.entity_manager'));
        } else {
            $cart = new Cart($this->getEm(), $this->getSessionId());
        }

        return $cart;
    }

    public function emptyCart()
    {
        $cart = $this->getRepository()->findBySession($this->getSessionId());

        if($cart) {
            $cart->emptyCart($this->getEm());
        }

        return true;
    }

    public function onCartAdd(ContentEvent $event)
    {
        $id = $this->getRequest()->attributes->get('id');
        $product = $this->get('doctrine.orm.entity_manager')->getRepository('eDemyProductBundle:Product')->findOneById($id);

        if(($mailto = $this->getParam('sendtomail', 'eDemyMainBundle')) != 'sendtomail') {
            $message = \Swift_Message::newInstance()
                ->setSubject('add to cart ' . $product->getName())
                //->setFrom('__MAILTO__')
                ->setTo($mailto)
                ->setBody($product->getName());
            //$this->get('mailer')->send($message);
        }

        if($this->get('kernel')->getEnvironment() == 'dev') {
            //die(var_dump($this->get('edemy.cart')));
            //die(var_dump($product->getId()));
            //die(var_dump($product));
        }
        //die(var_dump($product));
        if($product) {
            $cart = $this->getCart();
            // @TODO BUSCAR EL ITEM EN EL CARRITO PARA SUMAR O AÑADIR
            $cart->addProduct($product);
            $this->getEm()->persist($cart);
            $this->getEm()->flush();
            //$this->get('edemy.cart')->addObject($product->getId(), 'cesta');
        }

        $response = $this->newRedirectResponse('edemy_cart_frontpage');
        $event->setContent($response);
        $event->stopPropagation();

        //$this->addEventContent($event, $this->newRedirectResponse($this->router->generate('edemy_cart_cart_index')));
        //$event->stopPropagation();

        return true;
    }

    public function onCartRemove(ContentEvent $event)
    {
        $id = $this->getRequest()->attributes->get('id');
        $product = $this->get('doctrine.orm.entity_manager')->getRepository('eDemyProductBundle:Product')->findOneById($id);
        if($product) {
            $cart = $this->getCart();
            $cart->removeProduct($product);
            $this->getEm()->persist($cart);
            $this->getEm()->flush();
        }

        $response = $this->newRedirectResponse('edemy_cart_frontpage');
        $event->setContent($response);
        $event->stopPropagation();

        return true;
    }

    public function onHeaderModuleTray(ContentEvent $event)
    {
        //die(var_dump($this->get('edemy.cart')));
        $this->addEventModule($event, 'templates/cart/tray.html.twig', array(
            'cart' => $this->get('edemy.cart'),
        ));

        return true;
    }
    
    public function onCartCustomerCreate(ContentEvent $event)
    {
        $request = $this->requestStack->getCurrentRequest();
        $entity = new Customer();
        $form = $this->createCreateForm($entity);
        $form->handleRequest($request);
        if ($form->isValid()) {
            //$this->em->persist($entity);
            //$this->em->flush();
            if($form->get('creditcard')->isClicked()) {
                $nextAction = 'edemy_cart_cart_creditcard';
            }
            if($form->get('cashondelivery')->isClicked()) {
                $nextAction = 'edemy_cart_cart_cashondelivery';
            }
            if($form->get('paypal')->isClicked()) {
                $nextAction = 'edemy_cart_cart_paypal';
            }
            $event->addModule(
                $this->newRedirectResponse(
                    $this->router->generate($nextAction)
                )
            );

            return true;
        }

		//$host = $frontcontroller = $request->getHost();
		//$env = $this->kernel->getEnvironment();
		//if($env == 'dev') {
            //TODO remove reference to dev.php
		//	$frontcontroller = $host . '/dev.php';
        //}
        $event->addModule($this->templating->render(
            "eDemyCartBundle::customer.html.twig",
            array(
                //'host'              => $host,
                //'env'               => $env,
                //'frontcontroller'   => $frontcontroller,
                'cart'              => $this->cart,
                'items'             => $this->cart->getItems(),
                'entity' => $entity,
                'form'   => $form->createView(),
                //'locale'            => $request = $this->requestStack->getCurrentRequest()->attributes->get('_locale'),
            ))
        );

        return true;
    }

    public function onCartCreditCard(ContentEvent $event)
    {
        //$request = $this->requestStack->getCurrentRequest();
        $event->addModule($this->templating->render(
            "eDemyCartBundle::creditcard.html.twig",
            array(
                'cart'              => $this->cart,
                'items'             => $this->cart->getItems(),
            ))
        );

        return true;
    }

    public function onCartCashOnDelivery(ContentEvent $event)
    {
        //$request = $this->requestStack->getCurrentRequest();
        $event->addModule($this->templating->render(
            "eDemyCartBundle::cashondelivery.html.twig",
            array(
                'cart'              => $this->cart,
                'items'             => $this->cart->getItems(),
            ))
        );

        return true;
    }

    public function onCartPaypal(ContentEvent $event)
    {
        //$request = $this->requestStack->getCurrentRequest();
        $this->addEventModule($event, "templates/cart/paypal", array(
            'cart'              => $this->cart,
            'items'             => $this->cart->getItems(),
        ));

        return true;
    }

    public function onCartCustomerNotify(ContentEvent $event)
    {
        
    }

    public function onCartNotify(ContentEvent $event)
    {
        $request = $this->requestStack->getCurrentRequest();
        $msg = "";
        $host = $frontcontroller = $request->getHost();
        $env = $this->kernel->getEnvironment();
        if($host == '' or $env == 'dev'){
            $mailmsg = 'pedido de prueba';
            $mailto = '';
        } else {
            $mailmsg = 'Nuevo pedido';
            $mailto = '';
        }
        
        foreach($request->request as $key => $value) {
            $msg .= "<" . $key . " " . $value . ">\n";
        }

        $message = \Swift_Message::newInstance()
            ->setSubject($mailmsg)
            ->setFrom('')
            ->setTo($mailto)
            ->setBody($msg)
        ;
        $this->mailer->send($message);

        $event->setContent($this->newRedirectResponse($this->router->generate('edemy_cart_cart_index')));
        $event->stopPropagation();
    }

    public function onCartSuccess(ContentEvent $event)
    {
        $this->cart->clear();

        $event->addModule($this->render("templates/cart/success", array()));

        return true;
    }

    public function showImageAction($id)
    {
        $product = $this->em->getRepository('eDemyProductBundle:Product')->find($id);

        if (!$product) {
            throw new NotFoundHttpException('Unable to find Product entity');
        }
    
        return $this->templating->renderResponse(
            "eDemyCartBundle::showImage.html.twig",
            array(
                'path' => $product->getWebPath(),
            )
        );
    }

    private function createCreateForm(Customer $entity)
    {
        $form = $this->formFactory->create(
            new CustomerType(), 
            $entity, 
            array(
                'action' => $this->router->generate('edemy_cart_cart_customercreate'),
                'method' => 'POST',
            )
        );

        $form->add('submit', 'submit', array('label' => 'Create'));

        return $form;
    }

    public function onPaypalAction($landing = 'paypal')
    {
        $env = $this->get('kernel')->getEnvironment();
        if($env == 'dev') {
            $uri = 'https://api-3t.sandbox.paypal.com/nvp';
            if($this->getParam('paypal_returnURL_dev') != 'paypal_returnURL_dev') {
                $returnURL = $this->getParam('paypal_returnURL_dev');
            }
            if($this->getParam('paypal_cancelURL_dev') != 'paypal_cancelURL_dev') {
                $cancelURL = $this->getParam('paypal_cancelURL_dev');
            }
            if($this->getParam('paypal_api_sandbox_username') != 'paypal_api_sandbox_username') {
                $api_sandbox_username = $this->getParam('paypal_api_sandbox_username');
            }
            if($this->getParam('paypal_api_sandbox_password') != 'paypal_api_sandbox_password') {
                $api_sandbox_password = $this->getParam('paypal_api_sandbox_password');
            }
            if($this->getParam('paypal_api_sandbox_signature') != 'paypal_api_sandbox_signature') {
                $api_sandbox_signature = $this->getParam('paypal_api_sandbox_signature');
            }
            $options = array(
                'USER' => urlencode($api_sandbox_username),
                'PWD'=> urlencode($api_sandbox_password),
                'SIGNATURE' => urlencode($api_sandbox_signature),
                'VERSION' => urlencode('109.0')
            );
        } else {
            $uri = 'https://api-3t.paypal.com/nvp';
            if($this->getParam('paypal_returnURL') != 'paypal_returnURL') {
                $returnURL = $this->getParam('paypal_returnURL');
            }
            if($this->getParam('paypal_cancelURL') != 'paypal_cancelURL') {
                $cancelURL = $this->getParam('paypal_cancelURL');
            }
            if($this->getParam('paypal_api_username') != 'paypal_api_username') {
                $api_username = $this->getParam('paypal_api_username');
            }
            if($this->getParam('paypal_api_password') != 'paypal_api_password') {
                $api_password = $this->getParam('paypal_api_password');
            }
            if($this->getParam('paypal_api_signature') != 'paypal_api_signature') {
                $api_signature = $this->getParam('paypal_api_signature');
            }
            $options = array(
                'USER' => urlencode($api_username),
                'PWD'=> urlencode($api_password),
                'SIGNATURE' => urlencode($api_signature),
                'VERSION' => urlencode('109.0')
            );
        }

        $cart = $this->getCart();
        $amount = $cart->getTotal();
        $currency_code = 'EUR';

        $adapter = new PaypalClient($uri, $options);
        $reply = $adapter->ecSetExpressCheckout(
            $amount,
            $returnURL,
            $cancelURL,
            $currency_code,
            $cart,
            $landing
        );
        if ($reply->isSuccess()) {
            $replyData = $adapter->parse($reply->getBody());
            if ($replyData->ACK == 'Success' || $replyData->ACK == 'SuccessWithWarning') {
                $token = $replyData->TOKEN; // ...It's already URL encoded for us.
                // Save the amount total... We must use this when we capture the funds.
                $_SESSION['CHECKOUT_AMOUNT'] = $amount;
                // Redirect to the PayPal express checkout page, using the token.
                if($env == 'dev') {
                    header(
                        'Location: https://www.sandbox.paypal.com/webscr?&cmd=_express-checkout&token='.$token
                    );
                } else {
                    header(
                        'Location: https://www.paypal.com/webscr?&cmd=_express-checkout&token='.$token
                    );
                }
            }
        } else {
            throw new Exception('ECSetExpressCheckout: We failed to get a successfull response from PayPal.');
        }
    }

    public function onPaypalPaymentCompletedAction(ContentEvent $event) {
        $request = $this->getRequest();
        $env = $this->get('kernel')->getEnvironment();
        if($env == 'dev') {
            $uri = 'https://api-3t.sandbox.paypal.com/nvp';
            if($this->getParam('paypal_returnURL_dev') != 'paypal_returnURL_dev') {
                $returnURL = $this->getParam('paypal_returnURL_dev');
            }
            if($this->getParam('paypal_cancelURL_dev') != 'paypal_cancelURL_dev') {
                $returnURL = $this->getParam('paypal_cancelURL_dev');
            }
            if($this->getParam('paypal_api_sandbox_username') != 'paypal_api_sandbox_username') {
                $api_sandbox_username = $this->getParam('paypal_api_sandbox_username');
            }
            if($this->getParam('paypal_api_sandbox_password') != 'paypal_api_sandbox_password') {
                $api_sandbox_password = $this->getParam('paypal_api_sandbox_password');
            }
            if($this->getParam('paypal_api_sandbox_signature') != 'paypal_api_sandbox_signature') {
                $api_sandbox_signature = $this->getParam('paypal_api_sandbox_signature');
            }
            $options = array(
                'USER' => urlencode($api_sandbox_username),
                'PWD'=> urlencode($api_sandbox_password),
                'SIGNATURE' => urlencode($api_sandbox_signature),
                'VERSION' => urlencode('109.0')
            );
        } else {
            $uri = 'https://api-3t.paypal.com/nvp';
            if($this->getParam('paypal_returnURL') != 'paypal_returnURL') {
                $returnURL = $this->getParam('paypal_returnURL');
            }
            if($this->getParam('paypal_cancelURL') != 'paypal_cancelURL') {
                $returnURL = $this->getParam('paypal_cancelURL');
            }
            if($this->getParam('paypal_api_username') != 'paypal_api_username') {
                $api_username = $this->getParam('paypal_api_username');
            }
            if($this->getParam('paypal_api_password') != 'paypal_api_password') {
                $api_password = $this->getParam('paypal_api_password');
            }
            if($this->getParam('paypal_api_signature') != 'paypal_api_signature') {
                $api_signature = $this->getParam('paypal_api_signature');
            }
            $options = array(
                'USER' => urlencode($api_username),
                'PWD'=> urlencode($api_password),
                'SIGNATURE' => urlencode($api_signature),
                'VERSION' => urlencode('109.0')
            );
        }
        $token = $request->query->get('token');
        $payerid = $request->query->get('PayerID');
        $data = array();
        $currency_code = 'EUR';
        $adapter = new PaypalClient($uri, $options);
        $reply = $adapter->ecGetExpressCheckoutDetails(
            $token
        );

        $this->order = null;
        if ($reply->isSuccess()) {
            $replyData = $adapter->parse($reply->getBody());
            if ($replyData->ACK == 'Success' || $replyData->ACK == 'SUCCESSWITHWARNING') {
                $payer_id = $replyData->PAYERID;
                $payerstatus = $replyData->PAYERSTATUS;
                $amount = $replyData->AMT;
            }
        } else {
            throw new Exception('No hemos obtenido una respuesta de Paypal.');
        }
        if($amount!=null){
            $this->confirmPayAction($token, $payer_id, $amount, $currency_code);
        }
        //EL CARRITO SE CONVIERTE EN PEDIDO Y SE VACÍA
        $this->emptyCart();
        $this->addEventModule($event, 'templates/cart/success', array(
            //'cart'                => $this->getCart(),
            //'order'               => $this->order,
            //'items'             => $this->get('edemy.cart')->getItems(),
            //'locale'            => $request = $this->requestStack->getCurrentRequest()->attributes->get('_locale'),
        ));
    }

    /**
     * @Route("/paymentconfirmed")
     */
    public function confirmPayAction($token, $payer_id, $payment_amount, $currency_code) {
        //die('b');
        $env = $this->get('kernel')->getEnvironment();
        if($env == 'dev') {
            $uri = 'https://api-3t.sandbox.paypal.com/nvp';
            if($this->getParam('paypal_returnURL_dev') != 'paypal_returnURL_dev') {
                $returnURL = $this->getParam('paypal_returnURL_dev');
            }
            if($this->getParam('paypal_cancelURL_dev') != 'paypal_cancelURL_dev') {
                $returnURL = $this->getParam('paypal_cancelURL_dev');
            }
            if($this->getParam('paypal_api_sandbox_username') != 'paypal_api_sandbox_username') {
                $api_sandbox_username = $this->getParam('paypal_api_sandbox_username');
            }
            if($this->getParam('paypal_api_sandbox_password') != 'paypal_api_sandbox_password') {
                $api_sandbox_password = $this->getParam('paypal_api_sandbox_password');
            }
            if($this->getParam('paypal_api_sandbox_signature') != 'paypal_api_sandbox_signature') {
                $api_sandbox_signature = $this->getParam('paypal_api_sandbox_signature');
            }
            $options = array(
                'USER' => urlencode($api_sandbox_username),
                'PWD'=> urlencode($api_sandbox_password),
                'SIGNATURE' => urlencode($api_sandbox_signature),
                'VERSION' => urlencode('109.0')
            );
        } else {
            $uri = 'https://api-3t.paypal.com/nvp';
            if($this->getParam('paypal_returnURL') != 'paypal_returnURL') {
                $returnURL = $this->getParam('paypal_returnURL');
            }
            if($this->getParam('paypal_cancelURL') != 'paypal_cancelURL') {
                $returnURL = $this->getParam('paypal_cancelURL');
            }
            if($this->getParam('paypal_api_username') != 'paypal_api_username') {
                $api_username = $this->getParam('paypal_api_username');
            }
            if($this->getParam('paypal_api_password') != 'paypal_api_password') {
                $api_password = $this->getParam('paypal_api_password');
            }
            if($this->getParam('paypal_api_signature') != 'paypal_api_signature') {
                $api_signature = $this->getParam('paypal_api_signature');
            }
            $options = array(
                'USER' => urlencode($api_username),
                'PWD'=> urlencode($api_password),
                'SIGNATURE' => urlencode($api_signature),
                'VERSION' => urlencode('109.0')
            );
        }
        $adapter = new PaypalClient($uri, $options);
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
                //BORRAR CARRITO
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

        return true;
    }


}
