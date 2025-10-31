<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Scanner;

use Symfony\Component\Finder\Finder;
use Tourze\ArchitectureDiagramBundle\Model\Architecture;
use Tourze\ArchitectureDiagramBundle\Model\Component;

class EnhancedArchitectureScanner
{
    /** @var array<string, ControllerScanner|EntityScanner|RepositoryScanner|ServiceScanner|EventListenerScanner> */
    private array $scanners = [];

    private RelationAnalyzer $relationAnalyzer;

    public function __construct(
        ControllerScanner $controllerScanner,
        EntityScanner $entityScanner,
        RepositoryScanner $repositoryScanner,
        ServiceScanner $serviceScanner,
        EventListenerScanner $eventListenerScanner,
        RelationAnalyzer $relationAnalyzer,
    ) {
        $this->scanners = [
            'controller' => $controllerScanner,
            'entity' => $entityScanner,
            'repository' => $repositoryScanner,
            'service' => $serviceScanner,
            'event_listener' => $eventListenerScanner,
        ];
        $this->relationAnalyzer = $relationAnalyzer;
    }

    public function scanProject(string $projectPath): Architecture
    {
        $architecture = new Architecture(
            basename($projectPath) . ' 系统架构',
            '完整的系统架构分析，包含硬件、数据流、安全等多个维度'
        );

        $this->scanComponents($projectPath, $architecture);
        $this->analyzeInfrastructure($projectPath, $architecture);
        $this->analyzeDataFlow($projectPath, $architecture);
        $this->analyzeExternalIntegrations($projectPath, $architecture);
        $this->analyzeSecurityMeasures($projectPath, $architecture);
        $this->analyzeUserAccess($projectPath, $architecture);
        $this->analyzeManagementFeatures($projectPath, $architecture);

        $architecture->setMetadata('scan_time', date('Y-m-d H:i:s'));
        $architecture->setMetadata('project_path', $projectPath);

        return $architecture;
    }

    private function scanComponents(string $projectPath, Architecture $architecture): void
    {
        foreach ($this->scanners as $type => $scanner) {
            $components = $scanner->scan($projectPath);
            if (is_array($components)) {
                foreach ($components as $component) {
                    $architecture->addComponent($component);
                }
            }
        }

        $this->relationAnalyzer->analyze($architecture);
    }

    private function analyzeInfrastructure(string $projectPath, Architecture $architecture): void
    {
        $this->analyzeDockerCompose($projectPath, $architecture);
        $this->addDefaultAppServers($architecture);
    }

    private function analyzeDockerCompose(string $projectPath, Architecture $architecture): void
    {
        $dockerFile = $projectPath . '/docker-compose.yml';
        if (!file_exists($dockerFile)) {
            return;
        }

        $content = file_get_contents($dockerFile);
        if (false === $content) {
            return;
        }

        $this->addDatabaseInfrastructure($content, $architecture);
        $this->addCacheInfrastructure($content, $architecture);
        $this->addLoadBalancerInfrastructure($content, $architecture);
        $this->addMessageQueueInfrastructure($content, $architecture);
    }

    private function addDatabaseInfrastructure(string $content, Architecture $architecture): void
    {
        if (!$this->hasDatabaseService($content)) {
            return;
        }

        $architecture->addInfrastructure('mysql_server', 'MySQL数据库服务器', 'database', [
            'version' => '8.0',
            'storage' => '100GB SSD',
            'memory' => '16GB',
        ]);

        $architecture->addComponent(new Component(
            'main_database',
            'database',
            '主数据库',
            'MySQL 8.0',
            '存储核心业务数据'
        ));
    }

    private function hasDatabaseService(string $content): bool
    {
        return str_contains($content, 'mysql') || str_contains($content, 'mariadb');
    }

    private function addCacheInfrastructure(string $content, Architecture $architecture): void
    {
        if (!str_contains($content, 'redis')) {
            return;
        }

        $architecture->addInfrastructure('redis_server', 'Redis缓存服务器', 'cache', [
            'version' => '7.0',
            'memory' => '8GB',
            'persistence' => 'AOF+RDB',
        ]);

        $architecture->addComponent(new Component(
            'cache_layer',
            'cache',
            '缓存层',
            'Redis',
            '提供高性能缓存服务'
        ));
    }

    private function addLoadBalancerInfrastructure(string $content, Architecture $architecture): void
    {
        if (!str_contains($content, 'nginx')) {
            return;
        }

        $architecture->addInfrastructure('nginx_lb', 'Nginx负载均衡器', 'load_balancer', [
            'version' => '1.21',
            'ssl' => 'TLS 1.3',
            'workers' => '4',
        ]);

        $architecture->addComponent(new Component(
            'load_balancer',
            'load_balancer',
            '负载均衡器',
            'Nginx',
            '分发请求到应用服务器'
        ));
    }

