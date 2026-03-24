# Feature Specification: Project "High-Weight" Optimization (Base System)

**Feature Branch**: `001-optimize-existing-base`  
**Created**: 2026-03-24  
**Status**: Draft  
**Input**: User description: "根据现有系统现状进行针对性高权重优化" (Targeted high-weight optimization based on system status)

## User Scenarios & Testing *(mandatory)*

### User Story 1 - System Reliability & Security Refactoring (Priority: P1)

As a maintainer, I want to remove all hardcoded secrets and redundant logic from the core helper classes to ensure the system is secure and maintainable.

**Why this priority**: Hardcoded tokens (like the IPInfo token in AppHelper) and duplicate logic (e.g., file size formatting) lead to security vulnerabilities and bugs during updates.

**Independent Test**: Can be verified by running `grep` for secrets and executing unit tests for AppHelper methods.

**Acceptance Scenarios**:

1. **Given** AppHelper contains a hardcoded IPInfo token, **When** I move it to `.env` and `config/services.php`, **Then** the `getIpInfo()` method still works and no secrets are in the codebase.
2. **Given** duplicate file size formatting logic exists, **When** I consolidate it into a single optimized method, **Then** all calls to file size formatting return consistent results.

---

### User Story 2 - Automated Architecture & Feature Testing (Priority: P1)

As a developer, I want a comprehensive test suite that enforces architectural patterns and verifies core behaviors to prevent regressions.

**Why this priority**: The current test coverage (10 tests) is insufficient for a project of this scale. Enforcing "Configurator" patterns via code is better than just documentation.

**Independent Test**: Can be verified by running `php artisan test` and seeing an increase from 10 to 40+ passing tests.

**Acceptance Scenarios**:

1. **Given** the Configurator pattern is established in ARCHITECTURE.md, **When** I add Pest Architecture tests, **Then** any new code violating the decoupling rules (e.g., direct config in Provider) fails the build.
2. **Given** core models and resources are untested, **When** I add Pest unit/feature tests, **Then** they accurately reflect the expected behavior of User, OtpRecord, and AdminUser models.

---

### User Story 3 - Modernization & Performance Optimization (Priority: P2)

As a user, I want the application to leverage the latest Laravel 11/12/13 features and PHP 8.5 performance benefits for a smoother experience.

**Why this priority**: Using property-based casts and Attributes improves code readability and performance. Octane optimization ensures the app remains stable under high load.

**Independent Test**: Verified by code review and benchmark tests (if applicable).

**Acceptance Scenarios**:

1. **Given** User model uses old-style accessors and array casts, **When** I refactor them to use class-level property casts and `Attribute::make()`, **Then** the model logic remains functional but more concise.
2. **Given** the app runs on Octane, **When** I audit singletons in AppConfigurator, **Then** all stateful services are request-safe.

---

### Edge Cases

- **Token Rotation**: How does the system handle an expired or missing IPInfo token in `.env`? (Should fallback gracefully to a generic "Unknown" status without throwing 500).
- **Collision in Order IDs**: How does the unique order ID generation handle microsecond collisions in a high-concurrency Octane environment?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST move all hardcoded external API keys (specifically IPInfo token) to `config/services.php`.
- **FR-002**: AppHelper MUST consolidate `formatFileSize` and `readableBytes` into a single high-performance method.
- **FR-003**: System MUST provide architecture tests in `tests/Arch.php` to ensure `AppConfigurator` and `FilamentConfigurator` are used for configuration.
- **FR-004**: All models MUST be refactored to use Laravel 11/13 property-based Casts and `Attribute` objects where appropriate.
- **FR-005**: `AppConfigurator` exception handler MUST be checked for potential N+1 or memory leaks when capturing large stack traces in non-debug environments.
- **FR-006**: `AppHelper::json_encode` and `json_decode` should be audited to see if they can be simplified using Laravel's native helpers while maintaining logging.

### Key Entities

- **AppHelper**: Central utility class for system-wide operations.
- **AppConfigurator**: Logic hub for Laravel routing, middleware, and core configurations.
- **FilamentConfigurator**: Logic hub for Filament panel setup.
- **User / AdminUser**: Core identity models.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 0 hardcoded external service tokens found in the logic layer (excluding config files).
- **SC-002**: Test coverage increased from 10 tests to at least 40 tests, covering all major resources and config helpers.
- **SC-003**: `php artisan pint` returns success on all optimized files.
- **SC-004**: System successfully passes `pest --arch` tests for pattern compliance.

## Assumptions

- **Environment**: PHP 8.5+ and Laravel 13 features are fully available and preferred.
- **Consistency**: Any breaking change in `AppHelper` (e.g., method renaming) will be propagated to all calling sites.
- **Octane**: The application is intended to run in a stateful environment like Octane (RoadRunner), so request Isolation must be maintained.
