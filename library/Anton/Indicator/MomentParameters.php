<?php
/**
 * Description of MomentParameters
 *
 * @author Yury Zalatarski <googoldie@gmail.com>
 */
class Anton_Indicator_MomentParameters extends Anton_Indicator_Abstract
{
    use Anton_Utils_Config;
    
    protected $_buffers = array(
        'min_min_bar'           =>  array(),
        'min_min_bar_approved'  =>  array(),
        'min_close_bar'         =>  array(),
        'min_close_bar_real'    =>  array(),
        'max_max_bar'           =>  array(),
        'max_max_bar_approved'  =>  array(),
        'max_close_bar'         =>  array(),
        'max_close_bar_real'    =>  array(),
        'e'                     =>  array(),
        'cMovePercent'          =>  array(),
        'min'                   =>  array(),
        'pmin'                  =>  array(),
        'max'                   =>  array(),
        'pmax'                   =>  array(),
        'bmax'                  =>  array(),
        'false_break_down'      =>  array(),
        'last_fall_speed'       =>  array(),
        'confirmation_growth_speed'       =>  array(),
        'bmax_proccessing'       =>  array(),
        'pmin_proccessing'       =>  array(),
        'confirmation_result'    =>  array(),
        'growth_potential'       =>  array(),
        'confirmation_size'      =>  array(),
        'mins_sequence'          =>  array(),
    );
    
    protected $_scaleKoef = 5;
    protected $_minE = 0.01;

    protected $_barNum;
    protected $_bar;    
    
    // бар с самой низкой ценой закрытия или открытия
    protected $_minCloseBar;
    // бар с самой низкой ценой закрытия
    protected $_minCloseBarReal;
    protected $_minMinBar;
    protected $_pMin;
    protected $_ppMin;
    
    // бар с самой высокой ценой закрытия или открытия
    protected $_maxCloseBar;
    // бар с самой высокой ценой закрытия
    protected $_maxCloseBarReal;
    protected $_maxMaxBar;
    protected $_pMax;
    protected $_ppMax;
    
    protected $_percentIndID;
    
    protected $_confirmationBarIndex;
    
    protected $_minGrowthSize;
    protected $_minFallSize;
    
    protected $_growthProb;
    
