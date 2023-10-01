<?php
/**
 * Description of Anton_Entity_Collection
 *
 * @author Yury Zalatarski <googoldie@gmail.com>
 */
class Anton_Entity_Collection implements ArrayAccess, Countable, Iterator
{
    protected $_entities = array();
    protected $_index = array();
    
    protected $_count = 0;
    
    public function clear()
    {
        $this->_entities = $this->_index = array();
        $this->_count = 0;
    }
    
    public function get($index = null, $time = null)
    {
        $index = $index === null ? -1 : $index;
        $offset = $time === null ? ($index < 0 ? $this->_count : 0) : array_search($time, $this->_index);
        if (!isset($this->_index[$offset + $index])) {
            return null;
        }
        
        return $this->getByKey($this->_index[$offset + $index]);
    }
    
    public function getByKey($key)
    {
        if ($this->_entities[$key]) {
            return $this->_entities[$key];
        }
    }
    
    public function addItem(Anton_Entity_Abstract $item)
    {
        $this->_entities[$item->getRegisteredTime()] = $item;
        $this->_index[] = $item->getRegisteredTime();
        $this->_count++;
        
        return $this;
    }
    
    public function shift()
    {
        if ($this->_count > 0) {
            unset($this->_entities[$this->_index[0]]);
            array_shift($this->_index); // array_shift очень медленный
            $this->_count--;
        }
    }
    
    public function offsetExists($offset)
    {
        return isset($this->_entities[$offset]);
    }
    
    public function offsetUnset($offset)
    {
        if (isset($this->_entities[$offset])) {
            unset($this->_entities[$offset]);
            $indexKey = array_search($offset, $this->_index);
            unset($this->_index[$indexKey]);
            $this->_index = array_values($this->_index);
            $this->_count--;
        }
    }
    
    public function offsetGet($offset)
    {
        return isset($this->_entities[$offset]) ? $this->_entities[$offset] : null;
    }
    
    public function offsetSet($offset, $value)
    {
        throw Zend_Registry::get('env')->get('exception_generator')->load(__CLASS__, 'offsetSet is denied');
    }
    
    public function count()
    {
        return $this->_count;
    }
    
    public function rewind()
    {
        reset($this->_entities);
        reset($this->_index);
    }
    
    public function current()
    {
        return current($this->_entities);
    }
    
    public function key() 
    {
        return key($this->_entities);
    }
    
    public function next() 
    {
        next($this->_index);
        return next($this->_entities);
    }
    
    public function valid()
    {
        $key = key($this->_entities);
        return $key !== null;
    }
}
