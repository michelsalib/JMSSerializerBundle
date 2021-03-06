<?php

/*
 * Copyright 2011 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\SerializerBundle\Serializer;

use JMS\SerializerBundle\Exception\RuntimeException;
use JMS\SerializerBundle\Serializer\Construction\ObjectConstructorInterface;
use JMS\SerializerBundle\Serializer\Naming\PropertyNamingStrategyInterface;
use JMS\SerializerBundle\Metadata\PropertyMetadata;
use JMS\SerializerBundle\Metadata\ClassMetadata;

/**
 * Generic Deserialization Visitor.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class GenericDeserializationVisitor extends AbstractVisitor
{
    private $navigator;
    private $result;
    private $objectStack;
    private $currentObject;

    public function setNavigator(GraphNavigator $navigator)
    {
        $this->navigator = $navigator;
        $this->result = null;
        $this->objectStack = new \SplStack;
    }

    public function getNavigator()
    {
        return $this->navigator;
    }

    public function prepare($data)
    {
        return $this->decode($data);
    }

    public function visitString($data, array $type)
    {
        $data = (string) $data;

        if (null === $this->result) {
            $this->result = $data;
        }

        return $data;
    }

    public function visitBoolean($data, array $type)
    {
        $data = (Boolean) $data;

        if (null === $this->result) {
            $this->result = $data;
        }

        return $data;
    }

    public function visitInteger($data, array $type)
    {
        $data = (integer) $data;

        if (null === $this->result) {
            $this->result = $data;
        }

        return $data;
    }

    public function visitDouble($data, array $type)
    {
        $data = (double) $data;

        if (null === $this->result) {
            $this->result = $data;
        }

        return $data;
    }

    public function visitArray($data, array $type)
    {
        if ( ! is_array($data)) {
            throw new RuntimeException(sprintf('Expected array, but got %s: %s', gettype($data), json_encode($data)));
        }

        // If no further parameters were given, keys/values are just passed as is.
        if ( ! $type['params']) {
            if (null === $this->result) {
                $this->result = $data;
            }

            return $data;
        }

        switch (count($type['params'])) {
            case 1: // Array is a list.
                $listType = $type['params'][0];

                $result = array();
                if (null === $this->result) {
                    $this->result = &$result;
                }

                foreach ($data as $v) {
                    $result[] = $this->navigator->accept($v, $listType, $this);
                }

                return $result;

            case 2: // Array is a map.
                list($keyType, $entryType) = $type['params'];

                $result = array();
                if (null === $this->result) {
                    $this->result = &$result;
                }

                foreach ($data as $k => $v) {
                    $result[$this->navigator->accept($k, $keyType, $this)] = $this->navigator->accept($v, $entryType, $this);
                }

                return $result;

            default:
                throw new \RuntimeException(sprintf('Array type cannot have more than 2 parameters, but got %s.', json_encode($type['params'])));
        }
    }

    public function startVisitingObject(ClassMetadata $metadata, $object, array $type)
    {
        $this->setCurrentObject($object);

        if (null === $this->result) {
            $this->result = $this->currentObject;
        }
    }

    public function visitProperty(PropertyMetadata $metadata, $data)
    {
        $name = $this->namingStrategy->translateName($metadata);

        if ( ! isset($data[$name])) {
            return;
        }

        if ( ! $metadata->type) {
            throw new RuntimeException(sprintf('You must define a type for %s::$%s.', $metadata->reflection->class, $metadata->name));
        }

        $v = $this->navigator->accept($data[$name], $metadata->type, $this);
        if (null === $v) {
            return;
        }

        if (null === $metadata->setter) {
            $metadata->reflection->setValue($this->currentObject, $v);

            return;
        }

        $this->currentObject->{$metadata->setter}($v);
    }

    public function endVisitingObject(ClassMetadata $metadata, $data, array $type)
    {
        $obj = $this->currentObject;
        $this->revertCurrentObject();

        return $obj;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function setCurrentObject($object)
    {
        $this->objectStack->push($this->currentObject);
        $this->currentObject = $object;
    }

    public function getCurrentObject()
    {
        return $this->currentObject;
    }

    public function revertCurrentObject()
    {
        return $this->currentObject = $this->objectStack->pop();
    }

    abstract protected function decode($str);
}
