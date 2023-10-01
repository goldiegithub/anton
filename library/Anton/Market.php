<?php
/**
 * Description of Market
 *
 * @author Yury Zalatarski <googoldie@gmail.com>
 */
class Anton_Market
{
    public static $infinite;
    
    protected $_subscriptions = array();
    protected $_index = array();
    
    protected $_lastEntityID = 0;
    
    protected $_lastTime = null;
    protected $_endTime;
    protected $_startTime;
    
    protected $_started = false;
    protected $_delayed = false;
    
    protected $_delayCallback = null;
    
    private $_subscribedInstruments = array();
    private $_barSubscribedPeriods = array();
    private $_barSubscribedInstruments = array();
    private $_streamParameters = array();
    
    private $_bars = array();
    private $_ticks = array();
    
    private $_newBarFlag = array();
    private $_lastBarByTicks = array();
    
    private $_excludeIDs = array();

    private $_subscriptionStatus = array(
        'Tick'  =>  array('enabled' =>  array()),
        'Bar'   =>  array('enabled' =>  array()),
    );
    
    protected $_saveStatusCheck = false;
    protected $_lastStatusCheckTime;
    protected $_statusCheckLogFile;
    
    public function __construct()
    {
        self::$infinite = Zend_Registry::get('env')->get('DateTimeGenerator')->maxTime();
        
        $this->_saveStatusCheck = (bool)Zend_Registry::get('env')->get('save_status_check');
        $home = getenv('HOME');
        if (empty($home)) {
            if (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
              $home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
            }
        }
        $this->_statusCheckLogFile = $home . '/anton/root/log/status_check_php.log';

        $this->reset();
    }
    
    public function reset()
    {
        $this->_subscribedInstruments = new Doctrine_Collection('Instrument');
        $this->_barSubscribedInstruments = new Doctrine_Collection('Instrument');
        
        $this->_startTime = Zend_Registry::get('env')->get('DateTimeGenerator')->maxTime();
        $this->_endTime = Zend_Registry::get('env')->get('DateTimeGenerator')->minTime();
        
        $this->_bars = array();
        $this->_ticks = array();
        
        $this->_subscriptions = array();
        $this->_index = array();
        
        $this->_lastEntityID = 0;
        $this->_lastTime = null;
        $this->_started = false;
        $this->_delayed = false;
        $this->_delayCallback = null;
        $this->_barSubscribedPeriods = array();
        $this->_streamParameters = array();
        
        $this->_newBarFlag = array();
        $this->_lastBarByTicks = array();
        $this->_subscriptionStatus = array(
            'Tick'  =>  array('enabled' =>  array()),
            'Bar'   =>  array('enabled' =>  array()),
        );
    }
    
    public function start(callable $pauseCallback = null)
    {
        $this->_started = true;
        
        if (!$this->_delayed) {
            $this->_preLoadTicks();
            $this->_preLoadBars();
        } else {
            $curIndexItem = current($this->_index);
            while ($curIndexItem !== FALSE && $curIndexItem['id'] != $this->_lastEntityID) {
                $curIndexItem = next($this->_index);
            }
            if ($curIndexItem === FALSE) {
                throw Zend_Registry::get('env')->get('exception_generator')->load(__CLASS__, 'Cannot find last entity in index');
            }
        }
        
        $this->_delayed = false;
        
        WiseRep_Profiler::startBlock('marketStart');
        do {
            if ($this->_saveStatusCheck) {
                if (is_null($this->_lastStatusCheckTime) || time() - $this->_lastStatusCheckTime > 300) {
                    file_put_contents($this->_statusCheckLogFile, implode(PHP_EOL, array(
                        $t = time(),
                        DateTime::createFromFormat('U', $t)->format('Y-m-d H:i:s')
                    )));
                    $this->_lastStatusCheckTime = $t;
                }
            }
            $entity = $this->_getNextEntity();
            if ($entity) {
                $entityType = $entity->getType();

                $out = array(
                    'entity'    =>  array(
                        'created_at'    =>  $entity->created_at,
                        'itype'         =>  $entity->itype,
                    )
                );
//                Zend_Registry::get('output')->writeln(json_encode($out));
                WiseRep_Profiler::startBlock('lookupsubscriptions');
                $satisfiedSubscriptions = array();
                foreach ($this->_subscriptionStatus[$entityType]['enabled'] as $ssptID) {
                    if($this->_subscriptions['byID'][$ssptID]->satisfy($entity)) {
                        $satisfiedSubscriptions[] = $this->_subscriptions['byID'][$ssptID];
                        $this->_subscriptions['byID'][$ssptID]->handle($entity);
                    }
                }
                
                $this->_lastEntityID = $entity->item_id;
                $this->_lastTime = $entity->getRegisteredTime();

                WiseRep_Profiler::endBlock();
                
                WiseRep_Profiler::startBlock('pauseproccessandoutput');
                $event = new stdClass();
                $event->entity = $entity;
                $event->entityType = $entityType;
                $event->satisfiedSubscriptions = $satisfiedSubscriptions;

                $this->_delayed = $this->_delayed || ($pauseCallback && call_user_func($pauseCallback, $event));

                WiseRep_Profiler::endBlock();
                
//                if ($i % 1000 === 0) {
//                    WiseRep_Profiler::saveData($i);
//                }
//                $i++;
            }else{
                usleep(250000);
            }
        }while (!$this->_delayed && (is_null($this->_endTime) || $this->_endTime == self::$infinite || $entity));
        
        if ($this->_delayed && $this->_delayCallback instanceof Closure) {
            $cb = $this->_delayCallback;
            $this->_delayCallback = null;
            call_user_func($cb);
        }
        WiseRep_Profiler::endBlock();
        WiseRep_Profiler::saveData('finish');
    }
    
