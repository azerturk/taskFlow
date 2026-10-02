# TaskFlow roadmap

## Status

Bu sənəd yalnız gələcək seçimləri saxlayır. Buradakı heç bir maddə cari məhsul müqaviləsinin hissəsi sayılmır və istifadəçi ayrıca roadmap işini açıq şəkildə başlatmadan implementasiya edilmir.

Cari işlək sistem, biznes və texniki həqiqət üçün [`docs-ibp/README.md`](docs-ibp/README.md), qəbul edilmiş release sübutu üçün [`docs-ibp/technical/RELEASE_BASELINE.md`](docs-ibp/technical/RELEASE_BASELINE.md) oxunmalıdır.

## R1 — Modul əlaqələrinin zəiflədilməsi

Məqsəd davranışı dəyişmədən modular monolith sərhədlərini ölçmək və yalnız real faydası sübut olunan yerlərdə gücləndirməkdir.

- module dependency graph və boundary metric-ləri;
- sabit use case-lər üçün purpose-specific cross-module contract-lar;
- notification/audit/cleanup kimi uyğun side-effect-lər üçün transactional domain event/outbox qiymətləndirilməsi;
- Dashboard üçün ölçülmüş read projection və cache strategiyası;
- module boundary failure/resilience testləri;
- cycle, hidden Eloquent coupling və breaking-contract yoxlamaları.

Gate: cari direct dependency-lər profillənməli, davranış və transaction ownership qorunmalı, əlavə abstraction konkret cycle/testability/performance problemini həll etməlidir. Generic bus, shared-kernel və speculative interface qadağandır.

## R2 — Təhlükəsizlik və privacy hardening

- MFA/2FA və recovery lifecycle;
- PAT siyahısı, expiry, rotation və device/session idarəsi;
- upload malware scanning, quarantine və scan statusu;
- strict CSP rollout, Trusted Types qiymətləndirilməsi və əlavə browser header hardening;
- security-event alerting və admin görünüşü;
- automated dependency, secret, container və SAST/DAST gate-ləri;
- data retention, privacy export/deletion siyasəti;
- incident response və backup/restore runbook-ları.

Gate: threat model, əməliyyat sahibi, false-positive/failure davranışı və deployment infrastrukturu müəyyən edilmədən tətbiq edilmir.

## R3 — Əməliyyat və performans

- observability: structured log, metrics, trace və SLO;
- queue worker supervision və failed-job bərpası;
- MySQL slow-query/index review və real həcm benchmark-ları;
- object storage/CDN deyil, private object storage stream strategiyasının qiymətləndirilməsi;
- backup, restore və disaster-recovery drill;
- zero-downtime migration/deploy proseduru;
- böyük project/board üçün projection, pagination və archive performansı.

Gate: real ölçü, hədəf SLO və rollback planı olmayan optimizasiya edilmir.

## Məhsul scope-una avtomatik daxil olmayan mövzular

Workspace/multi-tenancy, sprint, epic, release planning, story point, custom field/type/workflow/permission scheme, multiple assignee, dependency, recurring task, automation, webhook və external integration ayrıca məhsul qərarı tələb edir. Bu siyahıda görünməsi belə implementasiya icazəsi deyil.

## Roadmap işini başlatma qaydası

1. İstifadəçi konkret roadmap maddəsini açıq şəkildə seçir.
2. Məhsul məqsədi, təhlükəsizlik/data təsiri və qəbul meyarı yazılır.
3. Cari [`ARCHITECTURE_DECISIONS.md`](docs-ibp/technical/ARCHITECTURE_DECISIONS.md) ilə conflict qiymətləndirilir.
4. İmplementasiya, migration və rollback strategiyası təsdiqlənir.
5. Pest/MySQL/Playwright gate-ləri və sənədlər eyni dəyişiklikdə yenilənir.

## Ümumi:

- **Core Architecture**
    - Modular Monolith
    - Module Boundary
    - High Cohesion
    - Loose Coupling
    - Separation of Concerns
    - Encapsulation
    - Dependency Direction
    - Dependency Inversion
    - Internal vs Public API

- **Module Communication**
    - Module Public API / Contract
    - Facade
    - Proxy
    - Extension
    - Adapter
    - Port
    - Anti-Corruption Layer
    - Direct Module Call
    - Indirect Communication
    - Synchronous Communication
    - Asynchronous Communication

- **Event-Driven Concepts**
    - Event-Driven Architecture
    - Domain Event
    - Integration Event
    - Event Handler
    - Event Dispatcher
    - Event Bus
    - Publish / Subscribe
    - Eventual Consistency
    - Idempotency
    - Event Ordering
    - Event Versioning

- **Reliability**
    - Transactional Outbox
    - Inbox Pattern
    - At-Least-Once Delivery
    - Duplicate Message Handling
    - Retry
    - Dead Letter Queue
    - Failure Recovery

- **Domain & Application Design**
    - DDD
    - Bounded Context
    - Aggregate
    - Aggregate Root
    - Entity
    - Value Object
    - Domain Service
    - Application Service
    - Repository
    - Command
    - Query
    - CQRS
    - Use Case
    - Transaction Boundary

- **Database Isolation**
    - Database per Module
    - Schema per Module
    - Schema-Based Database Isolation
    - Table Ownership
    - No Cross-Module Table Access
    - Cross-Module Data Access
    - Read Model
    - Projection
    - Foreign Key Boundaries
    - Distributed Transaction Avoidance

- **Dependency Management**
    - Allowed Dependencies
    - Forbidden Dependencies
    - Cyclic Dependency
    - Dependency Graph
    - Shared Kernel
    - Shared Abstractions
    - Common Module
    - Module Dependency Rules

- **Architecture Enforcement**
    - Architecture Tests
    - Dependency Tests
    - Layer Tests
    - Module Boundary Tests
    - Naming Convention Tests
    - Forbidden Reference Tests
    - ArchUnit / NetArchTest tipli yanaşmalar

- **Extensibility**
    - Extension Points
    - Plugin Architecture
    - Strategy Pattern
    - Factory
    - Decorator
    - Middleware / Pipeline
    - Hook
    - Module Registration

- **API & Contract Design**
    - Contract Stability
    - Contract Versioning
    - DTO
    - Request / Response Model
    - Internal Contract
    - Integration Contract
    - Backward Compatibility
    - Breaking Change

- **Consistency & Transactions**
    - Strong Consistency
    - Eventual Consistency
    - Local Transaction
    - Cross-Module Transaction
    - Saga
    - Process Manager
    - Compensating Action

- **Observability**
    - Structured Logging
    - Correlation ID
    - Trace ID
    - Distributed Tracing mindset
    - Metrics
    - Audit Log
    - Event Tracking

- **Testing**
    - Unit Test
    - Integration Test
    - Module Integration Test
    - Contract Test
    - Architecture Test
    - End-to-End Test
    - Test Isolation

- **Common Anti-Patterns**
    - Big Ball of Mud
    - Shared Database Everything
    - Cross-Module Repository Access
    - Cross-Module Entity Usage
    - God Module
    - God Service
    - Circular Dependency
    - Leaky Abstraction
    - Shared DTO Everywhere
    - Hidden Coupling
    - Temporal Coupling
    - Distributed Monolith

**Modular Monolith → Module Boundaries → High Cohesion → Loose Coupling → Public API/Contract → Facade/Proxy/Extension → Domain & Integration Events → Event-Driven Communication → Outbox/Inbox → Idempotency → Eventual Consistency → Schema-per-Module → Architecture Tests → Dependency Rules → Contract Tests → Observability.**
