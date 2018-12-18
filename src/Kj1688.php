<?php
/**
 * Created by PhpStorm.
 * User: dai
 * Date: 2018/12/13
 * Time: 15:58
 */
namespace Vin7ent\Kj1688;

use Illuminate\Support\Facades\Facade;

class Kj1688 extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'alikj';
    }
}