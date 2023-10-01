<?php
/**
 * Description of BarByTicks
 *
 * @author Yury Zalatarski <googoldie@gmail.com>
 */
class Anton_Entity_BarByTicks
{
    private $_topen = null;
    private $_tclose = null;
    private $_tmax = null;
    private $_tmin = null;
    
    private $_items = array();
    private $_prices = array();
    
    public function getOpen()
    {
        $item = reset($this->_items);
        return Anton_Entity_Item::loadFromItem($item);
    }

    public function getClose()
    {
        $item = end($this->_items);
        return Anton_Entity_Item::loadFromItem($item);
    }

    public function getMax()
    {
        $maxs = array_keys($this->_items, max($this->_prices));
        return Anton_Entity_Item::loadFromItem($this->_items[$maxs[0]]);
    }

    public function getMin()
    {
        $mins = array_keys($this->_items, min($this->_prices));
        return Anton_Entity_Item::loadFromItem($this->_items[$mins[0]]);
    }
    
    public function addItem($item)
    {
        $this->_items[$item['id']] = $item;
        $this->_prices[$item['id']] = $item['ask'];
    }
}
