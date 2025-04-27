<?php
/**
 * This file is part of the Yuga framework.
 *
 * @package    Yuga
 * @category   ActiveRecord
 * @author     Your Name
 * @license    https://opensource.org/licenses/MIT  MIT License
 * @link       https://yuga.com
 */
namespace Igniter\ActiveRecord\Contracts;

use Closure;

interface Relation
{
    public function noConditions(Closure $callback);

    public function getResults();
}