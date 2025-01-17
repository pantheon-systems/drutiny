<?php

namespace Drutiny\Console\Command;

use Drutiny\PolicyFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Yaml\Yaml;


/**
 *
 */
class AuditInfoCommand extends Command
{

    protected $policyFactory;

    public function __construct(PolicyFactory $policyFactory)
    {
        $this->policyFactory = $policyFactory;
        parent::__construct();
    }

  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('audit:info')
        ->setDescription('Show all php audit classes available.')
        ->addArgument(
            'audit',
            InputArgument::REQUIRED,
            'The name of the audit class to display info about.'
        );
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $audit = $input->getArgument('audit');
        $reflection = new \ReflectionClass($audit);
        $container = $this->getApplication()
          ->getKernel()
          ->getContainer();
        $container->get('target.factory')->create('none:');
        $audit_instance = $container->get($audit);

        $info = [];

        $info[] = ['Namespace', new TableCell($audit, ['colspan' => 4])];
        $info[] = ['Extends', new TableCell($reflection->getParentClass()->name, ['colspan' => 4])];

        $info[] = new TableSeparator();

        $info[] = [
           '<info>Parameters</info>',
           '<fg=yellow>Name</>',
           '<fg=yellow>Required</>',
           '<fg=yellow>Description</>',
           '<fg=yellow>Default value</>'
        ];
        foreach ($audit_instance->getDefinition()->getArguments() as $param) {
          $info[] = [
            '',
            $param->getName(),
            $param->isRequired() ? 'Required' : 'Optional',
            $param->getDescription(),
            Yaml::dump($param->getDefault()),
          ];
        }

        $info[] = new TableSeparator();

        $policy_list = array_filter($this->policyFactory->getPolicyList(), function ($policy) use ($audit) {
            return $policy['class'] == $audit;
        });

        $info[] = ['Policies', new TableCell($this->listing(array_map(function ($policy) {
          return $policy['name'];
        }, $policy_list)), ['colspan' => 4])];

        $info[] = new TableSeparator();

        $info[] = ['Filename', new TableCell($reflection->getFilename(), ['colspan' => 4])];

        $info[] = new TableSeparator();

        $info[] = ['Methods', $this->listing(array_map(function ($method) use ($audit) {
          if ($method->getDeclaringClass()->name !== $audit) {
            return false;
          }
          $function = $method->name . '(';
          foreach ($method->getParameters() as $parameter) {
            $function .= '$' . $parameter->name . ', ';
          }

          if (count($method->getParameters())) {
            $function = substr($function, 0, -2);
          }
          $function .= ')';

          return $function;
        }, $reflection->getMethods()))];


        $io = new SymfonyStyle($input, $output);
        $io->title('Audit Info');
        $io->table([], $info);
        return 0;
    }


    protected function listing($array) {
      return  "* ".implode("\n* ", array_filter($array));
    }
}
