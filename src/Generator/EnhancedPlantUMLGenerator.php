<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Generator;

use Tourze\ArchitectureDiagramBundle\Model\Architecture;
use Tourze\ArchitectureDiagramBundle\Model\Component;

class EnhancedPlantUMLGenerator
{
    /** @var array<string, string> */
    private array $layerColors = [
        'user' => '#e6f3ff',
        'presentation' => '#ffe6e6',
        'application' => '#fff0e6',
        'domain' => '#e6ffe6',
        'infrastructure' => '#f0e6ff',
        'data' => '#e6ffff',
        'integration' => '#ffffe6',
        'hardware' => '#d9d9d9',
        'security' => '#ffe6f0',
        'management' => '#f0ffe6',
    ];

    /** @param array<string, mixed> $options */
    public function generateSystemOverview(Architecture $architecture, array $options = []): string
    {
        $output = [];
        $output[] = '@startuml';
        $output[] = '!include https://raw.githubusercontent.com/plantuml-stdlib/C4-PlantUML/master/C4_Context.puml';
        $output[] = '';
        $output[] = 'LAYOUT_WITH_LEGEND()';
        $output[] = 'title ' . $architecture->getName();
        $output[] = '';

        $output = array_merge($output, $this->generateHardwareInfrastructure($architecture));
        $output = array_merge($output, $this->generateSystemBoundary($architecture));
        $output = array_merge($output, $this->generateExternalSystems($architecture));
        $output = array_merge($output, $this->generateDataFlows($architecture));
        $output = array_merge($output, $this->generateSecurityBoundary($architecture));

        $output[] = '@enduml';

        return implode(PHP_EOL, $output);
    }

    /** @param array<string, mixed> $options */
    public function generateLayeredArchitecture(Architecture $architecture, array $options = []): string
    {
        $output = [];
        $output[] = '@startuml';
        $output[] = '!include https://raw.githubusercontent.com/plantuml-stdlib/C4-PlantUML/master/C4_Component.puml';
        $output[] = '';
        $output[] = 'LAYOUT_TOP_DOWN()';
        $output[] = 'title 分层架构图';
        $output[] = '';

        $layers = $architecture->getLayers();
        $componentsByLayer = $this->groupComponentsByLayer($architecture);

        $layerOrder = ['user', 'presentation', 'application', 'domain', 'infrastructure', 'data', 'integration'];

        foreach ($layerOrder as $layerName) {
            if (!isset($componentsByLayer[$layerName]) || [] === $componentsByLayer[$layerName]) {
                continue;
            }

            $color = $this->layerColors[$layerName] ?? '#f0f0f0';
            $output[] = sprintf('Container_Boundary(%s_layer, "%s 层", "%s") {', $layerName, $this->getLayerChineseName($layerName), $color);

            foreach ($componentsByLayer[$layerName] as $component) {
                $output = array_merge($output, $this->generateLayeredComponent($component, '    '));
            }

            $output[] = '}';
            $output[] = '';
        }

        $output = array_merge($output, $this->generateLayerRelations($architecture));

        $output[] = '@enduml';

        return implode(PHP_EOL, $output);
    }

    /** @param array<string, mixed> $options */
    public function generateDataFlowDiagram(Architecture $architecture, array $options = []): string
    {
        $output = [];
        $output[] = '@startuml';
        $output[] = 'skinparam componentStyle rectangle';
        $output[] = 'title 数据流图';
        $output[] = '';

        $output[] = 'package "数据源" {';
        $output = array_merge($output, $this->generateDataSources($architecture, '    '));
        $output[] = '}';
        $output[] = '';

        $output[] = 'package "数据采集" {';
        $output = array_merge($output, $this->generateDataCollectors($architecture, '    '));
        $output[] = '}';
        $output[] = '';

        $output[] = 'package "数据处理" {';
        $output = array_merge($output, $this->generateDataProcessors($architecture, '    '));
        $output[] = '}';
        $output[] = '';

        $output[] = 'package "数据存储" {';
        $output = array_merge($output, $this->generateDataStorage($architecture, '    '));
        $output[] = '}';
        $output[] = '';

        $output[] = 'package "数据交换" {';
        $output = array_merge($output, $this->generateDataExchange($architecture, '    '));
        $output[] = '}';
        $output[] = '';

        $output = array_merge($output, $this->generateDataFlowRelations($architecture));

        $output[] = '@enduml';

        return implode(PHP_EOL, $output);
    }

