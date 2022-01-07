<?php

namespace Msgframework\Lib\Registry;

use Joomla\Utilities\ArrayHelper;
use stdClass;

class Registry implements \JsonSerializable, \ArrayAccess, \IteratorAggregate, \Countable
{
    /**
     * Registry Object
     *
     * @var    stdClass
     */
    protected stdClass $data;

    /**
     * Flag if the Registry data object has been initialized
     *
     * @var    boolean
     */
    protected bool $initialized = false;

    /**
     * Path separator
     *
     * @var    string
     */
    public string $separator = '.';

    /**
     * Constructor
     *
     * @param   mixed  $data  The data to bind to the new Registry object.
     *
     */
    public function __construct($data = null)
    {
        // Instantiate the internal data object.
        $this->data = new stdClass;

        // Optionally load supplied data.
        if ($data instanceof self)
        {
            $this->merge($data);
        }
        elseif (\is_array($data) || \is_object($data))
        {
            $this->bindData($this->data, $data);
        }
        elseif (!empty($data) && \is_string($data))
        {
            $this->loadString($data);
        }
    }

    /**
     * Magic function to clone the registry object.
     *
     * @return  void
     *
     */
    public function __clone()
    {
        $this->data = unserialize(serialize($this->data));
    }

    /**
     * Magic function to render this object as a string using default args of toString method.
     *
     * @return  string
     *
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * Get a namespace in a given string format
     *
     * @param   array   $options  Parameters used by the formatter, see formatters for more info
     *
     * @return  string   Namespace in string format
     *
     */
    public function toString(array $options = []): string
    {
        $bitMask = $options['bitmask'] ?? 0;
        $depth   = $options['depth'] ?? 512;

        return json_encode($this->data, $bitMask, $depth);
    }

    /**
     * Count elements of the data object
     *
     * @return  integer  The custom count as an integer.
     *
     */
    public function count(): int
    {
        return \count(get_object_vars($this->data));
    }

    /**
     * Implementation for the JsonSerializable interface.
     * Allows us to pass Registry objects to json_encode.
     *
     * @return  object
     *
     * @note    The interface is only present in PHP 5.4 and up.
     */
    public function jsonSerialize()
    {
        return $this->data;
    }

    /**
     * Sets a default value if not already assigned.
     *
     * @param   string  $key      The name of the parameter.
     * @param   mixed   $default  An optional value for the parameter.
     *
     * @return  mixed  The value set, or the default if the value was not previously set (or null).
     *
     */
    public function def(string $key, $default = '')
    {
        $value = $this->get($key, $default);
        $this->set($key, $value);

        return $value;
    }

    /**
     * Check if a registry path exists.
     *
     * @param   string  $path  Registry path
     *
     * @return  boolean
     *
     */
    public function exists(string $path): bool
    {
        // Return default value if path is empty
        if (empty($path))
        {
            return false;
        }

        // Explode the registry path into an array
        $nodes = explode($this->separator, $path);

        // Initialize the current node to be the registry root.
        $node  = $this->data;
        $found = false;

        // Traverse the registry to find the correct node for the result.
        foreach ($nodes as $n)
        {
            if (\is_array($node) && isset($node[$n]))
            {
                $node  = $node[$n];
                $found = true;

                continue;
            }

            if (!isset($node->$n))
            {
                return false;
            }

            $node  = $node->$n;
            $found = true;
        }

        return $found;
    }

