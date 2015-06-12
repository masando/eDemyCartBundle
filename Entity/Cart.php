<?php

namespace eDemy\CartBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use JMS\Serializer\Annotation\Type;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use eDemy\ProductBundle\Entity\Product;

class Cart
{
    /**
     * Type("ArrayCollection<eDemy\CartBundle\Entity\CartItem>")
     */
    protected $items;

    private $em;
    private $serializer;
    private $session;
    private $discount;
    
    public function __construct(EntityManager $em, $serializer, SessionInterface $session)
    {
        $this->em = $em;
        $this->serializer = $serializer;
        $this->session = $session;
        $this->data = array();
        //$this->data['items'] = new ArrayCollection();
        //$this->data->items = $this->session->get('items', null);
        
        //if($this->items != null) {
            //$this->items = new ArrayCollection($this->serializer->deserialize($this->items, 'ArrayCollection<eDemy\CartBundle\Entity\CartItem>', 'xml'));
            $this->items = new ArrayCollection();
        //} else {
        //    $this->items = new ArrayCollection();
        //}

        //$this->discount = false;
        //$this->data = $this->session->get('data', null);
        //if($this->data != null) {
            //$data = new ArrayCollection($this->serializer->deserialize($this->data, 'ArrayCollection<eDemy\CartBundle\Entity\CartItem>', 'xml'));
            //$data = new ArrayCollection();
            //die(var_dump($data));
            //$this->items = $data['items'];
            //$this->discount = $data['discount'];
        //}
        
    }

    public function addObject($item, $name = null, $single = false)
    {
        if($item) {
            $data = $this->deserialize($name, get_class($item), true);
            //die(var_dump($data));
            if($data) {
                $this->data[$name] = $data;
                //die($data);
            } else {
                //die('no data');
            }
        }
        if($name == null) {
            $name = $item->getEntityName();
        }
        if(gettype($item) == 'integer') {
            //$item = strval($item);
            //die(var_dump($item));
            if(!array_key_exists($name, $this->data)) {
                //die('a');
                $this->data[$name][$item] = 1;
            } else if(!array_key_exists($item, $this->data[$name])) {
                //die('b');
                $this->data[$name][$item] = 1;
            } else {
                //die('c');
                $this->data[$name][$item] += 1;
            }
            $this->serialize($name);
        } else {
            if($single) {
                if($item == null) {
                    $this->session->remove('data'.$name);
                    //$this->data[$name] = null;
                    return;
                } else {
                    $this->data[$name] = $item;
                }
            } else {
                $this->data[$name][] = $item;
            }
            $this->serialize($name, 'xml', false);
        }
        //if($this->get('kernel')->getEnvironment() == 'dev') {
        //    die(var_dump($this->data[$name]));
        //}
        

        return $this;
    }

    public function serialize($name, $format = 'xml', $encode = true){
        $data = $this->data[$name];
        //die(var_dump($data));
        if($encode) {
            $data = json_encode($data);
        }
        //die(var_dump($data));
        $data = $this->serializer->serialize($data, $format);
        //die(var_dump($data));
        
        /*
        $data = $this->serializer->serialize(array(
            'items' => $this->items,
            //'discount' => $this->discount,
        ), 'xml');
        * */
        $this->session->set('data'.$name, $data);
        //die(var_dump($this->session));
        return $data;
    }

    public function deserialize($name, $class = null, $single = false, $encode = false){
        $data = $this->session->get('data'.$name, null);
        //die(var_dump($data));
        //die(var_dump($this->serializer->deserialize($data, $class . '<' . $class . '>', 'xml')));
        if($data) {
            if($class) {
                if($single) {
                    return $this->serializer->deserialize($data, $class . '<' . $class . '>', 'xml');
                } else {
                    return $this->serializer->deserialize($data, 'ArrayCollection<' . $class . '>', 'xml');
                }
            } else {
                //die(var_dump($data));
                $data = $this->serializer->deserialize($data, 'string', 'xml');
                //die(var_dump($data));
                if($encode) {
                    $data = json_decode($data, true);
                }
                //die(var_dump($data));
                return $data;
                return $this->serializer->deserialize($data, 'array', 'xml');
            }
        }
        return false;
    }

    public function addItem(\eDemy\CartBundle\Entity\CartItem $item)
    {
        $this->items[] = $item;
        $this->serialize($item->getEntityName());
        return $this;
    }

    public function removeItem(\eDemy\CartBundle\Entity\CartItem $item)
    {
        $this->items->removeElement($item);
    }

    public function getItems()
    {
        return $this->items;
    }

    public function addProduct(Product $product)
    {
        $found = false;
        //die(var_dump($this->items));
        foreach($this->getItems() as $item) {
            if($item->getProductId() == $product->getId()){
                $item->setQuantity($item->getQuantity() + 1);
                $found = true;
            }
        }
        if(!$found){
            $item = new CartItem();
            $item->setProductId($product->getId());
            $item->setQuantity(1);
            $this->addItem($item);
        }
        $this->serialize();
    }

    public function removeProduct(Product $product)
    {
        foreach($this->getItems() as $item) {
            if($item->getProductId() == $product->getId()){
                if($item->getQuantity() > 1) $item->setQuantity($item->getQuantity() - 1);
                else $this->removeItem($item);
            }
        }
        $this->serialize();
    }

    public function addDiscount()
    {
        $this->discount = true;
        $this->serialize();
    }
    public function hasDiscount()
    {
        return $this->discount;
    }

    public function isEmpty($name = 'cesta') {
        //die(var_dump($this->getProducts($name)));
        return ($this->getProducts()->count() == 0);
    }
    
    public function isNotEmpty() {
        return !$this->isEmpty();
    }

    public function getTotal($name) {
        $total = 0;
        $data = $this->deserialize($name);
        if($data) {
            foreach($data as $id => $q) {
                $total += $q * $this->getItemPrice($id);
            }
        }
        /*
        foreach($this->getItems() as $item) {
            $total += $item->getQuantity() * $this->getItemPrice($item);
        }
        * */
        return $total;
    }
    
    public function getQuantity($name, $id) {
        $data = $this->deserialize($name);
        if($data) {
            //die(var_dump($data[$id]));
            return $data[$id];
        }
        /*
        $q = 0;
        if(defined($this->items)) {
            foreach($this->getItems() as $item) {
                $q += $item->getQuantity();
            }
        }
        return $q;
        * */
    }

    public function getItemPrice($id) {
        $product = $this->em->getRepository('eDemyProductBundle:Product')->find($id);
        if($product) {
            return $product->getPrice();
        }
    }
    
    public function getProduct(CartItem $item) {
        $product = $this->em->getRepository('eDemyProductBundle:Product')->find($item->getProductId());
        
        return $product;
    }
    
    public function clear() {
        $this->data->clear();
        $this->serialize();
    }
    
    public function getProducts($name = 'cesta') {
        $cesta = $this->deserialize($name);
        //die(var_dump($cesta));
        $products = new ArrayCollection();
        foreach($cesta as $id => $quantity) {
            $product = $this->em->getRepository('eDemyProductBundle:Product')->find($id);
            if($product) {
                $products->add($product);
            }
        }
        //die(var_dump($products));

        return $products;
    }
}