    protected function _calculate($barNum, $buffer)
    {
        $this->_bar = $bar = $this->_pastBars->get();
        $this->_barNum = $barNum;
        
        // нужно чтобы все буферы считались за один проход т.к. при повторном проходе на одном $barNum затираются расчеты предыдущего прохода данными предыдущего бара
        $this->_buffers['min_min_bar_approved'][$barNum] = (int)@$this->_buffers['min_min_bar_approved'][$barNum - 1];
        $this->_buffers['bmax'][$barNum] = @$this->_buffers['bmax'][$barNum - 1];
        $this->_buffers['false_break_down'][$barNum] = @$this->_buffers['false_break_down'][$barNum - 1];
        $this->_buffers['last_fall_speed'][$barNum] = @$this->_buffers['last_fall_speed'][$barNum - 1];
        $this->_buffers['confirmation_growth_speed'][$barNum] = null;
        $this->_buffers['confirmation_result'][$barNum] = null;
        $this->_buffers['confirmation_size'][$barNum] = null;
        $this->_buffers['prediction_result'][$barNum] = null;
        $this->_buffers['growth_potential'][$barNum] = null;
        $this->_buffers['growth_prob'][$barNum] = null;
        $this->_buffers['bmax_proccessing'][$barNum] = @$this->_buffers['bmax_proccessing'][$barNum - 1];
        $this->_buffers['pmin_proccessing'][$barNum] = @$this->_buffers['pmin_proccessing'][$barNum - 1];
        $this->_buffers['mins_sequence'][$barNum] = @$this->_buffers['mins_sequence'][$barNum - 1];
        
        if ($bar != $this->_minMinBar && $this->_isNewMin()) {
            $this->_buffers['false_break_down'][$barNum] = 0;
            $this->_minMinBar = $this->_minCloseBar = $bar;
            $this->_minCloseBarReal = $this->_minCloseBar;
            
            if($this->_pMax != $this->_maxMaxBar) {
                $this->_ppMax = $this->_pMax;
                $this->_pMax = $this->_maxMaxBar;
            }
            
            $i = -2; $prevBar = $this->_pastBars->get($i);$e = $this->_e();
            while ($prevBar->pmin >= $bar->pmin && $prevBar->pmax / $bar->pmin - 1 < $e) {
                if (min($prevBar->pclose, $prevBar->popen) < min($this->_minCloseBar->pclose, $this->_minCloseBar->popen)) {
                    $this->_minCloseBar = $prevBar;
                }
                if ($prevBar->pclose < $this->_minCloseBarReal->pclose) {
                    $this->_minCloseBarReal = $prevBar;
                }
                $prevBar = $this->_pastBars->get(--$i);
            }
            
            if($this->_maxMaxBar) {
                $maxWidth = 0; $i=0;$_bar = null;
                while($_bar != $this->_maxMaxBar && $_bar = $this->_pastBars->get(--$i)) {
                    $maxWidth = ($w = $_bar->popen - $_bar->pclose) > $maxWidth ? $w : $maxWidth;
                }
                $this->_buffers['last_fall_speed'][$barNum] = $maxWidth / abs($i) / ($this->_maxMaxBar->pmax - $this->_minMinBar->pmin);
            }
            
            $this->_buffers['min_min_bar_approved'][$barNum] = 0;
            $this->_buffers['bmax'][$barNum] = null;
            
            if ($this->_confirmationBarIndex) {
                // Определяем успешность входа как уровень следующего минимума по отношению к текущему уровню последнего confirmation. То на сколько высоко след. минимум и определит успешность сделки
                $this->_buffers['confirmation_result'][$this->_confirmationBarIndex] = 
                    ($this->_minMinBar->pclose - $this->_pastBars->get($this->_confirmationBarIndex)->pclose) / $this->_pastBars->get($this->_confirmationBarIndex)->pclose
                ;
            }
            
            out(APPLICATION_ENV)->writeln('New Min: ' . json_encode(array(
                'minCloseBar'  => $this->_minCloseBar,
                'minMinBar'  => $this->_minMinBar,
            )));
        } else{
            if (!is_null($this->_minCloseBar) && min($bar->pclose, $bar->popen) < min($this->_minCloseBar->pclose, $this->_minCloseBar->popen)) {
                $this->_minCloseBar = $bar;
                out(APPLICATION_ENV)->writeln('New minCloseBar: ' . json_encode(array(
                    'minCloseBar'  => $this->_minCloseBar,
                )));
            }
            if (!is_null($this->_minCloseBarReal) && $bar->pclose < $this->_minCloseBarReal->pclose) {
                $this->_minCloseBarReal = $bar;
            }
        }
        $this->_buffers['min_min_bar'][$barNum] = $this->_minMinBar ? $this->_minMinBar->id : null;
        $this->_buffers['min_close_bar'][$barNum] = $this->_minCloseBar ? $this->_minCloseBar->id : null;
        $this->_buffers['min_close_bar_real'][$barNum] = $this->_minCloseBarReal ? $this->_minCloseBarReal->id : null;
        $this->_buffers['pmin'][$barNum] = $this->_pMin ? $this->_pMin->pmin : null;
        $this->_buffers['min'][$barNum] = $this->_minMinBar ? $this->_minMinBar->pmin : null;
        
        if ($this->_maxMaxBar && $this->_ppMin && $this->_ppMax && isset($this->_buffers['min_close_bar'][$barNum]) && !$this->_buffers['min_min_bar_approved'][$barNum] && $this->_isApprovedMinBar()) {
            $this->_buffers['min_min_bar_approved'][$barNum] = 1;
            $this->_buffers['confirmation_size'][$barNum] = ($bar->pclose - ($min = min($this->_minCloseBar->pclose, $this->_minCloseBar->popen))) / $min;
            
                switch (true) {
                    case $this->_buffers['confirmation_result'][$this->_confirmationBarIndex] <= $this->_minFallSize && $this->_buffers['mins_sequence'][$barNum - 1] != 100:
                        $this->_buffers['mins_sequence'][$barNum] = 100;
                        break;
                    case $this->_buffers['confirmation_result'][$this->_confirmationBarIndex] >= $this->_minGrowthSize && $this->_buffers['mins_sequence'][$barNum - 1] != 200:
                        $this->_buffers['mins_sequence'][$barNum] = 200;
                        break;
                    case $this->_buffers['confirmation_result'][$this->_confirmationBarIndex] >= 0 && $this->_buffers['mins_sequence'][$barNum - 1] > 0:
                        $this->_buffers['mins_sequence'][$barNum] = $this->_buffers['mins_sequence'][$barNum - 1] + 1;
                        break;
                    case $this->_buffers['confirmation_result'][$this->_confirmationBarIndex] >= 0 && $this->_buffers['mins_sequence'][$barNum - 1] < 0:
                        $this->_buffers['mins_sequence'][$barNum] = 1;
                        break;
                    case $this->_buffers['confirmation_result'][$this->_confirmationBarIndex] < 0 && $this->_buffers['mins_sequence'][$barNum - 1] < 0:
                        $this->_buffers['mins_sequence'][$barNum] = $this->_buffers['mins_sequence'][$barNum - 1] - 1;
                        break;
                    case $this->_buffers['confirmation_result'][$this->_confirmationBarIndex] < 0 && in_array($this->_buffers['mins_sequence'][$barNum - 1], array(100,200)):
                        $this->_buffers['mins_sequence'][$barNum] = -$this->_buffers['mins_sequence'][$barNum - 1] - 1;
                        break;
                    case $this->_buffers['confirmation_result'][$this->_confirmationBarIndex] < 0 && $this->_buffers['mins_sequence'][$barNum - 1] > 0:
                        $this->_buffers['mins_sequence'][$barNum] = -1;
                        break;
                    default:
                        $this->_buffers['mins_sequence'][$barNum] = null;
                        break;
                }
            
            $this->_confirmationBarIndex = $barNum;
            if ($this->_maxMaxBar->pmax <= $this->_ppMin->pmin) {
                $this->_buffers['bmax'][$barNum] = $bmax = $this->_ppMin->pmin;
            }else{
                $this->_buffers['bmax'][$barNum] = $bmax = $this->_ppMax->pmax;
            }
            if($this->_minMinBar->pmin < $this->_pMin->pmin && $bar->pmax >= $this->_pMin->pmin) {
                $this->_buffers['false_break_down'][$barNum] = ($this->_pMin->pmin - $this->_minMinBar->pmin) / ($bar->pmax - $this->_pMin->pmin);
            }
            
            $this->_buffers['bmax_proccessing'][$barNum] = ($bmax - $this->_maxMaxBar->pmax) / ($bmax - $this->_pMin->pmin);
            $this->_buffers['pmin_proccessing'][$barNum] = ($this->_minMinBar->pmin - $this->_pMin->pmin) / ($bmax - $this->_pMin->pmin);
            
            $maxWidth = 0; $i=0;$_bar = null;
            while($_bar != $this->_minMinBar && $_bar = $this->_pastBars->get(--$i)) {
                $maxWidth = ($w = $_bar->pclose - $_bar->popen) > $maxWidth ? $w : $maxWidth;
            }
            $this->_buffers['confirmation_growth_speed'][$barNum] = $maxWidth / abs($i) / ($_bar->pmax - $this->_minMinBar->pmin);
            $this->_buffers['growth_potential'][$barNum] = ($this->_maxMaxBar->pmax - $bar->pmax) / ($this->_maxMaxBar->pmax - $this->_minMinBar->pmin);
            if ($ms = $this->_buffers['mins_sequence'][$barNum]) {
                $this->_buffers['growth_prob'][$barNum] = $this->_growthProb["K_$ms"] ? : $this->_growthProb["K_0"];
            }
        }
        
        $this->_buffers['max_max_bar_approved'][$barNum] = (int)@$this->_buffers['max_max_bar_approved'][$barNum - 1];
        if ($bar != $this->_maxMaxBar && $this->_isNewMax()) {
            $this->_buffers['max_max_bar_approved'][$barNum] = 0;
            $this->_buffers['false_break_down'][$barNum] = 0;
            
            if($this->_pMin != $this->_minMinBar) {
                $this->_ppMin = $this->_pMin;
                $this->_pMin = $this->_minMinBar;
            }
            
            $this->_maxMaxBar = $this->_maxCloseBar = $bar;
            $this->_maxCloseBarReal = $this->_maxCloseBar;
            $i = -2; $prevBar = $this->_pastBars->get($i);$e = $this->_e();
            while ($prevBar->pmax >= $bar->pmax && $prevBar->pmax / $bar->pmax - 1 < $e) {
                if (max($prevBar->pclose, $prevBar->popen) < max($this->_maxCloseBar->pclose, $this->_maxCloseBar->popen)) {
                    $this->_maxCloseBar = $prevBar;
                }
                if ($prevBar->pclose < $this->_maxCloseBarReal->pclose) {
                    $this->_maxCloseBarReal = $prevBar;
                }
                $prevBar = $this->_pastBars->get(--$i);
            }
            
            out(APPLICATION_ENV)->writeln('New Max: ' . json_encode(array(
                'maxCloseBar'  => $this->_maxCloseBar,
                'maxMaxBar'  => $this->_maxMaxBar,
            )));
        } else{
            if (!is_null($this->_maxCloseBar) && max($bar->pclose, $bar->popen) < max($this->_maxCloseBar->pclose, $this->_maxCloseBar->popen)) {
                $this->_maxCloseBar = $bar;
                out(APPLICATION_ENV)->writeln('New maxCloseBar: ' . json_encode(array(
                    'maxCloseBar'  => $this->_maxCloseBar,
                )));
            }
            if (!is_null($this->_maxCloseBarReal) && $bar->pclose < $this->_maxCloseBarReal->pclose) {
                $this->_maxCloseBarReal = $bar;
            }
        }

        $this->_buffers['max_max_bar'][$barNum] = $this->_maxMaxBar ? $this->_maxMaxBar->id : null;
        $this->_buffers['max_close_bar'][$barNum] = $this->_maxCloseBar ? $this->_maxCloseBar->id : null;
        $this->_buffers['max_close_bar_real'][$barNum] = $this->_maxCloseBarReal ? $this->_maxCloseBarReal->id : null;
        $this->_buffers['pmax'][$barNum] = $this->_maxMaxBar ? $this->_maxMaxBar->pmax : null;
        $this->_buffers['max'][$barNum] = $this->_maxMaxBar ? $this->_maxMaxBar->pmin : null;
        
        
        if (isset($this->_buffers['max_close_bar'][$barNum]) && !$this->_buffers['max_max_bar_approved'][$barNum] && $this->_isApprovedMaxBar()) {
            $this->_buffers['max_max_bar_approved'][$barNum] = 1;
            $this->_buffers['max'][$barNum] = $this->_maxMaxBar->pmax;
        }
        
        $this->_buffers['cMovePercent'][$barNum] = $this->_indicatorManager->calculate($this->_percentIndID, null, 'cMovePercent');
        $this->_buffers['e'][$barNum] = $this->_e();
        
        return @$this->_buffers[$buffer][$barNum];
    }
    