    /**
     * Get a registry value.
     *
     * @param   string  $path     Registry path
     * @param   mixed   $default  Optional default value, returned if the internal value is null.
     *
     * @return  mixed  Value of entry or null
     *
     */
    public function get(string $path, $default = null)
    {
        // Return default value if path is empty
        if (empty($path))
        {
            return $default;
        }

        if (!strpos($path, $this->separator))
        {
            return (isset($this->data->$path) && $this->data->$path !== null && $this->data->$path !== '') ? $this->data->$path : $default;
        }

        // Explode the registry path into an array
        $nodes = explode($this->separator, trim($path));

        // Initialize the current node to be the registry root.
        $node  = $this->data;
        $found = false;

        // Traverse the registry to find the correct node for the result.
        foreach ($nodes as $n)
        {
            if (\is_array($node) && isset($node[$n]))
            {
                $node  = $node[$n];
                $found = true;

                continue;
            }

            if (!isset($node->$n))
            {
                return $default;
            }

            $node  = $node->$n;
            $found = true;
        }

        if (!$found || $node === null || $node === '')
        {
            return $default;
        }

        return $node;
    }

    /**
     * Gets this object represented as an ArrayIterator.
     *
     * This allows the data properties to be accessed via a foreach statement.
     *
     * @return  \ArrayIterator  This object represented as an ArrayIterator.
     *
     * @see     IteratorAggregate::getIterator()
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * Load an associative array of values into the default namespace
     *
     * @param   array    $array      Associative array of value to load
     * @param   boolean  $flattened  Load from a one-dimensional array
     * @param   string|null   $separator  The key separator
     *
     * @return  self
     *
     */
    public function loadArray(array $array, bool $flattened = false, ?string $separator = null): self
    {
        if (!$flattened)
        {
            $this->bindData($this->data, $array);

            return $this;
        }

        if (empty($separator))
        {
            $separator = $this->separator;
        }

        foreach ($array as $k => $v)
        {
            $this->set($k, $v, $separator);
        }

        return $this;
    }

    /**
     * Load the public variables of the object into the default namespace.
     *
     * @param   object  $object  The object holding the publics to load
     *
     * @return  self
     *
     */
    public function loadObject(object $object): self
    {
        $this->bindData($this->data, $object);

        return $this;
    }

    /**
     * Load json data from string
     *
     * @param string $data
     * @param array $options
     *
     * @return $this
     *
     */
    public function loadString(string $data, array $options = array()): self
    {
        $obj = json_decode($data);

        // If the data object has not yet been initialized, direct assign the object
        if (!$this->initialized)
        {
            $this->data        = $obj;
            $this->initialized = true;

            return $this;
        }

        $this->loadObject($obj);

        return $this;
    }

    /**
     * Merge a Registry object into this one
     *
     * @param   Registry  $source     Source Registry object to merge.
     * @param   boolean   $recursive  True to support recursive merge the children values.
     *
     * @return  self
     *
     */
    public function merge(Registry $source, bool $recursive = false): self
    {
        $this->bindData($this->data, $source->toArray(), $recursive, false);

        return $this;
    }

    /**
     * Method to extract a sub-registry from path
     *
     * @param   string  $path  Registry path
     *
     * @return  self
     *
     */
    public function extract(string $path): self
    {
        $data = $this->get($path);

        return new Registry($data);
    }

    /**
     * Checks whether an offset exists in the iterator.
     *
     * @param   mixed  $offset  The array offset.
     *
     * @return  boolean  True if the offset exists, false otherwise.
     *
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return $this->exists($offset);
    }

    /**
     * Gets an offset in the iterator.
     *
     * @param   mixed  $offset  The array offset.
     *
     * @return  mixed  The array value if it exists, null otherwise.
     *
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Sets an offset in the iterator.
     *
     * @param   mixed  $offset  The array offset.
     * @param   mixed  $value   The array value.
     *
     * @return  void
     *
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * Unsets an offset in the iterator.
     *
     * @param   mixed  $offset  The array offset.
     *
     * @return  void
     *
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * @param $node
     * @param array $nodes
     * @param bool $last
     * @param int $iterator
     * @return mixed
     */
    private function getNode(&$node, array $nodes, bool $last = false, int &$iterator = 0)
    {

        for ($iterator = 0, $n = \count($nodes) - 1; $iterator < $n; $iterator++)
        {
            if (\is_object($node))
            {
                if (!isset($node->{$nodes[$iterator]}) && ($iterator !== $n))
                {
                    $node->{$nodes[$iterator]} = new \stdClass;
                }

                // Pass the child as pointer in case it is an array
                $node = &$node->{$nodes[$iterator]};

                continue;
            }

            if (\is_array($node))
            {
                if (($iterator !== $n) && !isset($node[$nodes[$iterator]]))
                {
                    $node[$nodes[$iterator]] = new \stdClass;
                }

                // Pass the child as pointer in case it is an array
                $node = &$node[$nodes[$iterator]];
            }
        }

        return $node;
    }

