<?php

declare(strict_types=1);
/**
 * This file is part of the Igniter framework.
 *
 * @package    Igniter
 * @category   ActiveRecord
 * @author     Your Name
 * @license    https://opensource.org/licenses/MIT  MIT License
 * @link       https://igniter.com
 */
namespace Igniter\ActiveRecord\Support;

class Str
{
    /**
     * Convert a string to camelCase.
     *
     * @param string $value
     *
     * @return string
     */
    public static function camelize(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }

    /**
     * Convert a string to snake_case.
     *
     * @param string $value
     *
     * @return string
     */    
    public static function snakeCase(string $value): string
    {
        return preg_replace_callback('/(^|[a-z])([A-Z])/', function ($matches) {
            return strtolower(strlen($matches[1]) ? $matches[1] . '_' . $matches[2] : $matches[2]);
        },
        $value);
    }    
}   
