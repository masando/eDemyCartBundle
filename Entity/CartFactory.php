<?php

namespace eDemy\CartBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use JMS\Serializer\Annotation\Type;

class CartFactory
{
    public function get(SessionInterface $session, $serializer, EntityManager $em)
    {
        $cart = new Cart($session, $serializer, $em);

        return $cart;
    }	
}
