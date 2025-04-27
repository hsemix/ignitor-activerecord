<?php

declare(strict_types=1);
/**
 * This file is part of the Yuga framework.
 *
 * @package    Igniter
 * @category   ActiveRecord
 * @author     Hamid Ssemitala <semix.hamidouh@gmail.com>
 * @license    https://opensource.org/licenses/MIT  MIT License
 * @link      https://igniter.com
 */
namespace Igniter\ActiveRecord\Support;

use ReflectionClass;
use InvalidArgumentException;

class FileLocator
{
    private $namespaceMap = [];
    private $defaultNamespace = 'global';

    public function __construct()
    {
        $this->traverseClasses();
    }

    public function getNamespaceFromClass($class)
    {
        $reflection = new ReflectionClass($class);
        return $reflection->getNameSpaceName() === '' ? $this->defaultNamespace : $reflection->getNameSpaceName();
    }

    public function traverseClasses()
    {
        $classes = get_declared_classes();
        foreach ($classes as $class) {
            $namespace = $this->getNamespaceFromClass($class);
            $this->namespaceMap[$namespace][] = $class;
        }
    }

    public function getClassesOfNamespace($namespace)
    {
        if (!isset($this->namespaceMap[$namespace]))
            throw new InvalidArgumentException('The Namespace '.$namespace.' doesnot exist');
        
        return $this->namespaceMap[$namespace];
    }

    public function getNameSpaces()
    {
        return array_keys($this->namespaceMap);
    }
}
