<?php

namespace eDemy\CartBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use JMS\Serializer\Annotation\Type;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use eDemy\ProductBundle\Entity\Product;
use eDemy\MainBundle\Entity\BaseEntity;

/**
 * @ORM\Entity(repositoryClass="eDemy\CartBundle\Entity\CartRepository")
 * @ORM\Table()
 */
class Cart extends BaseEntity
{
    public function __construct($em = null, $session)
    {
        parent::__construct($em);
        $this->session = $session;
        $this->items = new ArrayCollection();
    }

    /**
     * @ORM\Column(name="session", type="text")
     */
    protected $session;

    public function setSession($session)
    {
        $this->session = $session;

        return $this;
    }

    public function getSession()
    {
        return $this->session;
    }

    public function showSessionInPanel()
    {
        return true;
    }

    public function showSessionInForm()
    {
        return true;
    }

    /**
     * @ORM\OneToMany(targetEntity="CartItem", mappedBy="cart", cascade={"persist","remove"})
     */
    protected $items;


    public function getItems()
    {
        return $this->items;
    }

    public function addItem(CartItem $item)
    {
        $found = false;
        $item->setCart($this);
        foreach ($this->items as $i) {
            if ($i->getRef() == $item->getRef()) {
                $found = true;
                $i->setQuantity($i->setQuantity() + 1);
            }
        }
        if (!$found) {
            $this->items->add($item);
        }
    }

    public function removeItem(CartItem $item)
    {
        $this->items->removeElement($item);
        $this->getEntityManager()->remove($item);
        $this->getEntityManager()->flush();
    }

    public function addProduct(Product $product)
    {
        $found = false;
        foreach ($this->items as $item) {
            if ($item->getRef() == $product->getId()) {
                $found = true;
                $item->setQuantity($item->getQuantity() + 1);
            }
        }
//        die(var_dump($this->items));
        if (!$found) {
            $item = new CartItem();
            $item->setCart($this);
            $item->setRef($product->getId());
            $item->setQuantity(1);

            $this->items->add($item);
        }
    }

    public function removeProduct(Product $product)
    {
        $found = false;
        foreach ($this->items as $item) {
            if ($item->getRef() == $product->getId()) {
                $found = true;
                if($item->getQuantity() > 1) {
                    $item->setQuantity($item->getQuantity() - 1);
                } else {
                    $this->removeItem($item);
                }

            }
        }
//        die(var_dump($this->items));
    }

    public function getTotal()
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $total += ($item->getQuantity() * $item->getProduct()->getPrice());
        }

        return $total;
    }

    public function emptyCart($em)
    {
        $this->setEntityManager($em);
        //die(var_dump($em));
        foreach ($this->items as $item) {
            $this->removeItem($item);
        }
    }

}
