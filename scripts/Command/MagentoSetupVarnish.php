<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace MagentoDevBox\Command;

require_once __DIR__ . '/../AbstractCommand.php';

use MagentoDevBox\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Bootstrap;
use Magento\PageCache\Model\Config;

/**
 * Command for Varnish setup
 */
class MagentoSetupVarnish extends AbstractCommand
{
    /**
     * @var \PDO
     */
    private $pdo;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('magento:setup:varnish')
            ->setDescription('Setup varnish')
            ->setHelp('This command allows you to setup Varnish inside magento.');

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->saveConfig($input, $output);

        require sprintf('%s/app/bootstrap.php', $input->getOption('magento-path'));
        $bootstrap = Bootstrap::create(BP, $_SERVER);

        $om = $bootstrap->getObjectManager();

        /** @var Config $config */
        $config = $om->get(Config::class);
        $content = $config->getVclFile(Config::VARNISH_4_CONFIGURATION_PATH);

        file_put_contents($input->getOption('varnish-config-path'), $content);

        return null;
    }

    /**
     * {@inheritdoc}
     */
    private function saveConfig(InputInterface $input, OutputInterface $output)
    {
        $this->getPDOConnection($input)->exec(
            'DELETE FROM core_config_data'
                . ' WHERE path = "system/full_page_cache/caching_application" '
                . ' OR path like "system/full_page_cache/varnish/%";'
        );

        $config = [
            [
                'scope' => 'default',
                'scope_id' => 0,
                'path' => 'system/full_page_cache/caching_application',
                'value' => 2
            ],
            [
                'scope' => 'default',
                'scope_id' => 0,
                'path' => 'system/full_page_cache/varnish/access_list',
                'value' => $input->getOption('webserver-host')
            ],
            [
                'scope' => 'default',
                'scope_id' => 0,
                'path' => 'system/full_page_cache/varnish/backend_host',
                'value' => $input->getOption('webserver-host')
            ],
            [
                'scope' => 'default',
                'scope_id' => 0,
                'path' => 'system/full_page_cache/varnish/backend_port',
                'value' => $input->getOption('webserver-port')
            ]
        ];

        $stmt = $this->getPDOConnection($input)->prepare(
            'INSERT INTO core_config_data (scope, scope_id, path, `value`) VALUES (:scope, :scope_id, :path, :value);'
        );

        foreach ($config as $item) {
            $stmt->bindParam(':scope', $item['scope']);
            $stmt->bindParam(':scope_id', $item['scope_id']);
            $stmt->bindParam(':path', $item['path']);
            $stmt->bindParam(':value', $item['value']);
            $stmt->execute();
        }

        $this->executeCommands(
            sprintf('cd %s && php bin/magento cache:clean config', $input->getOption('magento-path')),
            $output
        );
    }

    /**
     * @param InputInterface $input
     * @return \PDO
     */
    private function getPDOConnection(InputInterface $input)
    {
        if ($this->pdo === null) {
            $dsn = 'mysql:dbname=' . $input->getOption('db-name') . ';host=' . $input->getOption('db-host');
            $this->pdo = new \PDO($dsn, $input->getOption('db-user'), $input->getOption('db-password'));
        }
        return $this->pdo;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptionsConfig()
    {
        return [
            static::OPTION_MAGENTO_PATH => $this->getMagentoPathConfig(),
            'webserver-host' => [
                'initial' => true,
                'default' => 'web',
                'description' => 'Varnish Backend Host',
                'question' => 'Please enter Varnish Backend Host %default%'
            ],
            'webserver-port' => [
                'initial' => true,
                'default' => 80,
                'description' => 'Varnish Backend Port',
                'question' => 'Please enter Varnish Backend Port %default%'
            ],
            'db-host' => [
                'initial' => true,
                'default' => 'db',
                'description' => 'Magento Mysql host',
                'question' => 'Please enter magento Mysql host %default%'
            ],
            'db-port' => [
                'initial' => true,
                'default' => '3306',
                'description' => 'Magento Mysql port',
                'question' => 'Please enter magento Mysql port %default%'
            ],
            'db-user' => [
                'initial' => true,
                'default' => 'root',
                'description' => 'Magento Mysql user',
                'question' => 'Please enter magento Mysql user %default%'
            ],
            'db-password' => [
                'initial' => true,
                'default' => 'root',
                'description' => 'Magento Mysql password',
                'question' => 'Please enter magento Mysql password %default%'
            ],
            'db-name' => [
                'initial' => true,
                'default' => 'magento2',
                'description' => 'Magento Mysql database',
                'question' => 'Please enter magento Mysql database %default%'
            ],
            'varnish-config-path' => [
                'initial' => true,
                'default' => '/home/magento2/configs/varnish/default.vcl',
                'description' => 'Magento root directory',
                'question' => 'Please enter output configuration file path %default%'
            ]
        ];
    }
}
