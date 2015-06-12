<?php

namespace eDemy\CartBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;

use eDemy\MainBundle\Controller\BaseController;
use eDemy\MainBundle\Event\ContentEvent;

use eDemy\CartBundle\Entity\Cart;
use eDemy\CustomerBundle\Entity\Customer;
use eDemy\CustomerBundle\Form\CustomerType;

class CartController extends BaseController
{
    public static function getSubscribedEvents()
    {
        return self::getSubscriptions('cart', [], array(
            'edemy_header_module_'                 => array('onHeaderModuleTray', 0),

            'edemy_main_cart_index'             => array('onCartIndex', 0),
            'edemy_main_cart_customercreate'    => array('onCartCustomerCreate', 0),
            'edemy_main_cart_customernotify'    => array('onCartCustomeNotify', 0),
            'edemy_main_cart_creditcard'        => array('onCartCreditCard', 0),
            'edemy_main_cart_cashondelivery'    => array('onCartCashOnDelivery', 0),
            'edemy_main_cart_paypal'            => array('onCartPaypal', 0),

            'edemy_main_cart_add'        => array('onCartAdd', 0),
            'edemy_main_cart_remove'     => array('onCartRemove', 0),
            'edemy_main_cart_notify'     => array('onCartNotify', 0),
            'edemy_main_cart_success'    => array('onCartSuccess', 0),
        ));
    }

    public function onHeaderModuleTray(ContentEvent $event)
    {
        //die(var_dump($this->get('edemy.cart')));
        $this->addEventModule($event, 'tray.html.twig', array(
            'cart' => $this->get('edemy.cart'),
        ));

        return true;
    }
    
    public function onCartIndex(ContentEvent $event)
    {
        $cart = $this->get('edemy.cart');
        //die(var_dump($cart));
        //$request = $this->get('request');
        $this->addEventModule($event, 'index.html.twig', array(
            'cart'              => $cart,
            //'items'             => $this->get('edemy.cart')->getItems(),
            //'locale'            => $request = $this->requestStack->getCurrentRequest()->attributes->get('_locale'),
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
                $nextAction = 'edemy_main_cart_creditcard';
            }
            if($form->get('cashondelivery')->isClicked()) {
                $nextAction = 'edemy_main_cart_cashondelivery';
            }
            if($form->get('paypal')->isClicked()) {
                $nextAction = 'edemy_main_cart_paypal';
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
        $event->addModule($this->templating->render(
            "eDemyCartBundle::paypal.html.twig",
            array(
                'cart'              => $this->cart,
                'items'             => $this->cart->getItems(),
            ))
        );

        return true;
    }

    public function onCartCustomerNotify(ContentEvent $event)
    {
        
    }

    public function onCartAdd(ContentEvent $event)
    {
        $id = $this->getRequest()->attributes->get('id');
        $product = $this->get('doctrine.orm.entity_manager')->getRepository('eDemyProductBundle:Product')->findOneById($id);
        if($this->get('kernel')->getEnvironment() == 'dev') {
            //die(var_dump($this->get('edemy.cart')));
            //die(var_dump($product->getId()));
            //die(var_dump($product));
        }
        if($product) {
            $this->get('edemy.cart')->addObject($product->getId(), 'cesta');
        }
        
        $response = $this->newRedirectResponse('edemy_main_cart_index');
        $event->setContent($response);
        $event->stopPropagation();
        
        //$this->addEventContent($event, $this->newRedirectResponse($this->router->generate('edemy_main_cart_index')));
        //$event->stopPropagation();

        return true;
    }

    public function onCartRemove(ContentEvent $event)
    {
        $id = $this->requestStack->getCurrentRequest()->attributes->get('id');
        $product = $this->em->getRepository('eDemyProductBundle:Product')->findOneById($id);
        $this->cart->removeProduct($product);

        $event->setContent($this->newRedirectResponse($this->router->generate('edemy_main_cart_index')));
        $event->stopPropagation();

        return true;
    }

    public function onCartNotify(ContentEvent $event)
    {
        $request = $this->requestStack->getCurrentRequest();
        $msg = "";
        $host = $frontcontroller = $request->getHost();
        $env = $this->kernel->getEnvironment();
        if($host == 'beta.blenderseyewear.es' or $env == 'dev'){
            $mailmsg = 'pedido de prueba';
            $mailto = 'manuel@edemy.es';
        } else {
            $mailmsg = 'Nuevo pedido';
            $mailto = 'pedidos@blenderseyewear.es';
        }
        
        foreach($request->request as $key => $value) {
            $msg .= "<" . $key . " " . $value . ">\n";
        }

        $message = \Swift_Message::newInstance()
            ->setSubject($mailmsg)
            ->setFrom('hola@blenderseyewear.es')
            ->setTo($mailto)
            ->setBody($msg)
        ;
        $this->mailer->send($message);

        $event->setContent($this->newRedirectResponse($this->router->generate('edemy_main_cart_index')));
        $event->stopPropagation();
    }

    public function onCartSuccess(ContentEvent $event)
    {
        $this->cart->clear();

        $event->addModule($this->templating->render(
            "eDemyCartBundle::success.html.twig",
            array()
        ));

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
                'action' => $this->router->generate('edemy_main_cart_customercreate'),
                'method' => 'POST',
            )
        );

        $form->add('submit', 'submit', array('label' => 'Create'));

        return $form;
    }
}
