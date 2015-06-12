<?php

namespace eDemy\CartBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use JMS\Serializer\Annotation as SER;

/**
 * @SER\ExclusionPolicy("all")
 */
class CartItem
{
    protected $items;
    
    /**
     * @Type("integer")
     */
    protected $product_id;
    
    public function getProductId() {
        return $this->product_id;
    }

    public function setProductId($product_id) {
        $this->product_id = $product_id;
    }

    /***************************************/

    /**
     * @Type("integer")
     */
    protected $quantity;

    public function getQuantity() {
        return $this->quantity;
    }

    public function setQuantity($q) {
        $this->quantity = $q;
    }

    /***************************************/

    public function addObject($object)
    {
        $this->items[] = $object;
    }
}
