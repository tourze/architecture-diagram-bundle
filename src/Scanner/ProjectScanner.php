<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Scanner;

use Tourze\ArchitectureDiagramBundle\Exception\InvalidProjectPathException;
use Tourze\ArchitectureDiagramBundle\Model\Architecture;

class ProjectScanner
{
    private EntityScanner $entityScanner;

    private ControllerScanner $controllerScanner;

    private RepositoryScanner $repositoryScanner;

    private ServiceScanner $serviceScanner;

    private EventListenerScanner $eventListenerScanner;

    private RelationAnalyzer $relationAnalyzer;

    public function __construct()
    {
        $this->entityScanner = new EntityScanner();
        $this->controllerScanner = new ControllerScanner();
        $this->repositoryScanner = new RepositoryScanner();
        $this->serviceScanner = new ServiceScanner();
        $this->eventListenerScanner = new EventListenerScanner();
        $this->relationAnalyzer = new RelationAnalyzer();
    }

    public function scan(string $projectPath): Architecture
    {
        if (!is_dir($projectPath)) {
            throw InvalidProjectPathException::fromPath($projectPath);
        }

        $projectName = basename($projectPath);
        $architecture = new Architecture($projectName, "Architecture diagram for {$projectName}");

        $srcPath = $projectPath . '/src';
        if (!is_dir($srcPath)) {
            return $architecture;
        }

        $this->scanEntities($srcPath, $architecture);
        $this->scanControllers($srcPath, $architecture);
        $this->scanRepositories($srcPath, $architecture);
        $this->scanServices($srcPath, $architecture);
        $this->scanEventListeners($srcPath, $architecture);

        $this->relationAnalyzer->analyze($architecture);

        return $architecture;
    }

    private function scanEntities(string $srcPath, Architecture $architecture): void
    {
        $entityPath = $srcPath . '/Entity';
        if (!is_dir($entityPath)) {
            return;
        }

        $entities = $this->entityScanner->scan($entityPath);
        foreach ($entities as $entity) {
            $architecture->addComponent($entity);
        }
    }

    private function scanControllers(string $srcPath, Architecture $architecture): void
    {
        $controllerPath = $srcPath . '/Controller';
        if (!is_dir($controllerPath)) {
            return;
        }

        $controllers = $this->controllerScanner->scan($controllerPath);
        foreach ($controllers as $controller) {
            $architecture->addComponent($controller);
        }
    }

    private function scanRepositories(string $srcPath, Architecture $architecture): void
    {
        $repositoryPath = $srcPath . '/Repository';
        if (!is_dir($repositoryPath)) {
            return;
        }

        $repositories = $this->repositoryScanner->scan($repositoryPath);
        foreach ($repositories as $repository) {
            $architecture->addComponent($repository);
        }
    }

    private function scanServices(string $srcPath, Architecture $architecture): void
    {
        $servicePath = $srcPath . '/Service';
        if (!is_dir($servicePath)) {
            $servicePath = $srcPath . '/Services';
            if (!is_dir($servicePath)) {
                return;
            }
        }

        $services = $this->serviceScanner->scan($servicePath);
        foreach ($services as $service) {
            $architecture->addComponent($service);
        }
    }

    private function scanEventListeners(string $srcPath, Architecture $architecture): void
    {
        $listeners = $this->eventListenerScanner->scan($srcPath);
        foreach ($listeners as $listener) {
            $architecture->addComponent($listener);
        }
    }
}
