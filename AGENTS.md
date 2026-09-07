# TaskFlow repository qaydaları

## Səlahiyyət və oxu ardıcıllığı

Bu repository tam, işlək minimal Jira-tipli issue tracker-dir. Kod cari vəziyyətin, `docs-ibp` isə qəbul edilmiş məhsul və texniki müqavilənin mənbəyidir.

Kod dəyişməzdən əvvəl tam oxu:

1. `AGENTS.md`
2. `docs-ibp/README.md`
3. `docs-ibp/business/PRODUCT.md`
4. `docs-ibp/business/BUSINESS_RULES.md`
5. `docs-ibp/business/ROLES_AND_PERMISSIONS.md`
6. `docs-ibp/technical/ARCHITECTURE.md`
7. `docs-ibp/technical/ARCHITECTURE_DECISIONS.md`
8. `docs-ibp/technical/DATA_MODEL.md`
9. `docs-ibp/technical/SECURITY.md`
10. `docs-ibp/technical/TESTING.md`
11. dəyişən sahənin `docs-ibp/modules/*.md` sənədi

API işi üçün `docs-ibp/technical/API.md`, mühit işi üçün `docs-ibp/technical/ENVIRONMENT.md` də oxunmalıdır. `ROADMAP.md` deferred scope-dur və istifadəçi konkret roadmap maddəsini açıq başlamadan implementasiya edilmir.

Conflict prioriteti: açıq istifadəçi göstərişi, `AGENTS.md`, business sənədləri, architecture qərarları, uyğun texniki/modul sənəd, kod. Qərar dəyişirsə eyni işdə kod, test və authoritative sənəd birlikdə yenilənir. Alternativ plan/status/handoff sənədi yaratma.

## Layihə identikliyi

- Laravel 13, PHP 8.3+
- `nwidart/laravel-modules` modular monolith
- Blade, Tailwind CSS, Vite, vanilla JavaScript
- yalnız məhdud Livewire
- REST `/api/v1`
- Web session auth, API Sanctum PAT
- Spatie Permission + Policies/Gates
- Spatie Activitylog üzərində Activity modulu
- Pest + məqsədli Playwright

Modullar yalnız Projects, Tasks, Media, Activity və Dashboard-dur. Authentication, daxili user administration, PAT və notification host application-da qalır. `Api`, `Web`, `Auth`, `Users`, `Core`, `Shared`, `Labels`, `Board` və `Notifications` modulu yaratma.

## Məhsulun dəyişməz qaydaları

- Tək təşkilat; workspace/multi-tenancy yoxdur.
- Work item bir project, bir reporter və sıfır/bir assignee daşıyır; maraqlı üzvlər watcher-dir.
- Project üzvləri layihənin bütün işlərini görə bilir; assignment məsuliyyətdir, visibility deyil.
- Type: `task`, `bug`, `story`, bir səviyyəli `subtask`.
- Priority: `low < medium < high < urgent`.
- Project-scoped label var; generic category/component yoxdur.
- Workflow: `backlog`, `todo`, `in_progress`, `review`, `done`, `cancelled`; keçid sahibi `TaskStatusService`-dir.
- Yeni iş backlog və server rank ilə yaranır; client ilkin status/raw rank seçmir.
- Açıq reorder manager-only; assignee status keçidi target column sonuna append edir.
- Issue key project-localdır (`PAY-42`); köhnə global nömrələmə müqavilə deyil.
- Yalnız active project mutable-dir. Completed read-only və reopen edilə bilər; archived read-only və terminaldır.
- Media private-dir və bütün binary/metadata/storage əməliyyatı Media modulundan keçir.
- Multi-file upload all-or-nothing-dır; əvvəl tam validasiya və hər failure-də tam kompensasiya tələb edir.
- Public registration yoxdur. Suspend session/token-ləri ləğv edir, açıq işi unassign, watcher-ləri silir, tarixi Activity-də qoruyur.
- Notification database/in-app və Web-only-dir. Dashboard My Watched Work verir; ümumi task filter-də watcher filter yoxdur.
- Sprint, epic, custom field/workflow, dependency, recurring task, automation, webhook və external integration cari scope deyil.

## Application və persistence sərhədi

```text
Route
  -> Web/API Controller və ya təsdiqlənmiş Livewire component
  -> Form Request/component validation
  -> Policy/Gate və lazım olduqda Sanctum ability
  -> purpose-specific readonly DTO
  -> use-case və ya query Service
  -> RepositoryInterface
  -> Repositories/Eloquent implementation və model
  -> Blade/API Resource/redirect
```

Controller adapterdir. Authorization və DTO-dan sonra hər action bir application boundary çağırır. Controller və Livewire repository/Eloquent/relation query/transaction/workflow/storage çağırmır və response üçün eager load etmir. Bir metodlu gizlədici pass-through service yaratma; service tam use-case/read nəticəsinə sahib olsun.

