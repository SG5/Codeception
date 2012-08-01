<?php
namespace Codeception\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use \Symfony\Component\Console\Helper\DialogHelper;

class Build extends Base
{

	protected $template = <<<EOF
<?php
// This class was automatically generated by build task
// You can change it manually, but it will be overwritten on next build

use Codeception\Maybe;

%s %s extends %s
{
    %s
}


EOF;

    protected $methodTemplate = <<<EOF

   /**
    * This method is generated. DO NOT EDIT.
    *
    * @see %s::%s()
    */
    public function %s(%s) {
        \$this->scenario->%s('%s', func_get_args());
        if (\$this->scenario->running()) {
            \$result = \$this->scenario->runStep();
            return new Maybe(\$result);
        }
        return new Maybe();
    }
EOF;

    
    public function getDescription() {
        return 'Generates base classes for all suites';
    }

    protected function configure()
    {
        $this->setDefinition(array(
            new \Symfony\Component\Console\Input\InputOption('silent', '', InputOption::VALUE_NONE, 'Don\'t ask for rebuild')
        ));
        parent::configure();
    }

	protected function execute(InputInterface $input, OutputInterface $output)
	{
        $config = \Codeception\Configuration::config();
        $suites = \Codeception\Configuration::suites();

        foreach ($suites as $suite) {
            $settings = \Codeception\Configuration::suiteSettings($suite, $config);

            $modules = \Codeception\Configuration::modules($settings);

            $code = array();
            $methodCounter = 0;

            foreach ($modules as $modulename => $module) {
                $class = new \ReflectionClass($className = '\Codeception\\Module\\'.$modulename);
                $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
                foreach ($methods as $method) {
                    if (strpos($method->name, '_') === 0) continue;
                    $params = array();
                    foreach ($method->getParameters() as $param) {

                        if ($param->isOptional()) {
                            $params[] = '$' . $param->name.' = null';
                        } else {
                            $params[] = '$' . $param->name;
                        };

                    }

                    if (0 === strpos($method->name, 'see')) {
                        $type = 'assertion';
                    } elseif (0 === strpos($method->name, 'am')) {
                        $type = 'condition';
                    } else {
                        $type = 'action';
                    }

                    $params = implode(', ', $params);
                    $code[] = sprintf($this->methodTemplate, $className, $method->name, $method->name, $params, $type, $method->name);

                    $methodCounter++;
                }
            }

            $contents = sprintf($this->template, 'class',$settings['class_name'], '\Codeception\AbstractGuy', implode("\r\n\r\n ", $code));

            file_put_contents($file = $settings['path'].$settings['class_name'].'.php', $contents);
            $output->writeln("$file generated sucessfully. $methodCounter methods added");
        }
    }
}