    protected function _enoughFall()
    {
        $bar = $this->_pastBars->get(); $i = -2; $e = $this->_e();
        while($this->_pastBars->get($i)->pmin >= $bar->pmin && ($d = $this->_pastBars->get($i)->pmax / $bar->pmin - 1) < $e) {
            $i--;
        }
        
        return isset($d) && $d >= $e;
    }
    
    protected function _enoughGrowth()
    {
        $bar = $this->_pastBars->get(); $i = -2; $e = $this->_e();
        while($this->_pastBars->get($i)->pmax <= $bar->pmax && ($d = $bar->pmax / $this->_pastBars->get($i)->pmin - 1) < $e) {
            $i--;
        }
        
        return isset($d) && $d >= $e;
    }
    
    protected function _isNewMin()
    {
        return (!is_null($this->_minMinBar) && $this->_pastBars->get()->pmin < $this->_minMinBar->pmin)
                || ((is_null($this->_minCloseBar) || $this->_pastBars->get()->pmin <= $this->_pastBars->get(-2)->pmin)
                    && $this->_enoughFall()
                    )
        ;
    }
    
    protected function _isNewMax()
    {
        return (!is_null($this->_maxMaxBar) && $this->_pastBars->get()->pmax > $this->_maxMaxBar->pmax)
                || ((is_null($this->_maxCloseBar) || $this->_pastBars->get()->pmax >= $this->_pastBars->get(-2)->pmax)
                    && $this->_enoughGrowth()
                    )
        ;
    }
    
