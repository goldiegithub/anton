<?php
/**
 * Description of Anton_Entity_Item_Collection
 *
 * @author Yury Zalatarski <googoldie@gmail.com>
 */
class Anton_Entity_Item_Collection extends Anton_Entity_Collection
{
    protected $_items = array();
    
    public function loadFromArray($items)
    {
        $this->clear();
        foreach ($items as $item) {
            $this->addItem($item);
        }
        return $this;
    }
    
    public function clear()
    {
        $this->_items = $this->_entities = $this->_index = array();
        $this->_count = 0;
    }
    
    public function index(Anton_Entity_Item $entity)
    {
        return array_search($entity->item_id, $this->_index);
    }
    
    /**
     * 
     * @param int $index
     * @param int $itemID
     * @return Anton_Entity_Item
     */
    
    public function get($index = null, $itemID = null)
    {
        $index = $index === null ? -1 : $index;
        $offset = $itemID === null ? ($index < 0 ? $this->_count : 0) : array_search($itemID, $this->_index);
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
        
        $this->_entities[$key] = Anton_Entity_Item::loadFromItem($this->_items[$key]);
        return $this->_entities[$key];
    }
    
    public function addItem($item)
    {
        if (!isset($this->_items[$item['id']])) {
            $this->_items[$item['id']] = $item;
            $this->_entities[$item['id']] = false;
            $this->_index[] = $item['id'];
            $this->_count++;
        }
        
        return $this;
    }
    
    public function shift()
    {
        if ($this->_count > 0) {
            unset($this->_items[$this->_index[0]]);
            unset($this->_entities[$this->_index[0]]);
            array_shift($this->_index); // array_shift очень медленный
            $this->_count--;
        }
    }
    
    public function offsetExists($offset)
    {
        return isset($this->_items[$offset]);
    }
    
    public function offsetUnset($offset)
    {
        if (isset($this->_items[$offset])) {
            $indexKey = array_search($offset, $this->_index); // array_search очень медленный
            unset($this->_index[$indexKey]);
            $this->_index = array_values($this->_index);
            unset($this->_items[$offset]);
            unset($this->_entities[$offset]);
            $this->_count--;
        }
    }
    
    public function offsetGet($offset)
    {
        return isset($this->_items[$offset]) ? $this->_items[$offset] : null;
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
        reset($this->_items);
        reset($this->_index);
    }
    
    public function current()
    {
        $key = key($this->_items);
        return $this->getByKey($key);
    }
    
    public function key() 
    {
        return key($this->_items);
    }
    
    public function next() 
    {
        next($this->_items);
        next($this->_entities);
        next($this->_index);
        $key = key($this->_items);
        return is_null($key) ? false : $this->getByKey($key);
    }
    
    public function valid()
    {
        $key = key($this->_items);
        return $key !== null;
    }
}
