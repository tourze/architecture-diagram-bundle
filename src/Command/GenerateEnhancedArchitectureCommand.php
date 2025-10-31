<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ArchitectureDiagramBundle\Generator\EnhancedPlantUMLGenerator;
use Tourze\ArchitectureDiagramBundle\Model\Architecture;
use Tourze\ArchitectureDiagramBundle\Scanner\ControllerScanner;
use Tourze\ArchitectureDiagramBundle\Scanner\EnhancedArchitectureScanner;
use Tourze\ArchitectureDiagramBundle\Scanner\EntityScanner;
use Tourze\ArchitectureDiagramBundle\Scanner\EventListenerScanner;
use Tourze\ArchitectureDiagramBundle\Scanner\RelationAnalyzer;
use Tourze\ArchitectureDiagramBundle\Scanner\RepositoryScanner;
use Tourze\ArchitectureDiagramBundle\Scanner\ServiceScanner;

#[AsCommand(
    name: 'app:generate-enhanced-architecture',
    description: '生成增强的系统架构图（包含硬件、数据流、安全等多维度）',
)]
class GenerateEnhancedArchitectureCommand extends Command
{
    private EnhancedArchitectureScanner $scanner;

    private EnhancedPlantUMLGenerator $generator;

    public function __construct()
    {
        parent::__construct();

        $controllerScanner = new ControllerScanner();
        $entityScanner = new EntityScanner();
        $repositoryScanner = new RepositoryScanner();
        $serviceScanner = new ServiceScanner();
        $eventListenerScanner = new EventListenerScanner();
        $relationAnalyzer = new RelationAnalyzer();

        $this->scanner = new EnhancedArchitectureScanner(
            $controllerScanner,
            $entityScanner,
            $repositoryScanner,
            $serviceScanner,
            $eventListenerScanner,
            $relationAnalyzer
        );

        $this->generator = new EnhancedPlantUMLGenerator();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('project', InputArgument::REQUIRED, '项目路径或名称')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, '架构图类型 (overview|layered|dataflow|deployment|all)', 'all')
            ->addOption('output-dir', 'o', InputOption::VALUE_OPTIONAL, '输出目录', null)
            ->addOption('show-stats', null, InputOption::VALUE_NONE, '显示详细统计信息')
            ->setHelp(<<<'HELP'
                生成增强的系统架构图，包含多个维度的分析。

                架构图类型说明：
                - <comment>overview</comment>: 系统总体架构图（硬件、外部系统、安全边界）
                - <comment>layered</comment>: 分层架构图（用户层、展示层、应用层、领域层等）
                - <comment>dataflow</comment>: 数据流图（数据采集、处理、存储、交换）
                - <comment>deployment</comment>: 部署架构图（生产环境、灾备环境）
                - <comment>all</comment>: 生成所有类型的架构图

                示例用法:

                  生成所有架构图：
                  <info>%command.full_name% projects/symfony-easy-admin-demo</info>

                  只生成系统总体架构图：
                  <info>%command.full_name% projects/symfony-easy-admin-demo -t overview</info>

                  指定输出目录：
                  <info>%command.full_name% projects/symfony-easy-admin-demo -o diagrams/</info>

                  显示统计信息：
                  <info>%command.full_name% projects/symfony-easy-admin-demo --show-stats</info>

                生成的架构图将回答以下问题：
                ✓ 整个系统的硬件设置是怎么回事？
                ✓ 数据大概是从哪里来，怎么采集、存储、处理、交换的？
                ✓ 做了哪些功能抽象，以便于支撑上层的应用？
                ✓ 提供哪些业务应用？
                ✓ 管理、控制等功能有哪些？
                ✓ 终端用户怎么访问和使用这些应用？
                ✓ 该系统与外部系统是怎么进行对接的？
                ✓ 如何保障整个系统的安全、可靠、高质量的建设？
                HELP
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectPath = $this->resolveProjectPath($input->getArgument('project'));

        if (!is_dir($projectPath)) {
            $io->error("项目路径不存在: {$projectPath}");

            return Command::FAILURE;
        }

        $io->title('增强架构图生成器');
        $io->text("正在深度扫描项目: {$projectPath}");
        $io->progressStart(5);

        try {
            $io->progressAdvance();
            $architecture = $this->scanner->scanProject($projectPath);

            if (true === $input->getOption('show-stats')) {
                $this->displayDetailedStatistics($io, $architecture);
            }

            $type = $input->getOption('type');
            $outputDir = $input->getOption('output-dir') ?? $projectPath;

            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0o755, true);
            }