    protected function _isApprovedMinBar()
    {
//        $g = ($this->_pastBars->get()->pclose / min($this->_minCloseBar->popen, $this->_minCloseBar->pclose) - 1);
        return ($this->_pastBars->get()->pclose / min($this->_minCloseBar->popen, $this->_minCloseBar->pclose) - 1) > 0.004; // лучше перейти на $this->_e() / 3 и в стратегии тоже
    }
    
    protected function _isApprovedMaxBar()
    {
        return (max($this->_maxCloseBar->popen, $this->_maxCloseBar->pclose) / $this->_pastBars->get()->pclose - 1) > 0.004; // лучше перейти на $this->_e() / 3 и в стратегии тоже
    }

    protected function _e()
    {
        $avgBar = $this->_indicatorManager->calculate($this->_percentIndID, null, 'avg');
        $e = $this->_scaleKoef * $avgBar / 100;
        return $e > $this->_minE ? $e : $this->_minE;
    }
    
    public function setScaleKoef($scaleKoef)
    {
        $this->_scaleKoef = $scaleKoef;
        return $this;
    }
    
    public function setMinE($minE)
    {
        $this->_minE = $minE;
        return $this;
    }
    
    public function setMinFallSize($minFallSize)
    {
        $this->_minFallSize = $minFallSize;
        return $this;
    }
    
