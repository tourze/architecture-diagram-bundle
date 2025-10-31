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
use Tourze\ArchitectureDiagramBundle\Generator\PlantUMLGenerator;
use Tourze\ArchitectureDiagramBundle\Model\Architecture;
use Tourze\ArchitectureDiagramBundle\Scanner\ProjectScanner;

#[AsCommand(
    name: 'app:generate-architecture-diagram',
    description: '生成项目架构图（PlantUML格式）',
)]
class GenerateArchitectureDiagramCommand extends Command
{
    public function __construct(
        private readonly ProjectScanner $projectScanner,
        private readonly PlantUMLGenerator $generator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('project', InputArgument::REQUIRED, '项目路径或名称')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, '输出文件路径', null)
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, '输出格式 (plantuml)', 'plantuml')
            ->addOption('level', 'l', InputOption::VALUE_OPTIONAL, 'C4层级 (context|container|component|code)', 'component')
            ->addOption('no-c4', null, InputOption::VALUE_NONE, '不使用C4 Model格式')
            ->addOption('no-layers', null, InputOption::VALUE_NONE, '不按层分组')
            ->addOption('show-stats', null, InputOption::VALUE_NONE, '显示架构统计信息')
            ->setHelp(<<<'HELP'
                该命令扫描 Symfony 项目并生成架构图。

                示例用法:

                  生成 symfony-easy-admin-demo 的架构图：
                  <info>%command.full_name% projects/symfony-easy-admin-demo</info>

                  指定输出文件：
                  <info>%command.full_name% projects/symfony-easy-admin-demo -o architecture.puml</info>

                  生成简单格式（非C4）：
                  <info>%command.full_name% projects/symfony-easy-admin-demo --no-c4</info>

                  不按层分组：
                  <info>%command.full_name% projects/symfony-easy-admin-demo --no-layers</info>

                  显示统计信息：
                  <info>%command.full_name% projects/symfony-easy-admin-demo --show-stats</info>
                HELP
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectPath = $this->resolveProjectPath($input->getArgument('project'));

        if (!$this->validateProjectPath($projectPath, $io)) {
            return Command::FAILURE;
        }

        $this->displayHeader($io, $projectPath);

        try {
            return $this->generateArchitecture($input, $io, $projectPath);
        } catch (\Exception $e) {
            return $this->handleError($io, $output, $e);
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

    private function validateProjectPath(string $projectPath, SymfonyStyle $io): bool
    {
        if (is_dir($projectPath)) {
            return true;
        }

        $io->error("项目路径不存在: {$projectPath}");

        return false;
    }

    private function displayHeader(SymfonyStyle $io, string $projectPath): void
    {
        $io->title('架构图生成器');
        $io->text("正在扫描项目: {$projectPath}");
    }

    private function generateArchitecture(InputInterface $input, SymfonyStyle $io, string $projectPath): int
    {
        $architecture = $this->projectScanner->scan($projectPath);

        if (!$architecture->hasComponents()) {
            $io->warning('未找到任何组件。请确保项目包含 Controller、Entity、Repository 或 Service。');

            return Command::SUCCESS;
        }

        if (false !== $input->getOption('show-stats')) {
            $this->displayStatistics($io, $architecture);
        }

        $options = $this->buildGenerationOptions($input);
        $plantUML = $this->generator->generate($architecture, $options);
        $this->outputResult($input, $io, $plantUML);

        return Command::SUCCESS;
    }

    /** @return array{level: string, include_c4: bool, group_by_layer: bool} */
    private function buildGenerationOptions(InputInterface $input): array
    {
        return [
            'level' => $input->getOption('level') ?? 'component',
            'include_c4' => false === $input->getOption('no-c4'),
            'group_by_layer' => false === $input->getOption('no-layers'),
        ];
    }

    private function outputResult(InputInterface $input, SymfonyStyle $io, string $plantUML): void
    {
        $outputFile = $input->getOption('output');

        if (null !== $outputFile) {
            $this->saveToFile($outputFile, $plantUML, $io);
        } else {
            $this->displayInConsole($io, $plantUML);
        }
    }

    private function saveToFile(string $outputFile, string $plantUML, SymfonyStyle $io): void
    {
        file_put_contents($outputFile, $plantUML);
        $io->success("架构图已生成到: {$outputFile}");
        $this->displayUsageInstructions($io, $outputFile);
    }

    private function displayInConsole(SymfonyStyle $io, string $plantUML): void
    {
        $io->section('生成的 PlantUML 代码');
        $io->text($plantUML);
    }

    private function displayUsageInstructions(SymfonyStyle $io, string $outputFile): void
    {
        $io->section('使用说明');
        $io->listing([
            '使用 PlantUML 工具或在线编辑器查看生成的图表',
            '在线编辑器: https://www.plantuml.com/plantuml/uml/',
            '本地查看: plantuml ' . $outputFile,
        ]);
    }

    private function handleError(SymfonyStyle $io, OutputInterface $output, \Exception $e): int
    {
        $io->error('生成架构图时发生错误: ' . $e->getMessage());

        if ($output->isVerbose()) {
            $io->text($e->getTraceAsString());
        }

        return Command::FAILURE;
    }

    private function displayStatistics(SymfonyStyle $io, Architecture $architecture): void
    {
        $stats = $architecture->getStatistics();

        $io->section('架构统计信息');

        $io->table(
            ['指标', '数量'],
            [
                ['组件总数', $stats['total_components']],
                ['关系总数', $stats['total_relations']],
            ]
        );

        if ([] !== $stats['components_by_type']) {
            $io->text('组件类型分布:');
            $rows = [];
            foreach ($stats['components_by_type'] as $type => $count) {
                $rows[] = [ucfirst($type), $count];
            }
            $io->table(['类型', '数量'], $rows);
        }

        if ([] !== $stats['components_by_layer']) {
            $io->text('层级分布:');
            $rows = [];
            foreach ($stats['components_by_layer'] as $layer => $count) {
                $rows[] = [ucfirst($layer), $count];
            }
            $io->table(['层级', '数量'], $rows);
        }
    }
}
