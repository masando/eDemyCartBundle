<?php

namespace eDemy\CartBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class eDemyCartBundle extends Bundle
{
    public static function getBundleName($type = null)
    {
        if ($type == null) {

            return 'eDemyCartBundle';
        } else {
            if ($type == 'Simple') {

                return 'Cart';
            } else {
                if ($type == 'simple') {

                    return 'cart';
                }
            }
        }
    }

    public static function eDemyBundle() {

        return true;
    }
}