    private function addMessageQueueInfrastructure(string $content, Architecture $architecture): void
    {
        if (!$this->hasMessageQueueService($content)) {
            return;
        }

        $queueType = str_contains($content, 'rabbitmq') ? 'RabbitMQ' : 'Kafka';

        $architecture->addInfrastructure('message_queue', '消息队列服务器', 'message_broker', [
            'type' => $queueType,
            'memory' => '4GB',
        ]);

        $architecture->addComponent(new Component(
            'message_broker',
            'message_broker',
            '消息队列',
            $queueType,
            '异步消息处理'
        ));
    }

    private function hasMessageQueueService(string $content): bool
    {
        return str_contains($content, 'rabbitmq') || str_contains($content, 'kafka');
    }

    private function addDefaultAppServers(Architecture $architecture): void
    {
        $architecture->addInfrastructure('app_servers', '应用服务器集群', 'server', [
            'count' => '3',
            'cpu' => '8 cores',
            'memory' => '32GB',
            'os' => 'Ubuntu 22.04',
        ]);
    }

    private function analyzeDataFlow(string $projectPath, Architecture $architecture): void
    {
        $components = $this->getComponentsByTypes($architecture, ['entity', 'repository', 'service', 'controller']);

        $this->addControllerServiceFlow($architecture, $components);
        $this->addServiceRepositoryFlow($architecture, $components);
        $this->addRepositoryDatabaseFlow($architecture, $components);
        $this->addServiceCacheFlow($architecture, $components);
        $this->addServiceMessageFlow($architecture, $components);
        $this->addDataCollectorFlow($architecture);
    }

    /**
     * @param string[] $types
     * @return array<string, Component[]>
     */
    private function getComponentsByTypes(Architecture $architecture, array $types): array
    {
        $components = [];
        foreach ($types as $type) {
            $components[$type] = $architecture->getComponentsByType($type);
        }

        return $components;
    }

    /** @param array<string, Component[]> $components */
    private function addControllerServiceFlow(Architecture $architecture, array $components): void
    {
        if ([] === $components['controller'] || [] === $components['service']) {
            return;
        }

        $firstController = reset($components['controller']);
        $architecture->addDataFlow(
            'user_request',
            $firstController->getId(),
            'HTTP请求',
            '用户通过浏览器或API客户端发送请求'
        );
    }

    /** @param array<string, Component[]> $components */
    private function addServiceRepositoryFlow(Architecture $architecture, array $components): void
    {
        if ([] === $components['service'] || [] === $components['repository']) {
            return;
        }

        $firstService = reset($components['service']);
        $firstRepository = reset($components['repository']);
        $architecture->addDataFlow(
            $firstService->getId(),
            $firstRepository->getId(),
            '业务数据',
            '服务层处理业务逻辑并访问数据'
        );
    }

    /** @param array<string, Component[]> $components */
    private function addRepositoryDatabaseFlow(Architecture $architecture, array $components): void
    {
        if ([] === $components['repository']) {
            return;
        }

        $firstRepository = reset($components['repository']);
        $architecture->addDataFlow(
            $firstRepository->getId(),
            'main_database',
            'SQL查询',
            'Repository执行数据库查询'
        );
    }

    /** @param array<string, Component[]> $components */
    private function addServiceCacheFlow(Architecture $architecture, array $components): void
    {
        if (null === $architecture->getComponent('cache_layer') || [] === $components['service']) {
            return;
        }

        $firstService = reset($components['service']);
        $architecture->addDataFlow(
            $firstService->getId(),
            'cache_layer',
            '缓存数据',
            '频繁访问的数据缓存到Redis'
        );
    }

    /** @param array<string, Component[]> $components */
    private function addServiceMessageFlow(Architecture $architecture, array $components): void
    {
        if (null === $architecture->getComponent('message_broker') || [] === $components['service']) {
            return;
        }

        $firstService = reset($components['service']);
        $architecture->addDataFlow(
            $firstService->getId(),
            'message_broker',
            '异步消息',
            '将耗时任务发送到消息队列'
        );
    }

