<?php
namespace Console\Anton\Indicator\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Command\Command as BaseCommand;

/**
 * Calculate indicator
 *
 * @package     Anton
 * @subpackage  Indicator
 * @author      Yury Zolotarsky
 */
class Calculate extends BaseCommand
{
    protected function configure()
    {
        /* @var $doctrineOption DoctrineHelper */
        $this
            ->setName('indicator:calculate')
            ->setAliases(array('i:c'))
            ->setDescription('Calculate indicator')
            ->addArgument('indicator_service', InputArgument::REQUIRED, 'Specify indicator service', null)
            ->addArgument('instrument', InputArgument::REQUIRED, 'Instrument', null)
            ->addArgument('period', InputArgument::REQUIRED, 'Period', null)
            ->addArgument('save_alias', InputArgument::REQUIRED, 'Save alias', null)
            ->addArgument('input_file', InputArgument::OPTIONAL, 'Configuration file', null)
            ->addArgument('start_date', InputArgument::OPTIONAL, 'Start date(skip if indicator must work from last bar)', null)
            ->addArgument('end_date', InputArgument::OPTIONAL, 'End date(skip if indicator must work in realtime)', null)
            ->addOption('clear-data', 'c', InputOption::VALUE_NONE, 'Clear indicator data before load')
            ->addOption('deferred-save', 'd', InputOption::VALUE_NONE, 'Save to cache after calculating indicator for entire period')
        ;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $indService = $input->getArgument('indicator_service');
        $clearData = (bool)$input->getOption('clear-data');
        $deferredSave = (bool)$input->getOption('deferred-save');
        
        $fname = $input->getArgument('input_file');
        $config = array();
        if (strlen($fname) > 0) {
            if (!file_exists($fname)) {
                $output->writeln("Config file not found: " . $fname);
                return;
            }
            $configFileCnt = file_get_contents($fname);
            $config = json_decode($configFileCnt);
            if(json_last_error() !== JSON_ERROR_NONE) {
                $output->writeln("Config file parsing failed Error: " . json_last_error());
                return;
            }
        }
        $implementation = \Zend_Registry::get('Application')->getBootstrap()->getResource('Implementation');
        $indicatorManager = \Zend_Registry::get('env')->get('indicator_manager');
        
        \Zend_Registry::set('output', $output);
        
        $instrument = \Doctrine::getTable('Instrument')->findOneByParams(array(
            'instrument_name'   =>  $input->getArgument('instrument'),
        ));
        
        if (!$instrument) {
            $output->writeln("Instrument with name : " . $input->getArgument('instrument') . " not found");
            return;
        }
        
        $convToObject = \Zend_Registry::get('env')->get('DateTimeFromDbDate');
        $subscrOptions = array();
        $startDate = $input->getArgument('start_date');
        if ($startDate) {
            $subscrOptions['startTime'] = $convToObject->filter($startDate);
        }
        
        $indicator = $indicatorManager->load($indService, (array)$config + array(
            'Instrument'    =>  $input->getArgument('instrument'),
            'Period'        =>  $input->getArgument('period'),
        ), $alias = $input->getArgument('save_alias'), array(
            'save_cache'    =>  !$deferredSave,
            'reload_cache'  =>  true,
        ));
        
        $endDate = $input->getArgument('end_date');
        if ($endDate) {
            $subscrOptions['endTime'] = $convToObject->filter($endDate);
        }
        
        \Zend_Registry::set('output', $output);
        
        if ($clearData) {
            \Doctrine::getTable('SavedData')->deleteByParams(array(
                'cache_id'      =>  $alias,
            ));
        }
        
        $subscription = new \Anton_Market_Subscription_Bar($instrument, $input->getArgument('period'), $subscrOptions + array(
            'preLoad'      => $indicator->getBarsPreLoadHistorySize(),
            'handler'      =>  function($bar, $subs) use ($indicatorManager, $alias, $output) {
                foreach ($indicatorManager->get($alias)->getBuffersNames() as $buffer) {
                    $value = $indicatorManager->calculate($alias, $bar, $buffer);
                    $output->writeln('time;' . $bar->btime . ';buffer;' . $buffer . ';value;' . $value);
                }
            },
            'events'        =>  array(
                'onSubscribe'   =>  function($subscr) use ($indicator) {
                    $indicator->setStream($subscr->getStream());
                    if (method_exists($indicator, 'onSubscribe')) {
                        $indicator->onSubscribe($subscr);
                    }
                },
            ),
        ));
        \Zend_Registry::get('env')->get('market')->subscribe($subscription);
        \Zend_Registry::get('env')->get('market')->start();
        
        if ($deferredSave) {
            $indicatorManager->setOption($alias, 'save_cache', true);
            foreach ($subscription->getStream() as $bar) {
                foreach ($indicatorManager->get($alias)->getBuffersNames() as $buffer) {
                    $output->write('Saving to cache time;' . $bar->btime . ';buffer;' . $buffer . ';value:');
                    $value = $indicatorManager->calculate($alias, $bar, $buffer);
                    $output->writeln($value ? : "");
                }
            }
        }
    }
}