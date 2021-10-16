<?php

namespace Neos\Fusion\Core;

use Neos\Utility\Arrays;

/**
 * Collection of methods for the Fusion Parser
 */
class FusionAst
{

    /**
     * Retrieves a value from a node in the object tree, specified by the object path array.
     *
     * @param array $objectPathArray The object path, specifying the node to retrieve the value of
     * @param array $objectTree The current (sub-) tree, used internally - don't specify!
     * @return mixed The value
     */
    public static function &getValueFromObjectTree(array $objectPathArray, &$objectTree)
    {
        if (count($objectPathArray) > 0) {
            $currentKey = array_shift($objectPathArray);
            if (is_numeric($currentKey)) {
                $currentKey = (int)$currentKey;
            }
            if (!isset($objectTree[$currentKey])) {
                $objectTree[$currentKey] = [];
            }
            $value = &self::getValueFromObjectTree($objectPathArray, $objectTree[$currentKey]);
        } else {
            $value = &$objectTree;
        }
        return $value;
    }

    /**
     * Reserved parse tree keys for internal usage.
     *
     * @var array
     */
    public static $reservedParseTreeKeys = ['__meta', '__prototypes', '__stopInheritanceChain', '__prototypeObjectName', '__prototypeChain', '__value', '__objectType', '__eelExpression'];

    static public function keyIsReservedParseTreeKey(string $pathKey)
    {
        if (substr($pathKey, 0, 2) === '__'
            && in_array($pathKey, self::$reservedParseTreeKeys, true)) {
            throw new Exception(sprintf('Reversed key "%s" used.', $pathKey), 1437065270);
        }
    }

    /**
     * Assigns a value to a node or a property in the object tree, specified by the object path array.
     *
     * @param array $objectPathArray The object path, specifying the node / property to set
     * @param mixed $value The value to assign, is a non-array type or an array with __eelExpression etc.
     * @param array $objectTree The current (sub-) tree
     * @return array The modified object tree
     */
    static public function setValueInObjectTree(array $objectPathArray, $value, array &$objectTree)
    {
        $currentKey = array_shift($objectPathArray);
        if (is_numeric($currentKey)) {
            $currentKey = (int)$currentKey;
        }

        if (empty($objectPathArray)) {
            // last part of the iteration, setting the final value
            if (isset($objectTree[$currentKey]) && $value === null) {
                unset($objectTree[$currentKey]);
            } elseif (isset($objectTree[$currentKey]) && is_array($objectTree[$currentKey])) {
                if (is_array($value)) {
                    $objectTree[$currentKey] = Arrays::arrayMergeRecursiveOverrule($objectTree[$currentKey], $value);
                } else {
                    $objectTree[$currentKey]['__value'] = $value;
                    $objectTree[$currentKey]['__eelExpression'] = null;
                    $objectTree[$currentKey]['__objectType'] = null;
                }
            } else {
                $objectTree[$currentKey] = $value;
            }
        } else {
            // we still need to traverse further down
            if (isset($objectTree[$currentKey]) && !is_array($objectTree[$currentKey])) {
                // the element one-level-down is already defined, but it is NOT an array. So we need to convert the simple type to __value
                $objectTree[$currentKey] = [
                    '__value' => $objectTree[$currentKey],
                    '__eelExpression' => null,
                    '__objectType' => null
                ];
            } elseif (!isset($objectTree[$currentKey])) {
                $objectTree[$currentKey] = [];
            }

            self::setValueInObjectTree($objectPathArray, $value, $objectTree[$currentKey]);
        }

        return $objectTree;
    }

    public static function objectPathIsPrototype($path): bool
    {
        return ($path[count($path) - 2] ?? null) === '__prototypes';
    }

    /**
     * Precalculate merged configuration for inherited prototypes.
     *
     * @return void
     * @throws Fusion\Exception
     */
    public static function buildPrototypeHierarchy(array &$objectTree): void
    {
        if (isset($objectTree['__prototypes']) === false) {
            return;
        }

        $prototypes = &$objectTree['__prototypes'];
        foreach ($prototypes as $prototypeName => $prototypeConfiguration) {
            $prototypeInheritanceHierarchy = [];
            $currentPrototypeName = $prototypeName;
            while (isset($prototypes[$currentPrototypeName]['__prototypeObjectName'])) {
                $currentPrototypeName = $prototypes[$currentPrototypeName]['__prototypeObjectName'];
                array_unshift($prototypeInheritanceHierarchy, $currentPrototypeName);
                if ($prototypeName === $currentPrototypeName) {
                    throw new Fusion\Exception(sprintf('Recursive inheritance found for prototype "%s". Prototype chain: %s', $prototypeName, implode(' < ', array_reverse($prototypeInheritanceHierarchy))), 1492801503);
                }
            }

            if (count($prototypeInheritanceHierarchy)) {
                // prototype chain from most *general* to most *specific* WITHOUT the current node type!
                $prototypes[$prototypeName]['__prototypeChain'] = $prototypeInheritanceHierarchy;
            }
        }
    }

}
