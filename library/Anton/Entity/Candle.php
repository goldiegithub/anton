<?php
/**
 * Description of Candle
 *
 * @author Yury Zalatarski <googoldie@gmail.com>
 */
class Anton_Entity_Candle extends Anton_Entity_Abstract
{
    public function setData($data)
    {
        $this->_data = $data;
        $this->_data['time'] = $data['time'];
        $this->_data['open'] = (float)$data['open'];
        $this->_data['max'] = (float)$data['max'];
        $this->_data['min'] = (float)$data['min'];
        $this->_data['close'] = (float)$data['close'];
    }
    
    public function getRegisteredTime()
    {
        return $this->_data['time'];
    }
}
