<?php

namespace eDemy\CartBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use JMS\Serializer\Annotation as SER;
use eDemy\MainBundle\Entity\BaseEntity;

/**
 * @ORM\Table("CartItem")
 * @ORM\Entity
 */
class CartItem extends BaseEntity
{
    /**
     * @ORM\ManyToOne(targetEntity="eDemy\CartBundle\Entity\Cart", inversedBy="items")
     */
    protected $cart;

    public function setCart($cart)
    {
        $this->cart = $cart;

        return $this;
    }

    public function getCart()
    {
        return $this->cart;
    }

    /**
     * @ORM\Column(name="ref", type="integer")
     */
    protected $ref;

    public function setRef($ref)
    {
        $this->ref = $ref;

        return $this;
    }

    public function getRef()
    {
        return $this->ref;
    }

    public function showRefInPanel()
    {
        return true;
    }

    public function showRefInForm()
    {
        return true;
    }

    /**
     * @ORM\Column(name="quantity", type="integer")
     */
    protected $quantity;

    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function showQuantityInPanel()
    {
        return true;
    }

    public function showQuantityInForm()
    {
        return true;
    }

    //// PRODUCT
    public function getProduct() {
        $product = $this->getCart()->getEntityManager()->getRepository('eDemyProductBundle:Product')->findOneById($this->getRef());

        return $product;
    }

}
