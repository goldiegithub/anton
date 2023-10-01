<?php
/**
 * Description of Anton_Entity_Item_Tick
 *
 * @author Yury Zalatarski <googoldie@gmail.com>
 */
class Anton_Entity_Item_Tick extends Anton_Entity_Item
{
    protected $_type = 'Tick';
    
    public function setData($data)
    {
        $this->_data = $data;
        $this->_data['ask'] = (float)$data['ask'];
        $this->_data['bid'] = (float)$data['bid'];
        $this->_data['id'] = (int)$data['id'];
        $this->_data['item_id'] = (int)$data['id'];
        $this->_data['instrument_id'] = (int)$data['instrument_id'];
    }
}