    private function addDataCollectorFlow(Architecture $architecture): void
    {
        $architecture->addComponent(new Component(
            'data_collector',
            'etl',
            '数据采集器',
            'PHP ETL',
            '从多个数据源采集数据'
        ));

        $architecture->addDataFlow(
            'external_data_source',
            'data_collector',
            '原始数据',
            '从外部系统采集原始数据'
        );

        $architecture->addDataFlow(
            'data_collector',
            'main_database',
            '清洗后数据',
            '经过ETL处理的结构化数据'
        );
    }

    private function analyzeExternalIntegrations(string $projectPath, Architecture $architecture): void
    {
        $this->analyzeEnvIntegrations($projectPath, $architecture);
        $this->analyzeComposerIntegrations($projectPath, $architecture);
    }

    private function analyzeEnvIntegrations(string $projectPath, Architecture $architecture): void
    {
        $envContent = $this->readFileContent($projectPath . '/.env');
        if (null === $envContent) {
            return;
        }

        $services = $architecture->getComponentsByType('service');

        $this->addPaymentIntegration($envContent, $architecture, $services);
        $this->addMessagingIntegrations($envContent, $architecture);
        $this->addAuthIntegration($envContent, $architecture);
        $this->addCdnIntegration($envContent, $architecture);
    }

    private function readFileContent(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);

