<?php
/**
 * Description of Anton_Entity_Abstract
 *
 * @author Yury Zalatarski <googoldie@gmail.com>
 */
abstract class Anton_Entity_Abstract implements JsonSerializable
{
    protected $_data;
    
    abstract public function setData($data);
    
    public function __get($name)
    {
        return $this->_data[$name];
    }
    
    public function getData()
    {
        return $this->_data;
    }
    
    public function jsonSerialize() 
    {
        return $this->getData();
    }
    
    abstract public function getRegisteredTime();
}