    /** @param array<string, mixed> $options */
    public function generateDeploymentDiagram(Architecture $architecture, array $options = []): string
    {
        $output = [];
        $output[] = '@startuml';
        $output[] = '!include https://raw.githubusercontent.com/plantuml-stdlib/C4-PlantUML/master/C4_Deployment.puml';
        $output[] = '';
        $output[] = 'title 部署架构图';
        $output[] = '';

        $output[] = 'Deployment_Node(prod, "生产环境") {';
        $output = array_merge($output, $this->generateProductionEnvironment($architecture, '    '));
        $output[] = '}';
        $output[] = '';

        $output[] = 'Deployment_Node(dr, "灾备环境") {';
        $output = array_merge($output, $this->generateDisasterRecovery($architecture, '    '));
        $output[] = '}';
        $output[] = '';

        $output = array_merge($output, $this->generateDeploymentRelations($architecture));

        $output[] = '@enduml';

        return implode(PHP_EOL, $output);
    }

    /** @return array<string> */
    private function generateHardwareInfrastructure(Architecture $architecture): array
    {
        $infrastructures = $architecture->getInfrastructures();
        if ([] === $infrastructures) {
            return [];
        }

        $output = [];
        $output[] = 'Boundary(hardware, "硬件基础设施") {';
        foreach ($infrastructures as $id => $infra) {
            $specs = [] !== $infra['properties'] ? implode(', ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($infra['properties']), $infra['properties'])) : '';
            $output[] = sprintf('    System(%s, "%s", "%s")', $id, $infra['name'], $specs);
        }
        $output[] = '}';
        $output[] = '';

        return $output;
    }

    /** @return array<string> */
    private function generateSystemBoundary(Architecture $architecture): array
    {
        $output = [];
        $output[] = 'Boundary(system, "核心系统") {';

        $functionalModules = $architecture->getComponentsByType('service');
        if ([] !== $functionalModules) {
            foreach ($functionalModules as $module) {
                $output[] = sprintf(
                    '    Container(%s, "%s", "%s")',
                    $this->sanitizeId($module->getId()),
                    $module->getName(),
                    $module->getDescription()
                );
            }
        }

        $output[] = '}';
        $output[] = '';

        return $output;
    }

    /** @return array<string> */
    private function generateExternalSystems(Architecture $architecture): array
    {
        $externalSystems = $architecture->getExternalSystems();
        if ([] === $externalSystems) {
            return [];
        }

        $output = [];
        foreach ($externalSystems as $id => $system) {
            $output[] = sprintf('System_Ext(%s, "%s", "%s")', $id, $system['name'], $system['technology']);
        }
        $output[] = '';

        return $output;
    }

    /** @return array<string> */
    private function generateDataFlows(Architecture $architecture): array
    {
        $output = [];
        $dataFlows = $architecture->getDataFlows();
        foreach ($dataFlows as $flow) {
            $output[] = sprintf(
                'Rel(%s, %s, "%s", "%s")',
                $this->sanitizeId($flow['from']),
                $this->sanitizeId($flow['to']),
                $flow['data'],
                '' !== $flow['protocol'] ? $flow['protocol'] : 'N/A'
            );
        }

        return $output;
    }

    /** @return array<string> */
    private function generateSecurityBoundary(Architecture $architecture): array
    {
        $securityMeasures = $architecture->getSecurityMeasures();
        if ([] === $securityMeasures) {
            return [];
        }

        $output = [];
        $output[] = '';
        $output[] = 'Boundary(security, "安全保障体系", "安全边界") {';
        foreach ($securityMeasures as $id => $measure) {
            $output[] = sprintf(
                '    System(%s, "%s", "%s 范围: %s")',
                $id,
                $measure['name'],
                $measure['type'],
                $measure['scope']
            );
        }
        $output[] = '}';

        return $output;
    }

    /** @return array<string, array<Component>> */
    private function groupComponentsByLayer(Architecture $architecture): array
    {
        $componentsByLayer = [];
        foreach ($architecture->getComponents() as $component) {
            $layer = $architecture->getLayerForType($component->getType()) ?? 'other';
            $componentsByLayer[$layer][] = $component;
        }

        return $componentsByLayer;
    }

    /** @return array<string> */
    private function generateLayeredComponent(Component $component, string $indent): array
    {
        $id = $this->sanitizeId($component->getId());
        $name = $component->getName();
        $type = $component->getType();
        $technology = $component->getTechnology();
        $description = $this->truncateDescription($component->getDescription());

        return [sprintf(
            '%sComponent(%s, "%s", "%s", "%s")',
            $indent,
            $id,
            $name,
            $technology,
            $description
        )];
    }

    /** @return array<string> */
    private function generateLayerRelations(Architecture $architecture): array
    {
        $output = [];
        foreach ($architecture->getRelations() as $relation) {
            $from = $this->sanitizeId($relation->getFrom());
            $to = $this->sanitizeId($relation->getTo());
            $description = $relation->getDescription();
            $technology = $relation->getTechnology();

            if ('' !== $technology) {
                $output[] = sprintf('Rel(%s, %s, "%s", "%s")', $from, $to, $description, $technology);
            } else {
                $output[] = sprintf('Rel(%s, %s, "%s")', $from, $to, $description);
            }
        }

        return $output;
    }

    /** @return array<string> */
    private function generateDataSources(Architecture $architecture, string $indent): array
    {
        $dataSources = array_filter($architecture->getComponents(), function ($c) {
            return in_array($c->getType(), ['external_api', 'webhook', 'file_storage'], true);
        });

        $output = [];
        foreach ($dataSources as $source) {
            $output[] = sprintf(
                '%s[%s] as %s <<datasource>>',
                $indent,
                $source->getName(),
                $this->sanitizeId($source->getId())
            );
        }

        return $output;
    }

    /** @return array<string> */
    private function generateDataCollectors(Architecture $architecture, string $indent): array
    {
        $collectors = array_filter($architecture->getComponents(), function ($c) {
            return in_array($c->getType(), ['etl', 'sync_service', 'connector'], true);
        });

        $output = [];
        foreach ($collectors as $collector) {
            $output[] = sprintf(
                '%s[%s] as %s <<collector>>',
                $indent,
                $collector->getName(),
                $this->sanitizeId($collector->getId())
            );
        }

        return $output;
    }

    /** @return array<string> */
    private function generateDataProcessors(Architecture $architecture, string $indent): array
    {
        $processors = array_filter($architecture->getComponents(), function ($c) {
            return in_array($c->getType(), ['service', 'handler', 'workflow'], true);
        });

        $output = [];
        foreach ($processors as $processor) {
            $output[] = sprintf(
                '%s[%s] as %s <<processor>>',
                $indent,
                $processor->getName(),
                $this->sanitizeId($processor->getId())
            );
        }

        return $output;
    }

    /** @return array<string> */
    private function generateDataStorage(Architecture $architecture, string $indent): array
    {
        $storage = array_filter($architecture->getComponents(), function ($c) {
            return in_array($c->getType(), ['database', 'data_warehouse', 'data_lake', 'cache'], true);
        });

        $output = [];
        foreach ($storage as $store) {
            $output[] = sprintf(
                '%sdatabase %s as %s',
                $indent,
                $store->getName(),
                $this->sanitizeId($store->getId())
            );
        }

        return $output;
    }

    /** @return array<string> */
    private function generateDataExchange(Architecture $architecture, string $indent): array
    {
        $exchanges = array_filter($architecture->getComponents(), function ($c) {
            return in_array($c->getType(), ['message_broker', 'queue', 'gateway'], true);
        });

        $output = [];
        foreach ($exchanges as $exchange) {
            $output[] = sprintf(
                '%s[%s] as %s <<exchange>>',
                $indent,
                $exchange->getName(),
                $this->sanitizeId($exchange->getId())
            );
        }

        return $output;
    }

    /** @return array<string> */
    private function generateDataFlowRelations(Architecture $architecture): array
    {
        $output = [];
        foreach ($architecture->getDataFlows() as $flow) {
            $output[] = sprintf(
                '%s --> %s : %s',
                $this->sanitizeId($flow['from']),
                $this->sanitizeId($flow['to']),
                $flow['data']
            );
        }

        return $output;
    }

    /** @return array<string> */
    private function generateProductionEnvironment(Architecture $architecture, string $indent): array
    {
        $output = [];
        $output[] = $indent . 'Deployment_Node(web, "Web服务器集群", "Nginx + PHP-FPM") {';
        $output[] = $indent . '    Container(web_app, "Web应用", "Symfony")';
        $output[] = $indent . '}';
        $output[] = '';

        $output[] = $indent . 'Deployment_Node(app, "应用服务器集群", "Docker Swarm") {';
        $output[] = $indent . '    Container(api_service, "API服务", "REST/GraphQL")';
        $output[] = $indent . '    Container(worker, "后台任务", "Messenger")';
        $output[] = $indent . '}';
        $output[] = '';

        $output[] = $indent . 'Deployment_Node(db, "数据库集群", "MySQL Cluster") {';
        $output[] = $indent . '    ContainerDb(primary_db, "主数据库", "MySQL 8.0")';
        $output[] = $indent . '    ContainerDb(replica_db, "从数据库", "MySQL 8.0")';
        $output[] = $indent . '}';
        $output[] = '';

        $output[] = $indent . 'Deployment_Node(cache, "缓存集群", "Redis Sentinel") {';
        $output[] = $indent . '    Container(redis_master, "Redis主节点", "Redis 7.0")';
        $output[] = $indent . '    Container(redis_slave, "Redis从节点", "Redis 7.0")';
        $output[] = $indent . '}';

        return $output;
    }

    /** @return array<string> */
    private function generateDisasterRecovery(Architecture $architecture, string $indent): array
    {
        $output = [];
        $output[] = $indent . 'Deployment_Node(dr_db, "灾备数据库", "MySQL") {';
        $output[] = $indent . '    ContainerDb(dr_database, "灾备库", "MySQL 8.0")';
        $output[] = $indent . '}';
        $output[] = '';

        $output[] = $indent . 'Deployment_Node(backup, "备份存储", "对象存储") {';
        $output[] = $indent . '    Container(backup_storage, "备份数据", "S3兼容")';
        $output[] = $indent . '}';

        return $output;
    }

    /** @return array<string> */
    private function generateDeploymentRelations(Architecture $architecture): array
    {
        return [
            'Rel(web_app, api_service, "调用", "HTTPS")',
            'Rel(api_service, primary_db, "读写", "MySQL协议")',
            'Rel(api_service, replica_db, "只读", "MySQL协议")',
            'Rel(api_service, redis_master, "缓存", "Redis协议")',
            'Rel(worker, primary_db, "处理任务", "MySQL协议")',
            'Rel(primary_db, replica_db, "主从复制", "MySQL Replication")',
            'Rel(primary_db, dr_database, "异步复制", "MySQL Replication")',
            'Rel(redis_master, redis_slave, "主从复制", "Redis Replication")',
            'Rel(primary_db, backup_storage, "定时备份", "S3 API")',
        ];
    }

    private function getLayerChineseName(string $layer): string
    {
        $names = [
            'user' => '用户接入',
            'presentation' => '展示',
            'application' => '应用',
            'domain' => '领域',
            'infrastructure' => '基础设施',
            'data' => '数据',
            'integration' => '集成',
            'hardware' => '硬件',
            'security' => '安全',
            'management' => '管理',
        ];

        return $names[$layer] ?? ucfirst($layer);
    }

    private function sanitizeId(string $id): string
    {
        $result = preg_replace('/[^a-zA-Z0-9_]/', '_', $id);

        return null !== $result ? $result : $id;
    }

    private function truncateDescription(string $description, int $maxLength = 50): string
    {
        if (mb_strlen($description) <= $maxLength) {
            return $description;
        }

        return mb_substr($description, 0, $maxLength - 3) . '...';
    }
}