    protected function _getNextEntity()
    {
        WiseRep_Profiler::startBlock('nextEntity');
        do {
            $continue = false;
            $item = next($this->_index);
            if (!$item) {
                $converter = Zend_Registry::get('env')->get('DateTimeToDbDateTime');
                $convToObject = Zend_Registry::get('env')->get('DateTimeFromDbDate');

                $this->_lastTime = !is_null($this->_lastTime) 
                        ? $this->_lastTime 
                        : (!is_null($this->_startTime) ? $converter->filter($this->_startTime) : null)
                ;
                if (is_null($this->_lastTime)) {
                    $this->_lastTime = $converter->filter(Doctrine::getTable('Item')->getMaxCreatedAt()) ? : $converter->filter(Zend_Registry::get('env')->get('DateTimeGenerator')->minTime());
                }
                $endTime = $this->_endTime ? : Zend_Registry::get('env')->get('DateTimeGenerator')->maxTime();
                
                $boundTime = $convToObject->filter($this->_lastTime)->add(new DateInterval(Zend_Registry::get('env')->get('load_items_time_interval')));
                $boundTime = $endTime > $boundTime ? $boundTime : $endTime;

                $subscribedInstruments = $this->_subscribedInstruments->toKeyValueArray('id', 'id');
                $barSubscribedPeriods = $this->_barSubscribedPeriods;
                
                // C исключением след. проблема Т.к. за секунду можно несколько раз сюда прийти то нужно запоминать все айтемы с текущем временем а не только те что есть в тек. выборке
                if (!isset($this->_excludeIDs[$this->_lastTime])) {
                    $this->_excludeIDs = array(
                        $this->_lastTime    =>  array(),
                    );
                }
                $item = end($this->_index);
                while ($item && $item['created_at'] == $this->_lastTime) {
                    $this->_excludeIDs[$this->_lastTime][$item['id']] = $item['id'];
                    $item = prev($this->_index);
                }
                
                WiseRep_Profiler::startBlock('loadItems');

                $newIndex = Doctrine::getTable('Item')->fetchIndex(array(
                    'instruments'           =>  $subscribedInstruments,
                    'created_at'            =>  array(array($this->_lastTime, '>='), array($converter->filter($boundTime), '<=')),
                    'split_by_type'         =>  array(
                        'tick'          =>  empty($this->_subscriptions['byType']['Tick']) ? false : array(),
                        'bar'           =>  array(
                            'bperiods'              =>  $barSubscribedPeriods,
                        )
                    ),
                    'exclude_ids'   =>  $this->_excludeIDs[$this->_lastTime],
                    'ordered'               =>  'time', //not used in query
                ));

                WiseRep_Profiler::endBlock();
                
                WiseRep_Profiler::saveData('loadItems');

                if (empty($newIndex)) {
                    end($this->_index);
                    WiseRep_Profiler::endBlock();
                    return null;
                }
                
                $this->_index = $newIndex;
                unset($newIndex);
                
                $item = reset($this->_index);
            }
            
            if ($item['itype'] == 'Tick') {
                $collection = $this->_ticks[$item['instrument_id']]->addItem($item);
                if (array_key_exists($item['instrument_id'], $this->_lastBarByTicks)) {
                    foreach ($this->_barSubscribedPeriods as $period) {
                        if (array_key_exists($period, $this->_lastBarByTicks[$item['instrument_id']])) {
                            $this->addTickToLastBar($item['instrument_id'], $period, $item);
                        }
                    }
                }
                if (count($this->_ticks[$item['instrument_id']]) > @$this->_streamParameters[$item['itype']][$item['instrument_id']]['preLoad'] + 1) {
                    $this->_ticks[$item['instrument_id']]->shift();
                }
            } elseif ($item['itype'] == 'Bar') {
                if (!isset($this->_bars[$item['instrument_id']][$item['bperiod']])) {
                    $continue = true;
                }else{
                    $collection = $this->_bars[$item['instrument_id']][$item['bperiod']]->addItem($item);
                    $this->_newBarFlag[$item['instrument_id']][$item['bperiod']] = true;
                }
            }
            if ($continue || empty($this->_subscriptionStatus[$item['itype']]['enabled'])) {
                $this->_lastEntityID = $item['id'];
                $this->_lastTime = $item['created_at'];
                continue;
            }
        }while($continue || empty($this->_subscriptionStatus[$item['itype']]['enabled']));
        
        WiseRep_Profiler::endBlock();
        return $collection->get();
    }
    
