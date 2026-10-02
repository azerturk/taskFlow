# Texniki arxitektura

## Sistem forması

TaskFlow Laravel 13 və PHP 8.3+ üzərində qurulmuş modular monolith-dir. Tətbiq bir verilənlər bazası və bir deploy vahidi istifadə edir. Modullar `nwidart/laravel-modules` ilə qeydiyyatdan keçir; frontend Blade, Tailwind CSS, Vite və vanilla JavaScript-dir.

```text
Host application
├── session authentication
├── daxili istifadəçi və account lifecycle
├── Sanctum token issuance/revoke
├── database notification inbox
├── shared layout, middleware, Gate və rate limit
└── qlobal exception rendering

Modules
├── Projects
├── Tasks
├── Media
├── Activity
└── Dashboard
```

`Api`, `Web`, `Auth`, `Users`, `Core`, `Shared`, `Labels`, `Board` və `Notifications` adlı ayrıca modullar yoxdur.

## Request axını

```text
Route
  -> Web/API Controller və ya təsdiqlənmiş Livewire component
  -> Form Request/component validation
  -> Policy/Gate və lazım olduqda Sanctum ability
  -> məqsədli readonly DTO
  -> mutation service və ya query service
  -> RepositoryInterface
  -> Repositories/Eloquent implementasiyası
  -> Eloquent model/verilənlər bazası
  -> Blade, API Resource, redirect və ya stream response
```

Controller adapterdir. Authorization və DTO-dan sonra hər action bir application boundary çağırır. Controller və Livewire repository inject etmir, Eloquent/relationship query, `load()`, transaction, workflow, membership, `DB` və `Storage` əməliyyatı etmir.

## Application service-lər

- Mutation service use case orchestration, domain invariant, transaction və Activity sahibidir.
- Read/query service repository nəticələrini page/resource üçün compose edir, əlavə Eloquent query qurmur.
- Service caller-dən relation preload tələb etmir; lazım olan aggregate-i repository-dən özü alır.
- Ən yuxarı mutation service vahid transaction sərhədidir. Daxili collaborator transaction-neutral olur.
- Domain service HTTP `ValidationException` atmır; məqsədli domain exception istifadə edir.
- Server-owned field-lər request mass assignment-dan gəlmir.

Əsas transaction nümunələri:

- project create = project + owner manager membership + Activity;
- member mutation = invariant + membership + watcher cleanup + Activity;
- task create = locked project sequence + rank + task + labels/watchers + Activity;
- assignment = membership + version + auto-watch + Activity + notification;
- status = version/transition + timestamps + target-column rank + Activity + notification;
- media batch = storage ledger + metadata + association + Activity + tam compensation.

## Repository-lər

Standart quruluş:

```text
Repositories/
├── Contracts/*RepositoryInterface.php
└── Eloquent/Eloquent*Repository.php
```

Repository qatının sahibliyi:

- Eloquent persistence və explicit create/update/delete;
- actor/project visibility scope;
- filter, allowlist sort və bounded pagination;
- eager loading və presentation-ready result;
- aggregate və filter option query-ləri;
- row lock, issue sequence və rank/concurrency əməliyyatları.

Contract Eloquent `Builder` və relation builder qaytarmır. Nəticə model, collection, paginator, scalar aggregate və ya readonly read model-dir.

## Presentation sərhədi

- API controller yalnız Resource/ResourceCollection, stream və ya bodyless response qaytarır.
- Resource optional relation üçün `whenLoaded()` və ya explicit read model sahəsi istifadə edir.
- Blade, component, Resource və view composer query/lazy loading etmir.
- Workspace notification count query service və injected composer vasitəsilə hazırlanır.
- Notification linked record-ları actor-visible batch ilə həll olunur; per-row query/policy dövrü yoxdur.

## Authorization qatları

1. Authentication actor-u müəyyən edir.
2. API-də Sanctum ability route ailəsini daraldır.
3. Spatie permission geniş capability-ni təsdiqləyir.
4. Policy/Gate record, project role, membership və lifecycle kontekstini yoxlayır.
5. Service HTTP-dən kənar çağırış üçün də domain invariant-larını yenidən qoruyur.

Route binding əvvəl ability-ni, sonra actor-visible scope-u tətbiq edir. Nested comment/media/label/member parent daxilində həll olunur.

## Modul sahibliyi

| Sahə | Owner |
|---|---|
| User, session, PAT, notification, global role | Host application |
| Project identity, lifecycle, membership, issue sequence | Projects |
| Work item, workflow, rank, label, watcher, comment, Task–Media association | Tasks |
| Binary, private path, MIME, checksum, stream və fiziki cleanup | Media |
| Canonical audit event, sanitizer, scoped history | Activity |
| Aggregate/read queue və QuickTaskCreate presentation | Dashboard |

Ətraflı sərhədlər [`../modules`](../modules) sənədlərindədir.

## Route sahibliyi

- Host Web: `routes/web.php`
- Host auth API: `routes/api.php`
- Modul Web: `Modules/<Module>/routes/web.php`
- Modul API: `Modules/<Module>/routes/api.php`

Modul provider öz route, view, migration, policy, repository binding və təsdiqlənmiş Livewire component-lərini qeydiyyatdan keçirir. API route-ları `/api/v1`, adları `api.v1.*` altındadır.

## Livewire və JavaScript

Livewire yalnız:

- `QuickTaskCreate`
- `TaskFilters`
- `TaskStatusSelector`
- `TaskCommentForm`

Board drag/drop, modal, preview, counter və copy focused vanilla JavaScript istifadə edir. Server hər zaman authoritative-dir; əsas form axınları JavaScript olmadan işləyir və ya təhlükəsiz rədd edilir.

## Birbaşa modul asılılıqları

```text
Projects  -> Tasks, Activity
Tasks     -> Projects, Media, Activity
Media     -> host User və Storage
Activity  -> Projects, Tasks
Dashboard -> Projects, Tasks, Activity
Host      -> Projects, Tasks, Activity
```

Bu explicit asılılıqlar cari modular monolith üçün qəbul edilib və architecture test-ləri ilə allowlist olunur. Contract/event ilə loose coupling refaktoru yalnız [`ROADMAP.md`](../../ROADMAP.md) mövzusudur.

## Error arxitekturası

Gözlənilən domain vəziyyətləri məqsədli exception-larla 409 və ya 422-yə map olunur. Actor üçün əlçatmaz, silinmiş, mövcud olmayan və parent-mismatch record-lar eyni safe 404 alır. Generic `LogicException`, model, DB, storage və programming failure-ları istifadəçiyə mesajını çıxarmır və 500 olaraq qalır. Status kodları [`API.md`](API.md), təhlükəsizlik səbəbləri [`SECURITY.md`](SECURITY.md) sənədindədir.

## Enforcement

Architecture test-ləri controller/Livewire repository istifadəsini, qat xarici Eloquent/DB/Storage query-lərini, Builder leakage-i, raw model API return-u, təhlükəli exception mapping-i, təsdiqlənməmiş module dependency və Livewire component-i, Tasks daxilində fiziki file ownership-i və path separator bypass-larını rədd edir.

