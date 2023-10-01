<?php
namespace Console\Anton\Indicator\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Command\Command as BaseCommand;

/**
 * Load indicator data
 *
 * @package     Anton
 * @subpackage  Indicator
 * @author      Yury Zolotarsky
 */
class LoadFromCsv extends BaseCommand
{
    protected function configure()
    {
        /* @var $doctrineOption DoctrineHelper */
        $this
            ->setName('indicator:load-from-file')
            ->setAliases(array('i:l'))
            ->setDescription('Load indicator data')
            ->addArgument('cache-id', InputArgument::REQUIRED, 'Specify indicator name', null)
            ->addArgument('instrument', InputArgument::REQUIRED, 'Instrument', null)
            ->addArgument('period', InputArgument::REQUIRED, 'Period', null)
            ->addArgument('input_file', InputArgument::REQUIRED, 'Configuration file', null)
            ->addOption('skip-zero', 'z', InputOption::VALUE_NONE, 'Skip zero values')
            ->addOption('clear-data', 'c', InputOption::VALUE_NONE, 'Clear indicator data before load')
        ;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cacheID = $input->getArgument('cache-id');
        $period = $input->getArgument('period');
        $skipZero = (bool)$input->getOption('skip-zero');
        $clearData = (bool)$input->getOption('clear-data');
        
        $fname = $input->getArgument('input_file');
        $config = array();
        if (!file_exists($fname)) {
            $output->writeln("Config file not found: " . $fname);
            return;
        }
        
        \Zend_Registry::set('output', $output);
        
        $instrument = \Doctrine::getTable('Instrument')->findOneByParams(array(
            'instrument_name'   =>  $input->getArgument('instrument'),
        ));
        
        if (!$instrument) {
            $output->writeln("Instrument with name : " . $input->getArgument('instrument') . " not found");
            return;
        }
        
        $convToObject = \Zend_Registry::get('env')->get('DateTimeFromDbDate');
        
        $csvLoader = $data = \Zend_Registry::get('env')->get('csv_loader');
        
        $data = $csvLoader->load($fname);
        
        $headers = array_shift($data);
        
        if ($clearData) {
            \Doctrine::getTable('SavedData')->deleteByParams(array(
                'cache_id'      =>  $cacheID,
            ));
        }
        
        foreach ($data as $row) {
            $dateObj = $convToObject->filter($row[0]);
            $date1 = \Anton_Period::time($dateObj, $period);
            $output->writeln('item btime: ' . $date1);
            $date2 = \Anton_Period::time($dateObj->add(\Anton_Period::getInterval($period)), $period);
            $item = \Doctrine::getTable('Item')->findOneByParams(array(
                'instrument_id'     =>  $instrument->id,
                'bperiod'           =>  $period,
                'created_at'        =>  array(array($date1, '>'), array($date2, '<=')),
                'btime'             =>  $date1,
            ));
            if ($item) {
                for ($i = 1; $i < count($headers); $i++) {
                    if (!$skipZero || ($skipZero && !empty($row[$i]))) {
                        \Doctrine::getTable('SavedData')->create(array(
                            'cache_id'      =>  $cacheID,
                            'buffer'        =>  $headers[$i],
                            'item_id'       =>  $item->id,
                            'value'         =>  $row[$i],
                        ))->save();
                    }
                }
            }else{
                $output->writeln('item btime: ' . $date1 . ' not found');
            }
        }
    }
}