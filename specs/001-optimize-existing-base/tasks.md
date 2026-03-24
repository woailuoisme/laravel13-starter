# Tasks: Project "High-Weight" Optimization (Base System)

**Input**: Design documents from `/specs/001-optimize-existing-base/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, quickstart.md

**Tests**: Test tasks are included as requested by User Story 2. They should be written first per TDD workflow.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

- [x] T001 Verify project environment matches Laravel 13 requirements and Pest v4 availability in `composer.json`
- [x] T002 Add `IPINFO_TOKEN` key to `.env.example` to establish configuration baseline

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T003 Set up Pest architecture testing plugin (`pestphp/pest-plugin-arch`) via Composer if not already present in `composer.json`

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 2 - Automated Architecture & Feature Testing (Priority: P1)

**Goal**: A comprehensive test suite that enforces architectural patterns and verifies core behaviors to prevent regressions.

**Independent Test**: Can be verified by running `php artisan test` and seeing an increase from 10 to 40+ passing tests.

### Tests for User Story 2 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [x] T004 [P] [US2] Create architecture test file `tests/Arch.php` ensuring AppConfigurator and FilamentConfigurator are only used in bootstrap/providers
- [x] T005 [P] [US2] Add architecture test in `tests/Arch.php` to prevent static properties in App\Helpers namespace for Octane safety
- [x] T006 [P] [US2] Implement Pest unit tests for AppHelper utility functions in `tests/Feature/Helpers/AppHelperTest.php`
- [x] T007 [P] [US2] Implement Pest unit tests for basic User and AdminUser model logic in `tests/Unit/Models/UserTest.php` and `tests/Unit/Models/AdminUserTest.php`

### Implementation for User Story 2

- [x] T008 [US2] Update helper usages as necessary to resolve any valid architectural violations discovered by `tests/Arch.php`

**Checkpoint**: At this point, architecture tests and basic unit tests should be complete. Tests might fail until User Stories 1 & 3 are implemented.

---

## Phase 4: User Story 1 - System Reliability & Security Refactoring (Priority: P1)

**Goal**: Remove all hardcoded secrets and redundant logic from the core helper classes to ensure the system is secure and maintainable.

**Independent Test**: Can be verified by running `grep` for secrets and executing unit tests for AppHelper methods.

### Implementation for User Story 1

- [x] T009 [P] [US1] Add `ipinfo.token` configuration to `config/services.php` mapping to `IPINFO_TOKEN` env variable
- [x] T010 [US1] Extract hardcoded IPInfo token from `app/Helpers/AppHelper.php` (`getIpInfo`) and use `config('services.ipinfo.token')`
- [x] T011 [P] [US1] Refactor `formatFileSize` and `readableBytes` in `app/Helpers/AppHelper.php` to use Laravel Native `Illuminate\Support\Number::fileSize()`
- [x] T012 [P] [US1] Audit and simplify `AppHelper::json_encode` and `json_decode` logic using PHP 8.5 `mb_trim` and Laravel native helpers where appropriate in `app/Helpers/AppHelper.php`

**Checkpoint**: At this point, User Story 1 should be fully functional and `AppHelperTest` should pass.

---

## Phase 5: User Story 3 - Modernization & Performance Optimization (Priority: P2)

**Goal**: Leverage the latest Laravel 11/13 features and PHP 8.5 performance benefits for a smoother experience.

**Independent Test**: Verified by code review and unit tests covering Models and Exceptions.

### Implementation for User Story 3

- [x] T013 [P] [US3] Refactor `User` model to use property-based `casts()` method and new `Attribute::make()` syntax in `app/Models/User.php`
- [x] T014 [P] [US3] Refactor `AdminUser` model to use property-based `casts()` method and ensure `FilamentUser` interface / security compliance in `app/Models/AdminUser.php`
- [x] T015 [US3] Refactor `AppConfigurator::configureExceptions` in `app/Helpers/AppConfigurator.php` to align with the new fluent closures API and prevent N+1 memory issues under Octane
- [x] T016 [US3] Update `withExceptions` configuration in `bootstrap/app.php` to integrate the optimized `AppConfigurator::configureExceptions`

**Checkpoint**: All user stories should now be independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [x] T017 [P] Run `vendor/bin/pint --dirty` to ensure all modified code meets project formatting standards
- [x] T018 Execute complete test suite with `php artisan test --compact` to verify 40+ passing tests and architecture constraints
- [ ] T019 Run static analysis using PHPStan (e.g., `./vendor/bin/phpstan analyze app/Models app/Helpers`) to catch any type regressions (PHPStan not installed in project)
- [x] T020 Review and confirm Octane safety for all updated singletons and helpers

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Can start immediately
- **Foundational (Phase 2)**: Depends on Phase 1
- **User Stories (Phase 3+)**: All depend on Phase 2.
- **Polish (Phase 6)**: Depends on all user stories being complete.

### Within Each User Story

- **US2 (Tests & Arch)** should ideally run first to establish the TDD framework and constraints.
- **US1 & US3** can be executed in parallel by different focus areas (Helpers vs Models).