    protected function addTickToLastBar($instrumentID, $p, $item)
    {
        if ($this->_lastBarByTicks[$instrumentID][$p] === null || $this->_newBarFlag[$instrumentID][$p]) {
            $this->_lastBarByTicks[$instrumentID][$p] = new Anton_Entity_BarByTicks();
        }
        $this->_lastBarByTicks[$instrumentID][$p]->addItem($item);
    }
    
    public function getLastExtendedBar($p)
    {
        return $this->_lastBarByTicks[$instrumentID][$p];
    }

    public function delay(Closure $callback = null)
    {
        $this->_delayed = true;
        if ($callback) {
            $this->_delayCallback = $callback;
        }
    }
    
    public function subscribe(Anton_Market_Subscription $subscription)
    {
        if ($this->_started) {
            throw Zend_Registry::get('env')->get('exception_generator')->load(__CLASS__, 'Cannot subscribe. Market already started');
        }
        
        if ($subscription->getOption('startTime', Zend_Registry::get('env')->get('DateTimeGenerator')->maxTime()) < $this->_startTime) {
            $this->_startTime = $subscription->getOption('startTime');
        }
        
        if (!is_null($this->_endTime) && (is_null($subscription->getOption('endTime')) || $subscription->getOption('endTime') > $this->_endTime)) {
            $this->_endTime = $subscription->getOption('endTime');
        }
        
        $ID = $subscription->getID();
        $instrument = $subscription->getInstrument();
        $type = $subscription->getType();
        
        $this->_subscriptions['byID'][$ID] = $this->_subscriptions['byType'][$type][$ID] = $subscription;
        switch (true) {
            case $subscription instanceof Anton_Market_Subscription_Tick:
                if ($preLoad = $subscription->getOption('preLoad', false)) {
                    $this->_streamParameters[$type][$instrument->id]['preLoad'] = (int)@$this->_streamParameters[$type][$instrument->id]['preLoad'];
                    if ((int)@$this->_streamParameters[$type][$instrument->id]['preLoad'] < $preLoad) {
                        $this->_streamParameters[$type][$instrument->id]['preLoad'] = $preLoad;
                    }
                }
                
                $stream = @$this->_ticks[$instrument->id];
                if (!$stream) {
                    $this->_ticks[$instrument->id] = $stream = new Anton_Entity_Item_Collection_Tick;
                }
                
                break;
            case $subscription instanceof Anton_Market_Subscription_Bar:
                
                $period = $subscription->getPeriod();
                
                if ($preLoad = $subscription->getOption('preLoad', false)) {
                    $this->_streamParameters['Bar'][$instrument->id][$period]['preLoad'] = (int)@$this->_streamParameters['Bar'][$instrument->id][$period]['preLoad'];
                    if ((int)@$this->_streamParameters['Bar'][$instrument->id][$period]['preLoad'] < $preLoad) {
                        $this->_streamParameters['Bar'][$instrument->id][$period]['preLoad'] = $preLoad;
                    }
                }
                
                $this->_ticks[$instrument->id] = @$this->_ticks[$instrument->id] ? : new Anton_Entity_Item_Collection_Tick;
                $stream = @$this->_bars[$instrument->id][$period];
                if (!$stream) {
                    $this->_bars[$instrument->id][$period] = $stream = new Anton_Entity_Item_Collection_Bar;
                }
                
                $this->_barSubscribedPeriods[$period] = $period;
                $this->_barSubscribedInstruments->add($instrument);
                if ($subscription->getHandler()) {
                    $this->enableSubscription($ID);
                }
                $this->_newBarFlag[$instrument->id][$period] = true;
                $this->_lastBarByTicks[$instrument->id][$period] = null;
                
                break;
            default:
                unset($this->_subscriptions[$ID]);
                throw Zend_Registry::get('env')->get('exception_generator')->load(__CLASS__, 'Subscription type is not defined');
                break;
        }
        
        $subscription->setStream($stream);
        
        $this->_subscribedInstruments->add($instrument);
        
        if (($event = $subscription->getEvent('onSubscribe'))) {
            call_user_func($event, $subscription);
        }
        
        return $ID;
    }
    
