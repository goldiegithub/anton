<?php
/**
 * Description of Anton_Entity_Item_Bar
 *
 * @author Yury Zalatarski <googoldie@gmail.com>
 */
class Anton_Entity_Item_Bar extends Anton_Entity_Item
{
    protected $_type = 'Bar';
    
    public function setData($data)
    {
        $this->_data = $data;
        $this->_data['popen'] = (float)$data['popen'];
        $this->_data['pmax'] = (float)$data['pmax'];
        $this->_data['pmin'] = (float)$data['pmin'];
        $this->_data['pclose'] = (float)$data['pclose'];
        $this->_data['bvolume'] = (float)$data['bvolume'];
        $this->_data['id'] = (int)$data['id'];
        $this->_data['item_id'] = (int)$data['id'];
        $this->_data['instrument_id'] = (int)$data['instrument_id'];
    }
}