    /**
     * Set a registry value.
     *
     * @param   string  $path       Registry Path
     * @param   mixed   $value      Value of entry
     * @param   string|null  $separator  The key separator
     *
     * @return  void
     *
     */
    public function set(string $path, $value, ?string $separator = null): void
    {
        if (empty($separator))
        {
            $separator = $this->separator;
        }

        $nodes = array_values(array_filter(explode($separator, $path), 'strlen'));

        // Initialize the current node to be the registry root.
        $node = $this->data;

        // Traverse the registry to find the correct node for the result.
        for ($i = 0, $n = \count($nodes) - 1; $i < $n; $i++)
        {
            if (\is_object($node))
            {
                if (!isset($node->{$nodes[$i]}) && ($i !== $n))
                {
                    $node->{$nodes[$i]} = new \stdClass;
                }

                // Pass the child as pointer in case it is an object
                $node = &$node->{$nodes[$i]};

                continue;
            }

            if (\is_array($node))
            {
                if (($i !== $n) && !isset($node[$nodes[$i]]))
                {
                    $node[$nodes[$i]] = new \stdClass;
                }

                // Pass the child as pointer in case it is an array
                $node = &$node[$nodes[$i]];
            }
        }

        if(\is_object($node)) {
            $node->{$nodes[$i]} = $value;
        }
        if(\is_array($node)) {
            $node[$nodes[$i]] = $value;
        }
    }

    /**
     * Append value to a path in registry
     *
     * @param   string  $path   Parent registry Path
     * @param   mixed   $value  Value of entry
     *
     * @return  void
     *
     */
    public function append(string $path, $value): void
    {
        $nodes = array_values(array_filter(explode($this->separator, $path), 'strlen'));

        if ($nodes)
        {
            // Initialize the current node to be the registry root.
            $node = $this->data;

            // Traverse the registry to find the correct node for the result.
            for ($i = 0, $n = \count($nodes) - 1; $i <= $n; $i++)
            {
                if (\is_object($node))
                {
                    if (!isset($node->{$nodes[$i]}) && ($i !== $n))
                    {
                        $node->{$nodes[$i]} = new \stdClass;
                    }

                    // Pass the child as pointer in case it is an array
                    $node = &$node->{$nodes[$i]};
                }
                elseif (\is_array($node))
                {
                    if (($i !== $n) && !isset($node[$nodes[$i]]))
                    {
                        $node[$nodes[$i]] = new \stdClass;
                    }

                    // Pass the child as pointer in case it is an array
                    $node = &$node[$nodes[$i]];
                }
            }

            if (!\is_array($node))
            {
                // Convert the node to array to make append possible
                $node = get_object_vars($node);
            }

            $node[] = $value;
        }
    }

