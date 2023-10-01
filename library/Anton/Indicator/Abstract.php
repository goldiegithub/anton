<?php
/**
 * Description of Abstract
 *
 * @author Yury Zalatarski <googoldie@gmail.com>
 */
abstract class Anton_Indicator_Abstract
{
    protected $_instrument;
    protected $_period;
    
    /**
     * @var Anton_Entity_Item_Collection_Bar $_pastBars
     */
    protected $_pastBars;
    protected $_barsPreLoadHistorySize = 10;
    
    protected $_indicatorManager;
    
    protected $_buffers = array();
    
    public function init(){}
    
    public function setManager(Anton_Indicator_Manager $manager)
    {
        $this->_indicatorManager = $manager;
        
        return $this;
    }
    
    public function setInstrument($instrument)
    {
        $this->_instrument = Doctrine::getTable('Instrument')->findOneByParams(array(
            'instrument_name'   =>  $instrument,
        ));
        
        return $this;
    }
    
    public function setStream(Anton_Entity_Item_Collection_Bar $stream)
    {
        if ($this->_pastBars === null) {
            $this->_pastBars = $stream;
            return $this;
        }
        throw Zend_Registry::get('env')->get('exception_generator')->load(__CLASS__, 'Stream has already set');
    }
    
    public function setPeriod($period)
    {
        $this->_period = $period;
        
        return $this;
    }
    
    public function getPeriod()
    {
        return $this->_period;
    }
    
    public function getInstrument()
    {
        return $this->_instrument;
    }
    
    public function calculate(Anton_Entity_Item_Bar $bar, $buffer)
    {
        if (($index = $this->_pastBars->index($bar)) === FALSE) {
            throw Zend_Registry::get('env')->get('exception_generator')->load(__CLASS__, 'Cannot find passed bar in loaded history');
        }
        if (array_key_exists($index, $this->_buffers[$buffer])) {
            return $this->_buffers[$buffer][$index];
        }
        if ($index != $this->_pastBars->count() - 1) {
            // Если запрашиваем значение индикатора меньше размера прелоада, то возвращаем null. Нужно учесть что размер прелоада определяется 
            // размером максимального прелоада инструмента поэтому в onSubscribe индикаторов нужно устанавливать размер прелоада по максимальному 
            // прелоаду подписанных индикаторов
            if($index < $this->_barsPreLoadHistorySize) {
                return null;
            }
            throw Zend_Registry::get('env')->get('exception_generator')->load(__CLASS__, 'Cannot find value in cache. Indicator can calculate only last bar. Last bar is ' . $this->_pastBars->count() - 1 . ' Passed ' . $index);
        }
        return $this->_calculate($index, $buffer);
    }
    
    public function getBuffersNames()
    {
        return array_keys($this->_buffers);
    }
    
    function getBarsPreLoadHistorySize()
    {
        return $this->_barsPreLoadHistorySize;
    }

    function setBarsPreLoadHistorySize($barsPreLoadHistorySize)
    {
        $this->_barsPreLoadHistorySize = $barsPreLoadHistorySize;
        return $this;
    }
    
    public function getBars()
    {
        return $this->_pastBars;
    }
    
    abstract protected function _calculate($barNum, $buffer);
    
    public function onSubscribe(Anton_Market_Subscription_Bar $subscription) {}
}