        return false === $content ? null : $content;
    }

    private function analyzeComposerIntegrations(string $projectPath, Architecture $architecture): void
    {
        $requires = $this->getComposerRequirements($projectPath);
        if (null === $requires) {
            return;
        }

        $this->addAwsIntegration($requires, $architecture);
        $this->addElasticsearchIntegration($requires, $architecture);
    }

    /** @return array<string, string>|null */
    private function getComposerRequirements(string $projectPath): ?array
    {
        $composerContent = $this->readFileContent($projectPath . '/composer.json');
        if (null === $composerContent) {
            return null;
        }

        $decoded = json_decode($composerContent, true);
        if (!is_array($decoded)) {
            return null;
        }

        return array_merge(
            $decoded['require'] ?? [],
            $decoded['require-dev'] ?? []
        );
    }

    /** @param array<Component> $services */
    private function addPaymentIntegration(string $envContent, Architecture $architecture, array $services): void
    {
        if (!$this->hasPaymentConfig($envContent)) {
            return;
        }

        $architecture->addExternalSystem('payment_gateway', '支付网关', 'payment', 'REST API');
        $architecture->addDataFlow(
            [] !== $services ? reset($services)->getId() : 'payment_service',
            'payment_gateway',
            '支付请求',
            '处理在线支付交易'
        );
    }

    private function addMessagingIntegrations(string $envContent, Architecture $architecture): void
    {
        if ($this->hasSmsConfig($envContent)) {
            $architecture->addExternalSystem('sms_provider', '短信服务商', 'messaging', 'REST API');
            $architecture->addDataFlow(
                'notification_service',
                'sms_provider',
                '短信通知',
                '发送验证码和通知短信'
            );
        }

        if ($this->hasEmailConfig($envContent)) {
            $architecture->addExternalSystem('email_provider', '邮件服务商', 'email', 'SMTP');
            $architecture->addDataFlow(
                'notification_service',
                'email_provider',
                '邮件通知',
                '发送系统邮件通知'
            );
        }
    }

    private function addAuthIntegration(string $envContent, Architecture $architecture): void
    {
        if (!$this->hasOAuthConfig($envContent)) {
            return;
        }

        $architecture->addExternalSystem('oauth_provider', 'OAuth认证服务', 'authentication', 'OAuth 2.0');
        $architecture->addDataFlow(
            'auth_service',
            'oauth_provider',
            '认证请求',
            '第三方登录认证'
        );
    }

    private function addCdnIntegration(string $envContent, Architecture $architecture): void
    {
        if (!$this->hasCdnConfig($envContent)) {
            return;
        }

        $architecture->addExternalSystem('cdn_service', 'CDN服务', 'cdn', 'HTTP/HTTPS');
        $architecture->addInfrastructure('cdn', 'CDN节点', 'cdn', [
            'provider' => 'Cloudflare',
            'locations' => '全球',
        ]);
    }

    /** @param array<string, string> $requires */
    private function addAwsIntegration(array $requires, Architecture $architecture): void
    {
        if (!isset($requires['aws/aws-sdk-php'])) {
            return;
        }

        $architecture->addExternalSystem('aws_services', 'AWS云服务', 'cloud', 'AWS SDK');
        $architecture->addInfrastructure('aws_s3', 'AWS S3存储', 'object_storage', [
            'region' => 'us-east-1',
            'redundancy' => '99.999999999%',
        ]);
    }

    /** @param array<string, string> $requires */
    private function addElasticsearchIntegration(array $requires, Architecture $architecture): void
    {
        if (!isset($requires['elasticsearch/elasticsearch'])) {
            return;
        }

        $architecture->addExternalSystem('elasticsearch', 'Elasticsearch搜索引擎', 'search', 'REST API');
        $architecture->addComponent(new Component(
            'search_engine',
            'external_api',
            '搜索引擎',
            'Elasticsearch',
            '全文搜索和分析'
        ));
    }

    private function hasPaymentConfig(string $envContent): bool
    {
        return $this->hasAnyConfigPattern($envContent, ['PAYMENT_', 'STRIPE_', 'PAYPAL_']);
    }

    private function hasSmsConfig(string $envContent): bool
    {
        return $this->hasAnyConfigPattern($envContent, ['SMS_', 'TWILIO_']);
    }

    private function hasEmailConfig(string $envContent): bool
    {
        return $this->hasAnyConfigPattern($envContent, ['MAIL_', 'SMTP_']);
    }

    private function hasOAuthConfig(string $envContent): bool
    {
        return $this->hasAnyConfigPattern($envContent, ['OAUTH_', 'GOOGLE_', 'FACEBOOK_']);
    }

    private function hasCdnConfig(string $envContent): bool
    {
        return $this->hasAnyConfigPattern($envContent, ['CDN_', 'CLOUDFLARE_']);
    }

    /** @param string[] $patterns */
    private function hasAnyConfigPattern(string $content, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($content, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function analyzeSecurityMeasures(string $projectPath, Architecture $architecture): void
    {
        $this->addBasicSecurityMeasures($architecture);
        $this->addConfigBasedSecurityMeasures($projectPath, $architecture);
        $this->addSecurityComponents($architecture);
    }

    private function addBasicSecurityMeasures(Architecture $architecture): void
    {
        $architecture->addSecurityMeasure('firewall', 'Web应用防火墙', 'WAF', '所有入站流量');
        $architecture->addSecurityMeasure('ssl', 'SSL/TLS加密', 'encryption', 'HTTPS通信');
        $architecture->addSecurityMeasure('auth', '身份认证系统', 'authentication', '用户访问控制');
        $architecture->addSecurityMeasure('rbac', '角色权限控制', 'authorization', '功能访问控制');
        $architecture->addSecurityMeasure('audit', '审计日志系统', 'logging', '所有关键操作');
        $architecture->addSecurityMeasure('backup', '自动备份系统', 'backup', '数据库和文件');
        $architecture->addSecurityMeasure('monitoring', '安全监控系统', 'monitoring', '异常行为检测');
    }

    private function addConfigBasedSecurityMeasures(string $projectPath, Architecture $architecture): void
    {
        $securityContent = $this->readFileContent($projectPath . '/config/packages/security.yaml');
        if (null === $securityContent) {
            return;
        }

        if ($this->hasAnyConfigPattern($securityContent, ['jwt', 'lexik_jwt'])) {
            $architecture->addSecurityMeasure('jwt', 'JWT令牌认证', 'token', 'API访问');
        }

        if ($this->hasAnyConfigPattern($securityContent, ['two_factor', '2fa'])) {
            $architecture->addSecurityMeasure('2fa', '双因素认证', '2FA', '敏感操作');
        }

        if ($this->hasAnyConfigPattern($securityContent, ['rate_limit'])) {
            $architecture->addSecurityMeasure('rate_limit', '请求频率限制', 'rate_limiting', 'API保护');
        }
    }

    private function addSecurityComponents(Architecture $architecture): void
    {
        $architecture->addComponent(new Component(
            'security_manager',
            'auth_service',
            '安全管理器',
            'Symfony Security',
            '统一的安全管理服务'
        ));

        $architecture->addComponent(new Component(
            'encryption_service',
            'encryption',
            '加密服务',
            'OpenSSL/Sodium',
            '敏感数据加密存储'
        ));
    }

    private function analyzeUserAccess(string $projectPath, Architecture $architecture): void
    {
        $this->addWebInterface($projectPath, $architecture);
        $this->addApiInterfaces($projectPath, $architecture);
        $this->addCliInterface($projectPath, $architecture);
    }

    private function addWebInterface(string $projectPath, Architecture $architecture): void
    {
        if (!is_dir($projectPath . '/templates')) {
            return;
        }

        $architecture->addComponent(new Component(
            'web_ui',
            'web_ui',
            'Web用户界面',
            'Twig/HTML5',
            '响应式Web界面'
        ));

        $architecture->addDataFlow(
            'end_user',
            'web_ui',
            '浏览器访问',
            '用户通过浏览器访问系统'
        );
    }

    private function addApiInterfaces(string $projectPath, Architecture $architecture): void
    {
        $routesContent = $this->readFileContent($projectPath . '/config/routes.yaml');
        if (null === $routesContent) {
            return;
        }

        if (str_contains($routesContent, '/api/') || str_contains($routesContent, 'api_platform')) {
            $this->addRestApiComponents($architecture);
        }

        if (str_contains($routesContent, 'graphql')) {
            $this->addGraphqlComponent($architecture);
        }

        if (str_contains($routesContent, '/admin') || str_contains($routesContent, 'easyadmin')) {
            $this->addAdminPortalComponent($architecture);
        }
    }

    private function addRestApiComponents(Architecture $architecture): void
    {
        $architecture->addComponent(new Component(
            'rest_api',
            'rest_api',
            'REST API接口',
            'API Platform/Symfony',
            '提供RESTful API服务'
        ));

        $architecture->addComponent(new Component(
            'mobile_app_interface',
            'mobile_app',
            '移动应用接口',
            'JSON API',
            '为移动应用提供API'
        ));

        $architecture->addDataFlow(
            'mobile_device',
            'mobile_app_interface',
            '移动请求',
            '移动设备访问API'
        );
    }

    private function addGraphqlComponent(Architecture $architecture): void
    {
        $architecture->addComponent(new Component(
            'graphql_api',
            'graphql',
            'GraphQL接口',
            'GraphQL',
            '提供GraphQL查询服务'
        ));
    }

    private function addAdminPortalComponent(Architecture $architecture): void
    {
        $architecture->addComponent(new Component(
            'admin_portal',
            'portal',
            '管理后台',
            'EasyAdmin/Sonata',
            '系统管理界面'
        ));

        $architecture->addDataFlow(
            'admin_user',
            'admin_portal',
            '管理访问',
            '管理员访问后台'
        );
    }

    private function addCliInterface(string $projectPath, Architecture $architecture): void
    {
        if (!is_dir($projectPath . '/bin')) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($projectPath . '/bin')->name('console');

        if (!$finder->hasResults()) {
            return;
        }

        $architecture->addComponent(new Component(
            'cli_interface',
            'terminal',
            '命令行界面',
            'Symfony Console',
            '提供CLI管理工具'
        ));

        $architecture->addDataFlow(
            'system_admin',
            'cli_interface',
            'SSH/终端',
            '系统管理员通过命令行管理'
        );
    }

    private function analyzeManagementFeatures(string $projectPath, Architecture $architecture): void
    {
        $this->addManagementComponents($architecture);
        $this->addCiCdPipeline($projectPath, $architecture);
        $this->addManagementDataFlows($architecture);
    }

    private function addManagementComponents(Architecture $architecture): void
    {
        $architecture->addComponent(new Component('config_manager', 'config_service', '配置管理', 'Symfony Config', '系统配置管理服务'));
        $architecture->addComponent(new Component('log_aggregator', 'logging_service', '日志聚合', 'Monolog', '集中式日志管理'));
        $architecture->addComponent(new Component('health_monitor', 'monitoring_service', '健康监控', 'Prometheus/Grafana', '系统健康状态监控'));
        $architecture->addComponent(new Component('deployment_manager', 'deployment', '部署管理', 'CI/CD Pipeline', '自动化部署管理'));
        $architecture->addComponent(new Component('backup_manager', 'backup', '备份管理', 'Backup Script', '定期备份和恢复'));
    }

    private function addCiCdPipeline(string $projectPath, Architecture $architecture): void
    {
        $gitlabCi = file_exists($projectPath . '/.gitlab-ci.yml');
        $githubActions = is_dir($projectPath . '/.github/workflows');

        if (!$gitlabCi && !$githubActions) {
            return;
        }

        $provider = $gitlabCi ? 'GitLab CI' : 'GitHub Actions';
        $architecture->addComponent(new Component(
            'cicd_pipeline',
            'deployment',
            'CI/CD流水线',
            $provider,
            '自动化构建和部署'
        ));

        $architecture->addDataFlow(
            'cicd_pipeline',
            'deployment_manager',
            '部署指令',
            '自动化部署流程'
        );
    }

    private function addManagementDataFlows(Architecture $architecture): void
    {
        $architecture->addDataFlow(
            'health_monitor',
            'log_aggregator',
            '监控数据',
            '收集系统指标和日志'
        );

        $architecture->addDataFlow(
            'backup_manager',
            'backup_storage',
            '备份数据',
            '定期备份到远程存储'
        );
    }
}
