# Architecture Diagram Bundle

[English](README.md) | [中文](README.zh-CN.md)

自动生成 Symfony 项目架构图的 Bundle，基于 C4 Model 规范输出 PlantUML 格式。

## 功能特性

- 🔍 **自动扫描**：扫描项目中的 Controller、Entity、Repository、Service
- 📊 **C4 Model**：生成符合 C4 Model 规范的架构图
- 🎨 **PlantUML**：输出标准 PlantUML 格式，可直接渲染
- 📈 **统计信息**：显示组件数量、类型分布、层级分布
- 🔧 **灵活配置**：支持多种输出选项

## 安装

```bash
composer require tourze/architecture-diagram-bundle
```

## 使用方法

### 基础用法

生成项目架构图：
```bash
php bin/console app:generate-architecture-diagram projects/symfony-easy-admin-demo
```

### 增强架构图生成（推荐）

生成包含基础设施、外部系统、数据流和安全措施的增强架构图：
```bash
php bin/console app:generate-enhanced-architecture projects/symfony-easy-admin-demo
```

增强版本包括：
- 🏗️ 基础设施组件（数据库、缓存、消息队列等）
- 🌐 外部系统集成（支付网关、短信服务等）
- 📊 数据流分析（组件间数据传输）
- 🔒 安全措施（防火墙、SSL、认证等）

### 指定输出文件

```bash
php bin/console app:generate-architecture-diagram projects/symfony-easy-admin-demo -o architecture.puml
php bin/console app:generate-enhanced-architecture projects/symfony-easy-admin-demo --output-dir ./diagrams
```

### 显示统计信息

```bash
php bin/console app:generate-architecture-diagram projects/symfony-easy-admin-demo --show-stats
php bin/console app:generate-enhanced-architecture projects/symfony-easy-admin-demo --show-stats
```

### 生成简单格式（非C4）

```bash
php bin/console app:generate-architecture-diagram projects/symfony-easy-admin-demo --no-c4
```

### 不按层分组

```bash
php bin/console app:generate-architecture-diagram projects/symfony-easy-admin-demo --no-layers
```

### 生成不同类型的架构图（增强版）

```bash
# 系统概览图
php bin/console app:generate-enhanced-architecture projects/symfony-easy-admin-demo --type overview

# 组件图
php bin/console app:generate-enhanced-architecture projects/symfony-easy-admin-demo --type component

# 部署图
php bin/console app:generate-enhanced-architecture projects/symfony-easy-admin-demo --type deployment

# 时序图
php bin/console app:generate-enhanced-architecture projects/symfony-easy-admin-demo --type sequence

# 所有类型（默认）
php bin/console app:generate-enhanced-architecture projects/symfony-easy-admin-demo --type all
```

## 命令选项

### app:generate-architecture-diagram

| 选项 | 简写 | 说明 | 默认值 |
|-----|------|-----|--------|
| --output | -o | 输出文件路径 | 控制台输出 |
| --format | -f | 输出格式 | plantuml |
| --level | -l | C4层级 (context/container/component/code) | component |
| --no-c4 | | 不使用C4 Model格式 | false |
| --no-layers | | 不按层分组 | false |
| --show-stats | | 显示架构统计信息 | false |

### app:generate-enhanced-architecture

| 选项 | 简写 | 说明 | 默认值 |
|-----|------|-----|--------|
| --type | -t | 架构图类型 (overview/component/deployment/sequence/all) | all |
| --output-dir | -o | 输出目录路径 | 项目路径 |
| --show-stats | | 显示架构统计信息 | false |

## 架构层级

系统按照以下层级组织组件：

- **Presentation Layer（展示层）**：Controller、Command、Form
- **Application Layer（应用层）**：Service、Handler、Manager  
- **Domain Layer（领域层）**：Entity、Model、ValueObject
- **Infrastructure Layer（基础设施层）**：Repository、Gateway、Adapter

## 查看生成的图表

### 在线查看

将生成的 PlantUML 代码复制到在线编辑器：
https://www.plantuml.com/plantuml/uml/

### 本地查看

如果已安装 PlantUML：
```bash
plantuml architecture.puml
```

## 示例输出

```plantuml
@startuml
!include https://raw.githubusercontent.com/plantuml-stdlib/C4-PlantUML/master/C4_Component.puml

Container_Boundary(app, "Application") {
    Container_Boundary(presentation, "Presentation Layer") {
        Component(controller_user, "UserController", "Symfony Controller", "...")
    }
    Container_Boundary(domain, "Domain Layer") {
        Component(entity_user, "User", "Doctrine ORM", "...")
    }
}

Rel(controller_user, entity_user, "Uses")
@enduml
```

## 扩展功能（计划中）

- [ ] 支持扫描 Bundle 依赖关系
- [ ] 支持自定义组件类型
- [ ] 支持导出 SVG/PNG 格式
- [ ] 支持 Monorepo 多项目联合分析
- [ ] 支持依赖注入关系自动推断

## 许可证

Proprietary