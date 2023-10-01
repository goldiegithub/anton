<?php
namespace Console\Anton\Indicator\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Command\Command as BaseCommand;

/**
 * Output indicator data in csv compatable format
 *
 * @package     Anton
 * @subpackage  Indicator
 * @author      Yury Zolotarsky
 */
class OutputCsv extends BaseCommand
{
    protected function configure()
    {
        /* @var $doctrineOption DoctrineHelper */
        $this
            ->setName('indicator:output-csv')
            ->setAliases(array('i:o'))
            ->setDescription('Output indicator data in csv compatable format')
            ->addArgument('cache-id', InputArgument::REQUIRED, 'Specify indicator name', null)
            ->addArgument('buffer', InputArgument::OPTIONAL, 'Buffer', null)
        ;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cacheID = $input->getArgument('cache-id');
        
        $buffer = $input->getArgument('buffer');
        
        $sels = array();
        
        if ($buffer) {
            $sels['buffer'] = $buffer;
        }
        
        \Zend_Registry::set('output', $output);
        
        $data = \Doctrine::getTable('SavedData')->findByParams(array_merge($sels, array(
            'item'          =>  array(
                'order_by'      =>  array(
                    'field'      =>  'btime',
                    'direction'  =>  'ASC',
                ),
            ),
            'cache_id'      =>  $cacheID,
            'fields'        =>  array(
                'item.btime as btime',
                'item.created_at as created_at',
                'saved_data.buffer as buffer',
                'saved_data.value as value',
            ),
            'order_by'      =>  array(
                'field'      =>  'buffer',
                'direction'  =>  'ASC',
            ),
        )), \Doctrine::HYDRATE_ARRAY);
        
        $val = reset($data);
        $headersOutputFlag = false;$headers = array();
        do{
            $btime = $val['btime'];
            $createdAt = $val['created_at'];
            $values = array();
            do {
                $values[] = $val['value'];
                !$headersOutputFlag ? $headers[] = $val['buffer'] : null;
                $val = next($data);
            }while($val && $val['btime'] == $btime);
            if (!$headersOutputFlag) {
                $output->writeln('Date;Created_At;' . implode(';', $headers));
                $headersOutputFlag = true;
            }
            $output->writeln($btime . ';' . $createdAt . ';' . implode(';', $values));
        }while($val);
    }
}