    /**
     * Delete a registry value
     *
     * @param   string  $path  Registry Path
     *
     * @return  void
     *
     */
    public function remove(string $path): void
    {
        // Cheap optimisation to direct remove the node if there is no separator
        if (!strpos($path, $this->separator))
        {
            unset($this->data->$path);
            return;
        }

        $nodes = array_values(array_filter(explode($this->separator, $path), 'strlen'));

        if (!$nodes)
        {
            return;
        }

        // Initialize the current node to be the registry root.
        $node   = $this->data;
        $parent = null;

        // Traverse the registry to find the correct node for the result.
        for ($i = 0, $n = \count($nodes) - 1; $i < $n; $i++)
        {
            if (\is_object($node))
            {
                if (!isset($node->{$nodes[$i]}))
                {
                    continue;
                }

                $parent = &$node;
                $node   = $node->{$nodes[$i]};

                continue;
            }

            if (\is_array($node))
            {
                if (!isset($node[$nodes[$i]]))
                {
                    continue;
                }

                $parent = &$node;
                $node   = $node[$nodes[$i]];

                continue;
            }
        }

        // Get the old value if exists so we can return it
        if(\is_object($node)) {
            unset($parent->{$nodes[$i]});
        }
        if(\is_array($node)) {
            unset($parent[$nodes[$i]]);
        }
    }

    /**
     * Transforms a namespace to an array
     *
     * @return  array  An associative array holding the namespace data
     *
     */
    public function toArray(): array
    {
        return (array) $this->asArray($this->data);
    }

    /**
     * Transforms a namespace to an object
     *
     * @return  object   An an object holding the namespace data
     *
     */
    public function toObject(): object
    {
        return $this->data;
    }

    /**
     * Method to recursively bind data to a parent object.
     *
     * @param   object   $parent     The parent object on which to attach the data values.
     * @param   mixed    $data       An array or object of data to bind to the parent object.
     * @param   boolean  $recursive  True to support recursive bindData.
     * @param   boolean  $allowNull  True to allow null values.
     *
     * @return  void
     *
     */
    protected function bindData(object $parent, $data, bool $recursive = true, bool $allowNull = true)
    {
        // The data object is now initialized
        $this->initialized = true;

        // Ensure the input data is an array.
        $data = \is_object($data) ? get_object_vars($data) : (array) $data;

        foreach ($data as $k => $v)
        {
            if (!$allowNull && !(($v !== null) && ($v !== '')))
            {
                continue;
            }

            if ($recursive && ((\is_array($v) && ArrayHelper::isAssociative($v)) || \is_object($v)))
            {
                if (!isset($parent->$k))
                {
                    $parent->$k = new stdClass;
                }

                $this->bindData($parent->$k, $v);

                continue;
            }

            $parent->$k = $v;
        }
    }

    /**
     * Method to recursively convert an object of data to an array.
     *
     * @param   object  $data  An object of data to return as an array.
     *
     * @return  array  Array representation of the input object.
     *
     */
    protected function asArray(object $data): array
    {
        $array = [];

        if (\is_object($data))
        {
            $data = get_object_vars($data);
        }

        foreach ($data as $k => $v)
        {
            if (\is_object($v) || \is_array($v))
            {
                $array[$k] = $this->asArray($v);

                continue;
            }

            $array[$k] = $v;
        }

        return $array;
    }

    /**
     * Dump to one dimension array.
     *
     * @param   string|null  $separator  The key separator.
     *
     * @return  array
     *
     */
    public function flatten(?string $separator = null): array
    {
        $array = [];

        if (empty($separator))
        {
            $separator = $this->separator;
        }

        $this->toFlatten($separator, $this->data, $array);

        return $array;
    }

    /**
     * Method to recursively convert data to one dimension array.
     *
     * @param   string    $separator  The key separator.
     * @param   mixed     $data       Data source of this scope.
     * @param   array     $array      The result array, it is passed by reference.
     * @param   string    $prefix     Last level key prefix.
     *
     * @return  void
     *
     */
    protected function toFlatten(string $separator, $data = null, array &$array = [], string $prefix = ''): void
    {
        $data = (array) $data;

        if (empty($separator))
        {
            $separator = $this->separator;
        }

        foreach ($data as $k => $v)
        {
            $key = $prefix ? $prefix . $separator . $k : $k;

            if (\is_object($v) || \is_array($v))
            {
                $this->toFlatten($separator, $v, $array, $key);

                continue;
            }

            $array[$key] = $v;
        }
    }
}