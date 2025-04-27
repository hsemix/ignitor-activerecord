<?php

declare(strict_types=1);

/**
 * This file is part of the Igniter framework.
 *
 * @package    Igniter
 * @category   ActiveRecord
 * @author     Hamid Ssemitala <semix.hamidouh@gmail.com>
 * @license    https://opensource.org/licenses/MIT  MIT License
 * @link       https://igniter.com
 */
namespace Igniter\ActiveRecord\Support;

class Inflect
{
    /**
     * Pluralizes a word.
     *
     * @param string $word
     *
     * @return string
     */
    public static function pluralize(string $word): string
    {
        $plurals = [
            '/(quiz)$/i'               => '$1zes',
            '/^(ox)$/i'                => '$1en',
            '/([m|l])ouse$/i'          => '$1ice',
            '/(matr|vert|ind)ix|ex$/i' => '$1ices',      // matrices, vertices, indices
            '/(x|ch|ss|sh)$/i'         => '$1es',        // searches, shes, chess, shes
            '/([^aeiouy]|qu)y$/i'      => '$1ies',       // queries, answers
            '/(hive)$/i'               => '$1s',         // archive, hive
            '/(?:([^f])fe|([lr])f)$/i' => '$1$2ves',     // half, safe, wife
            '/sis$/i'                  => 'ses',         // basis, diagnosis
            '/([ti])um$/i'             => '$1a',         // datum, medium
            '/(p)erson$/i'             => '$1eople',     // person, salesperson
            '/(m)an$/i'                => '$1en',        // man, woman, spokesman
            '/(c)hild$/i'              => '$1hildren',   // child               
            '/(buffal|tomat)o$/i'      => '$1\2oes',     // buffalo, tomato
            '/(bu|campu)s$/i'          => '$1\2ses',     // bus, campus
            '/(alias|status|virus)$/i' => '$1es',        // alias, status, virus
            '/(octop|vir)us$/i'        => '$1i',         // octopus, virus
            '/(ax|cri|test)is$/i'      => '$1es',        // axis, crisis
            '/s$/i'                    => 's',           // no change (compatibility)
            '/$/'                      => 's',
        ];

        foreach ($plurals as $rule => $replacement) {
            if (preg_match($rule, $word)) {
                return preg_replace($rule, $replacement, $word);
            }
        }

        return $word;
    }

    /**
     * Singularizes a word.
     *
     * @param string $word
     *
     * @return string
     */
    public static function singularize(string $word): string
    {
        $singulars = [
            '/(quiz)zes$/i'               => '$1',
            '/(matr)ices$/i'              => '$1ix',
            '/(vert|ind)ices$/i'          => '$1ex',
            '/^(ox)en/i'                  => '$1',
            '/(alias|status|virus)es$/i'  => '$1',
            '/(octop|vir)i$/i'            => '$1us',
            '/(cris|ax|test)es$/i'        => '$1is',
            '/(shoe)s$/i'                 => '$1',
            '/(o)es$/i'                   => '$1',
            '/(bus)es$/i'                 => '$1',
            '/([m|l])ice$/i'              => '$1ouse',
            '/(x|ch|ss|sh)es$/i'          => '$1',
            '/(m)ovies$/i'                => '$1\2ovie',
            '/(s)eries$/i'                => '$1\2eries',
            '/([^aeiouy]|qu)ies$/i'       => '$1y',
            '/([lr])ves$/i'               => '$1f',
            '/(tive)s$/i'                 => '$1',
            '/(hive)s$/i'                 => '$1',
            '/([^f])ves$/i'               => '$1fe',
            '/(^analy)ses$/i'             => '$1sis',
            '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '$1$2sis',
            '/([ti])a$/i'                 => '$1um',
            '/(n)ews$/i'                  => '$1ews',
            '/s$/i'                       => '',
        ];        

        foreach ($singulars as $rule => $replacement) {
            if (preg_match($rule, $word)) {
                return preg_replace($rule, $replacement, $word);
            }
        }

        return $word;
    }
}
