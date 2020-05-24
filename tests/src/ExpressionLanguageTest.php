<?php

namespace DrutinyTests\Audit;

use Drutiny\Console\Application;
use Drutiny\Kernel;
use Drutiny\Policy;
use Drutiny\Sandbox\Sandbox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class ExpressionLanguageTest extends TestCase {

  protected $target;
  protected $application;
  protected $output;
  protected $container;

  protected function setUp(): void
  {
      $kernel = new Kernel('phpunit');
      $kernel->addServicePath(
        str_replace($kernel->getProjectDir(), '', dirname(dirname(__FILE__))));
      $this->application = new Application($kernel, 'x.y.z');
      $this->application->setAutoExit(FALSE);
      $this->output = $kernel->getContainer()->get('output');
      $this->container = $kernel->getContainer();
      $this->target = $this->container->get('target.factory')->create('@none');
      $this->target->setUri('https://example.com/');
  }

  public function testPolicyObjectUsage()
  {
      $language = $this->container->get('expression_language');
      $this->assertInstanceOf(ExpressionLanguage::class, $language);

      $this->assertEquals($language->evaluate('policy("Test:PassDependant")'), 'success');

      $params = ['version' => '1.2.4'];
      $this->assertEquals($language->evaluate('semver_gt("1.2.3", version)', $params), false);
      $this->assertEquals($language->evaluate('semver_gt("4.2.3", version)', $params), true);

      $this->assertEquals($language->evaluate('semver_gte("1.2.3", version)', $params), false);
      $this->assertEquals($language->evaluate('semver_gte("4.2.3", version)', $params), true);
      $this->assertEquals($language->evaluate('semver_gte("1.2", "1.2")'), true);
      $this->assertEquals($language->evaluate('semver_gte("1.2", "1.2.0")'), false);

      $version = $this->target->getProperty('php_version');
      $this->assertEquals($language->evaluate('target("php_version")'), $version);

      
  }
}