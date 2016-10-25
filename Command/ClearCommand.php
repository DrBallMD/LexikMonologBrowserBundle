<?php
namespace Lexik\Bundle\MonologBrowserBundle\Command;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Doctrine\DBAL\Types\Type;
/**
 * Description of ClearCommand
 *
 * @author Dmitry Anikeev <da@kww.su>
 */
class ClearCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('lexik:monolog-browser:clear')
            ->setDescription('Delete stored logs')
            ->addOption(
                'days',
                null,
                InputOption::VALUE_OPTIONAL,
                'Deletes all logs up to current date sub days',
                null
            )
            ->addOption(
                'level_name',
                null,
                InputOption::VALUE_OPTIONAL,
                'Level of logs for delete',
                null
            )             
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Confirm execute. Prints sql query if option is missing'
            )
        ;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        /* @var $connection \Doctrine\DBAL\Connection */
        $connection = $container->get('lexik_monolog_browser.doctrine_dbal.connection');
        $tableName = $this->getContainer()->getParameter('lexik_monolog_browser.doctrine.table_name');
        $qb = $connection->createQueryBuilder();
        $days = $input->getOption('days');
        if ($days)
        {
            $current = new \DateTime();
            $current->sub(new \DateInterval('P'.$days.'D'));
            $qb
                ->andWhere($qb->expr()->lte('datetime', ':date'))
                ->setParameter('date', $current, \Doctrine\DBAL\Types\Type::DATETIME)
            ;
        }
        $level_name = $input->getOption('level_name');
        if ($level_name)
        {
            $qb
                ->andWhere($qb->expr()->eq('level_name', ':level_name'))
                ->setParameter('level_name', $level_name, \PDO::PARAM_STR)
            ;
        }
        $ok = 1;
        if (!$input->getOption('force'))
        {
            try
            {
                $qb
                    ->select('COUNT(*)')
                    ->from($tableName,'e')
                ;
                $output->writeln(sprintf('<info>Will be deleted %s rows</info>', $qb->execute()->fetchColumn()));
            } 
            catch (Exception $ex) 
            {
                 $output->writeln(sprintf('<error>%s</error>', $ex->getMessage()));
                 $ok = 0;
            }
        }
        else
        {
            try
            {
                $qb
                    ->delete($tableName)
                ;
                $output->writeln(sprintf('<info>Deleted %s rows</info>', $qb->execute()));
            } 
            catch (Exception $ex) 
            {
                 $output->writeln(sprintf('<error>%s</error>', $ex->getMessage()));
                 $ok = 0;
            }
        }
        return $ok;
    }
    
    protected function convertDate($date, $qb) 
    {
            return Type::getType('datetime')->convertToDatabaseValue($date, $qb->getConnection()->getDatabasePlatform());
    }
}