    public function enableSubscription($ID)
    {
        if (!$this->getByID($ID)) {
            throw Zend_Registry::get('env')->get('exception_generator')->load(__CLASS__, 'Not found subscription with ID ' . $ID);
        }
        $this->_subscriptionStatus[$this->getByID($ID)->getType()]['enabled'][$ID] = $ID;
    }
    
    public function disableSubscription($ID)
    {
        if (!$this->getByID($ID)) {
            throw Zend_Registry::get('env')->get('exception_generator')->load(__CLASS__, 'Not found subscription with ID ' . $ID);
        }
        unset($this->_subscriptionStatus[$this->getByID($ID)->getType()]['enabled'][$ID]);
    }
    
    protected function _preLoadTicks()
    {
        foreach ($this->_subscribedInstruments as $instrument) {
            if (@$this->_streamParameters['Tick'][$instrument->id]['preLoad'] > 0) {
                $this->_ticks[$instrument->id]->loadFromArray(array_reverse(Doctrine::getTable('Item')->findByParams(array(
                    'instrument_id'         =>  $instrument->id,
                    'last_items'            =>  array($this->_streamParameters['Tick'][$instrument->id]['preLoad']),
                    'itype'                 =>  'Tick',
                    ) + (is_null($this->_startTime) ? array() : array(
                        'created_at'            =>  array(Zend_Registry::get('env')->get('DateTimeToDbDateTime')->filter($this->_startTime), '<='),
                    )
                ), Doctrine::HYDRATE_ARRAY)));
            }
        }
    }
    
    protected function _preLoadBars()
    {
        foreach ($this->_barSubscribedInstruments as $instrument) {
            foreach ($this->_barSubscribedPeriods as $period) {
                if (@$this->_streamParameters['Bar'][$instrument->id][$period]['preLoad'] > 0) {
                    $this->_bars[$instrument->id][$period]->loadFromArray(array_reverse(Doctrine::getTable('Item')->findByParams(array(
                        'instrument_id'         =>  $instrument->id,
                        'bperiod'               =>  $period,
                        'last_items'            =>  array($this->_streamParameters['Bar'][$instrument->id][$period]['preLoad']),
                        'itype'                 =>  'Bar',
                        ) + (is_null($this->_startTime) ? array() : array(
                            'created_at'            =>  array(Zend_Registry::get('env')->get('DateTimeToDbDateTime')->filter($this->_startTime), '<='),
                        )
                    ), Doctrine::HYDRATE_ARRAY)));
                }
            }
        }
    }
    
    public function getByID($ID)
    {
        return @$this->_subscriptions['byID'][$ID];
    }
    
    public function getTick($instrumentID, $index = null)
    {
        return $this->_ticks[$instrumentID]->get($index);
    }
    
    public function hasNoNewTicks($instrumentID, $time)
    {
        $datetime = DateTime::createFromFormat('U', $time);
        $cTicks = Doctrine::getTable('Item')->countByParams(array(
            'instrument_id'     =>  $instrumentID,
            'itype'             =>  'Tick',
            'created_at'        => array(Zend_Registry::get('env')->get('DateTimeToDbDateTime')->filter($datetime), '>'),
        ));
        return $cTicks == 0;
    }
}
