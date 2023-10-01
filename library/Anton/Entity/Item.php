<?php
/**
 * Description of Item
 *
 * @author Yury Zalatarski <googoldie@gmail.com>
 */
abstract class Anton_Entity_Item extends Anton_Entity_Abstract
{
    protected $_type;
    
    public static function loadFromItem($item)
    {
        $class = 'Anton_Entity_Item_' . $item['itype'];
        $entity = new $class;
        $entity->setData($item);
        
        return $entity;
    }
    
    public function setItemID($ID)
    {
        $this->_data['item_id'] = (int)$ID;
    }
    
    public function getRegisteredTime()
    {
        return $this->_data['created_at'];
    }
    
    public function getType()
    {
        return $this->_type;
    }
}