            $diagrams = $this->generateDiagrams($architecture, $type, $io);

            foreach ($diagrams as $name => $content) {
                $filename = $outputDir . '/' . $name . '.puml';
                file_put_contents($filename, $content);
                $io->progressAdvance();
                $io->text("✓ 生成 {$name} 架构图");
            }

            $io->progressFinish();

            $io->success(sprintf('成功生成 %d 个架构图到: %s', count($diagrams), $outputDir));

            $this->displayUsageInstructions($io, $outputDir, array_keys($diagrams));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->progressFinish();
            $io->error('生成架构图时发生错误: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function resolveProjectPath(string $projectPath): string
    {
        if (str_starts_with($projectPath, '/')) {
            return $projectPath;
        }

        $possiblePaths = [
            getcwd() . '/' . $projectPath,
            getcwd() . '/projects/' . $projectPath,
            getcwd() . '/packages/' . $projectPath,
        ];

        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }

        return $projectPath;
    }

    /** @return array<string, string> */
    private function generateDiagrams(Architecture $architecture, string $type, SymfonyStyle $io): array
    {
        $diagrams = [];

        switch ($type) {
            case 'overview':
                $diagrams['architecture-overview'] = $this->generator->generateSystemOverview($architecture);
                break;
            case 'layered':
                $diagrams['architecture-layered'] = $this->generator->generateLayeredArchitecture($architecture);
                break;
            case 'dataflow':
                $diagrams['architecture-dataflow'] = $this->generator->generateDataFlowDiagram($architecture);
                break;
            case 'deployment':
                $diagrams['architecture-deployment'] = $this->generator->generateDeploymentDiagram($architecture);
                break;
            case 'all':
            default:
                $diagrams['architecture-overview'] = $this->generator->generateSystemOverview($architecture);
                $diagrams['architecture-layered'] = $this->generator->generateLayeredArchitecture($architecture);
                $diagrams['architecture-dataflow'] = $this->generator->generateDataFlowDiagram($architecture);
                $diagrams['architecture-deployment'] = $this->generator->generateDeploymentDiagram($architecture);
                break;
        }

        return $diagrams;
    }

    private function displayDetailedStatistics(SymfonyStyle $io, Architecture $architecture): void
    {
        $stats = $architecture->getStatistics();

        $io->section('📊 架构统计信息');

        $this->displayBasicStats($io, $stats);
        $this->displayLayerDistribution($io, $stats);
        $this->displayTypeDistribution($io, $stats);
        $this->displayInfrastructures($io, $architecture);
        $this->displayExternalSystems($io, $architecture);
        $this->displaySecurityMeasures($io, $architecture);
    }

    /** @param array<string, mixed> $stats */
    private function displayBasicStats(SymfonyStyle $io, array $stats): void
    {
        $io->table(
            ['指标', '数量'],
            [
                ['🔧 组件总数', $stats['total_components']],
                ['🔗 关系总数', $stats['total_relations']],
                ['📊 数据流总数', $stats['total_data_flows'] ?? 0],
                ['🌐 外部系统总数', $stats['total_external_systems'] ?? 0],
                ['🖥️ 基础设施总数', $stats['total_infrastructures'] ?? 0],
                ['🔒 安全措施总数', $stats['total_security_measures'] ?? 0],
            ]
        );
    }

    /** @param array<string, mixed> $stats */
    private function displayLayerDistribution(SymfonyStyle $io, array $stats): void
    {
        if ([] === $stats['components_by_layer']) {
            return;
        }

        $io->text('📦 层级分布:');
        $rows = [];
        foreach ($stats['components_by_layer'] as $layer => $count) {
            $emoji = $this->getLayerEmoji($layer);
            $rows[] = ["{$emoji} " . ucfirst($layer), $count];
        }
        $io->table(['层级', '组件数'], $rows);
    }

