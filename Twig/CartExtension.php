<?php

namespace eDemy\CartBundle\Twig;

//use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CartExtension extends \Twig_Extension
{
    /** @var ContainerInterface $this->container */
    protected $container;
    
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('cartButton', array($this, 'cartButtonFunction'), array('is_safe' => array('html'), 'pre_escape' => 'html')),
            new \Twig_SimpleFunction('buyButton', array($this, 'buyButtonFunction'), array('is_safe' => array('html'), 'pre_escape' => 'html')),
        );
    }

    public function cartButtonFunction($entity)
    {
        //if ($this->container->get('security.authorization_checker')->isGranted('ROLE_USER')) {
        $router = $this->container->get('router');
        $edemyMain = $this->container->get('edemy.main');
        //$namespace = $edemyMain->getNamespace();
        //$ruta =  $namespace . '.' . $_route;
        if($entity->getPrice()) {
            $button = $edemyMain->render(
                'templates/cart/button',
                array(
                    'entity' => $entity,
                )
            );

            return $button;
        }

        return false;
        //}
    }

    public function getName()
    {
        return 'edemy_cart_extension';
    }

    public function buyButtonFunction()
    {
        //        if ($this->container->get('security.authorization_checker')->isGranted('ROLE_USER')) {
        $router = $this->container->get('router');
        $edemyMain = $this->container->get('edemy.main');
        //$namespace = $edemyMain->getNamespace();
        //$ruta =  $namespace . '.' . $_route;
        $button = $edemyMain->render(
            'templates/cart/buy',
            array(
                //'entity' => $entity,
            )
        );

        return $button;
//        }
    }
}