Service orchestration, invariant, transaction, məqsədli cross-module call və meaningful Activity sahibidir. Entry authorization-u əvəz etmir, amma archived/completed immutability, membership, one-assignee, issue key, rank və parent/child invariantını bütün caller-lər üçün qoruyur. Caller-dan relation preload tələb etmir. Top-level mutation transaction sahibidir; nested collaborator opaque transaction yaratmır.

Repository Eloquent query/persistence, actor/project scope, filter, sort, pagination, eager loading və lock sahibidir. Contract `Builder` qaytarmır; model/collection/paginator/read model/aggregate/scalar qaytarır. Atomic/server-owned write üçün purpose-specific metod istifadə et.

DTO readonly və yalnız validated input-dan qurulur. Eyni context üçün model və redundant ID ötürmə. Business date immutable olsun. `request()->all()` istifadə etmə.

## Modul əlaqələri

Cari direct dependency-lər məqsədlidir və gizlədilmir:

- Tasks → Projects membership və Project modeli;
- Tasks → Media authorized attachment use case-ləri;
- Projects/Tasks/host → Activity recorder;
- Dashboard → Projects/Tasks/Activity read sərhədləri.

Davranış və testlər sabit qalana qədər speculative contract, adapter, bus və event ilə bunları örtmə. Loose coupling ayrıca roadmap-dır.

## Media

Tasks kodu faylı birbaşa saxlamır. Media random path, detected MIME, size, checksum, image dimensions, safe stream və physical cleanup sahibidir; consuming modul authorization və association sahibidir.

Public disk URL, SVG və executable content yoxdur. Client filename/extension/MIME-ə güvənmə. Delete database/file inconsistency buraxmamalıdır.

## Livewire və JavaScript

Livewire yalnız:

- `QuickTaskCreate`
- `TaskFilters`
- `TaskStatusSelector`
- `TaskCommentForm`

Board drag/drop, modal, preview, counter və copy focused vanilla JavaScript-dir. Core əməliyyat JavaScript olmadan işləməli və ya təhlükəsiz fail etməlidir. Backend authoritative-dir.

## Validation, authorization və error

Form Request input shape yoxlayır; auth attempt, persistence query, rate-limit orchestration və domain uniqueness qərarı vermir. Policy/Gate access, service state invariantı verir. Sanctum ability policy-ni bypass etmir.

Expected domain exception məqsədli map olunur:

- 401 unauthenticated/suspended;
- 403 ability/authorization;
- 404 missing və safe nested/scope mismatch;
- 409 sənədləşdirilmiş state/concurrency conflict;
- 422 input validation;
- 429 rate limit;
- 500 opaque gözlənilməz failure.

Generic `LogicException`, `ModelNotFoundException`, DB/storage/runtime mesajını user-ə çıxarma. Nested resource parent-scoped olsun; filter gizli project/user/label/watcher/media/activity metadata-sı göstərməsin. Resource/Blade/view composer lazy query yaratmasın.

## Test və tamamlanma

Pest əsas qatdır: unit domain qaydası, integration repository/constraint, feature Web/API/security/Livewire üçündür. Playwright yalnız 10 kritik journey-ni desktop/mobile yoxlayır və Pest-i əvəz etmir.

Default DB SQLite `:memory:`; ayrıca `phpunit-mysql.xml` dedicated Herd MySQL compatibility profilidir. Modul migration/test discovery explicit olmalıdır. Yeni davranış uyğun test olmadan tamamlanmış sayılmır.

İş axını:

1. authoritative sənədləri və dirty workspace-i yoxla;
2. scope, gözlənən fayllar və verification-u elan et;
3. bütün acceptance davranışını və testləri implementasiya et;
4. riskə uyğun SQLite/MySQL/architecture/security/build/E2E gate-lərini işlə;
5. dəyişən həqiqəti `docs-ibp` daxilində yenilə;
6. nəticəni bir yekun reportla təhvil ver.

Report minimumu:

```text
Outcome:
Changed files:
Checks run and results:
Checks skipped and reasons:
Security/authorization impact:
Data/migration impact:
Remaining risks:
Documentation updated:
```

## Təhlükəsizlik və mühit

- `.env`/`.env.testing` yalnız istifadəçinin konkret icazəsi ilə oxunur və dəyişir; heç vaxt bütöv output edilmir.
- Credential, token plaintext/hash, private path və secret göstərilmir.
- Dependency install/update/remove, real migration/seeder, Git əməliyyatı və real browser automation explicit icazə tələb edir.
- Unidentified DB-də `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe` qadağandır.
- Test migration yalnız adı `taskflow_test` ilə başlayan dedicated DB və fail-closed bootstrap ilə işləyir.
- İstifadəçinin əlaqəsiz dəyişikliklərini qoru.
- Qəbul platforması Windows + Laravel Herd-dir; Unix/Linux ayrıca gate deyil.