    public function setMinGrowthSize($minGrowthSize)
    {
        $this->_minGrowthSize = $minGrowthSize;
        return $this;
    }
    
    public function setGrowthProb($growthProb)
    {
        $this->_growthProb = (array)$growthProb;
        return $this;
    }
    
    public function onSubscribe($subscription)
    {
        $context = $subscription->getOption('context', false);
        
        $this->_percentIndID = trim(implode('_', array($context, 'mp_percent')), '_');
        $this->_indicatorManager->load('percent', $this->_loadConfigFromFile('Percent/config_' . $this->_percentIndID . '.json', 'indicator') + array(
            'Instrument'    =>  $this->_instrument->instrument_name,
            'Period'        =>  $subscription->getPeriod(),
            'Stream'        =>  $subscription->getStream(),
        ), $this->_percentIndID, array(
            'reload_cache'  =>  true,
            'save_cache'    =>  false,
        ));

        $indicator = $this->_indicatorManager->get($this->_percentIndID);
        Zend_Registry::get('env')->get('market')->subscribe(new Anton_Market_Subscription_Bar($subscription->getInstrument(), $subscription->getPeriod(), array(
            'context'       =>  $this->_percentIndID,
            'startTime'     =>  $subscription->getOption('startTime'),
            'endTime'       =>  $subscription->getOption('endTime'),
            'preLoad'       =>  $indicator->getBarsPreLoadHistorySize(),
            'handler'       =>  $this->_indicatorManager->handler($this->_percentIndID),
            'events'        =>  array(
                'onSubscribe'   =>  function($subscr) use($indicator) {
                    $indicator->onSubscribe($subscr);
                    $subscr->setEvent('onSubscribe', null);
                },
            ),
        )));
        $this->setBarsPreLoadHistorySize($indicator->getBarsPreLoadHistorySize());
    }
}