    /** @param array<string, mixed> $stats */
    private function displayTypeDistribution(SymfonyStyle $io, array $stats): void
    {
        if ([] === $stats['components_by_type']) {
            return;
        }

        $io->text('🏷️ 组件类型分布:');
        $typeRows = [];
        foreach ($stats['components_by_type'] as $type => $count) {
            $typeRows[] = [ucfirst(str_replace('_', ' ', $type)), $count];
        }
        $io->table(['类型', '数量'], $typeRows);
    }

    private function displayInfrastructures(SymfonyStyle $io, Architecture $architecture): void
    {
        $infrastructures = $architecture->getInfrastructures();
        if ([] === $infrastructures) {
            return;
        }

        $io->text('🖥️ 基础设施:');
        $infraRows = [];
        foreach ($infrastructures as $id => $infra) {
            $specs = $this->formatInfraSpecs($infra);
            $infraRows[] = [$infra['name'], $infra['type'], $specs];
        }
        $io->table(['名称', '类型', '规格'], $infraRows);
    }

    /** @param array<string, mixed> $infra */
    private function formatInfraSpecs(array $infra): string
    {
        if (!isset($infra['properties']) || [] === $infra['properties']) {
            return '-';
        }

        return implode(', ', array_map(
            fn ($k, $v) => "{$k}: {$v}",
            array_keys($infra['properties']),
            $infra['properties']
        ));
    }

    private function displayExternalSystems(SymfonyStyle $io, Architecture $architecture): void
    {
        $externalSystems = $architecture->getExternalSystems();
        if ([] === $externalSystems) {
            return;
        }

        $io->text('🌐 外部系统集成:');
        $extRows = [];
        foreach ($externalSystems as $id => $system) {
            $extRows[] = [$system['name'], $system['type'], $system['technology']];
        }
        $io->table(['系统', '类型', '技术'], $extRows);
    }

    private function displaySecurityMeasures(SymfonyStyle $io, Architecture $architecture): void
    {
        $securityMeasures = $architecture->getSecurityMeasures();
        if ([] === $securityMeasures) {
            return;
        }

        $io->text('🔒 安全措施:');
        $secRows = [];
        foreach ($securityMeasures as $id => $measure) {
            $secRows[] = [$measure['name'], $measure['type'], $measure['scope']];
        }
        $io->table(['措施', '类型', '范围'], $secRows);
    }

    private function getLayerEmoji(string $layer): string
    {
        $emojis = [
            'user' => '👤',
            'presentation' => '🖼️',
            'application' => '⚙️',
            'domain' => '💼',
            'infrastructure' => '🏗️',
            'data' => '💾',
            'integration' => '🔗',
            'hardware' => '🖥️',
            'security' => '🔒',
            'management' => '📋',
        ];

        return $emojis[$layer] ?? '📦';
    }

    /** @param array<string> $diagrams */
    private function displayUsageInstructions(SymfonyStyle $io, string $outputDir, array $diagrams): void
    {
        $io->section('📖 使用说明');

        $io->text('生成的架构图文件:');
        $io->listing(array_map(fn ($name) => "{$outputDir}/{$name}.puml", $diagrams));

        $io->text('查看架构图的方法:');
        $io->listing([
            '在线查看: 将 .puml 文件内容复制到 https://www.plantuml.com/plantuml/uml/',
            'VS Code: 安装 PlantUML 插件后可直接预览',
            '命令行: plantuml -tpng ' . $outputDir . '/*.puml',
            'IntelliJ IDEA: 安装 PlantUML Integration 插件',
        ]);

        $io->text('架构图说明:');
        $descriptions = [
            'architecture-overview' => '系统总体架构 - 展示硬件设施、核心系统、外部集成和安全边界',
            'architecture-layered' => '分层架构 - 展示系统的层次结构和组件分布',
            'architecture-dataflow' => '数据流图 - 展示数据的采集、处理、存储和交换过程',
            'architecture-deployment' => '部署架构 - 展示生产环境和灾备环境的部署结构',
        ];

        foreach ($diagrams as $name) {
            if (isset($descriptions[$name])) {
                $io->text("  • {$name}: {$descriptions[$name]}");
            }
        }
    }
}
