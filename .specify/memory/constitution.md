<!--
Sync Impact Report
- Version change: none → 1.0.0
- List of modified principles (template → concrete):
    - [PRINCIPLE_1_NAME] → I. Laravel-First & Predictable
    - [PRINCIPLE_2_NAME] → II. Filament v5 for SDUI
    - [PRINCIPLE_3_NAME] → III. AI-Native Architecture
    - [PRINCIPLE_4_NAME] → IV. Test-Driven Assurance (Pest)
    - [PRINCIPLE_5_NAME] → V. Modern PHP & Quality Standards
- Added sections: Technical Stack, Development Workflow
- Removed sections: none
- Templates requiring updates (✅ updated): 
    - .specify/templates/plan-template.md
    - .specify/templates/spec-template.md
    - .specify/templates/tasks-template.md
- Follow-up TODOs: none
-->

# Laravel 13 Starter Constitution

## Core Principles

### I. Laravel-First & Predictable
Strictly follow Laravel 13+ conventions and architectural patterns. Use `php artisan make:*` commands for all component generation. Leverage the framework's predictable structure to ensure maximum compatibility with AI coding agents and long-term maintainability.

### II. Filament v5 for SDUI
Utilize Filament v5 for all administrative, dashboard, and data-heavy interfaces. Adhere to Server-Driven UI (SDUI) principles, using reactive Livewire components and centralized configuration for a consistent and premium user experience.

### III. AI-Native Architecture
Design and implement features using the `laravel/ai` SDK where appropriate. Maintain clear, type-safe interfaces and self-documenting code that AI agents can easily understand, analyze, and extend without ambiguity.

### IV. Test-Driven Assurance (Pest)
Every logic change or new feature must be accompanied by comprehensive tests using Pest v4. Follow the Red-Green-Refactor cycle. Prioritize feature tests that cover core user journeys and business logic.

### V. Modern PHP & Quality Standards
Leverage modern PHP 8.5+ features, including strict types, constructor property promotion, and enums. Maintain code quality by running Laravel Pint with the `--dirty` flag before every commit. Focus on concise, direct, and over-engineering-free implementation.

## Technical Stack
This project is built upon a modern, high-performance stack:
- **Core**: PHP 8.5, Laravel 13
- **Admin/UI**: Filament v5, Livewire v4, Alpine.js
- **Styling**: Tailwind CSS v4
- **Testing**: Pest v4, PHPUnit 12
- **Ecosystem**: Laravel AI SDK, Laravel Boost, Laravel Horizon, Laravel Octane, Laravel Scout, Laravel Socialite, Laravel Cashier (Stripe)

## Development Workflow
1. **Research**: Use `search-docs` and `database-query` tools to understand the existing context and best practices.
2. **Design**: Create a technical specification in `.specify/specs/` if the change is structural or complex.
3. **Generate**: Use appropriate Artisan commands (e.g., `php artisan make:filament-resource`) to scaffold components.
4. **Implement**: Follow TDD by writing failing tests first, then implementing minimal code to pass.
5. **Format & Verify**: Run `vendor/bin/pint --dirty` and `php artisan test --compact` before finalizing.

## Governance
This constitution supersedes all ad-hoc practices. Any architectural deviation must be justified in the implementation plan. Amendments require a version bump and propagation to any related `.specify` templates.

**Version**: 1.0.0 | **Ratified**: 2026-03-24 | **Last Amended**: 2026-03-24
