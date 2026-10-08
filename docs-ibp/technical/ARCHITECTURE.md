# Texniki arxitektura

## Junior üçün: arxitektura nəyi izah edir?

Arxitektura yalnız folder adları deyil. «Bu qaydanı kim qoruyur, hansı hissə hansı hissəni çağıra bilər, dəyişiklik zamanı nəyə toxunmamalıyıq?» suallarının cavabıdır.

Məsələn, status button-u request göndərir. Controller gələn istəyi uyğun application çağırışına çevirir. Service icazəli vəziyyət dəyişikliyini və onun bütöv nəticəsini idarə edir. Repository database əməliyyatını yerinə yetirir. Blade/Resource hazır nəticəni göstərir. Hər hissə bütün işi özündə etmədiyi üçün qaydanı Web/API/Livewire üçün təkrar yazmaq lazım gəlmir.

**Modular monolith** ayrıca məsuliyyətləri olan modulların bir tətbiq kimi işləməsidir. Ayrı folder avtomatik ayrı server, ayrı database və ya tam müstəqillik demək deyil. Cari məhsulda məqsədli direct dependency-lər var; R1 laboratoriyası isə başqa əlaqə üsullarını kiçik nümunədə göstərir.

Əvvəl [sistemin ümumi izahını](../diagrams/system/context.md), sonra [request qatlarının dərsini](../diagrams/system/request-layers.md), daha sonra [dependency xəritəsini](../diagrams/system/dependencies.md) oxu. Aşağıdakı hissələr həmin sadə izahların texniki müqaviləsidir.

## Sistem forması

TaskFlow Laravel 13 üzərində qurulmuş modular monolith-dir: modul sərhədləri olan, amma bir tətbiq kimi yayımlanan sistem. Tətbiq bir verilənlər bazası və bir deploy vahidi istifadə edir. Modullar `nwidart/laravel-modules` ilə qeydiyyatdan keçir; frontend Blade, Tailwind CSS, Vite və vanilla JavaScript-dir. `composer.json` runtime üçün PHP `^8.3` bildirir; cari locked test alətləri ilə development üçün PHP **8.4.1+** lazımdır ([mühit](ENVIRONMENT.md)).

```text
Host application
├── session authentication
├── daxili istifadəçi və account lifecycle
├── Sanctum token issuance/revoke
├── database notification inbox
├── shared layout, middleware, Gate və rate limit
└── qlobal exception rendering

Məhsul modulları
├── Projects
├── Tasks
├── Media
├── Activity
└── Dashboard

İzolyasiya edilmiş R1 tədris modulları
├── LearningCatalog
└── LearningInsights
```

`Api`, `Web`, `Auth`, `Users`, `Core`, `Shared`, `Labels`, `Board` və `Notifications` adlı ayrıca modullar yoxdur.

İki learning modulu beş məhsul modulunun refaktoru deyil. Onların `routes`, controller, Livewire və Blade səthi yoxdur. Məhsul/host learning modullarını çağırmır, learning modulları da məhsul/host-a müraciət etmir. Ayrı `r1_*` cədvəlləri istifadə edirlər. Quruluş və real axınlar [diagram xəritəsində](../diagrams/README.md) göstərilir.

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

Validation üçün cari praktik istisna var: project key və admin user email unikallığı dörd Form Request-də Laravel `unique`/`Rule::unique` presence rule-u ilə yoxlanır. Buna görə hazırkı bütün request validation-ı «heç DB oxumur» kimi təqdim etmək doğru deyil. Bu rule-lar service workflow/policy qərarının və DB UNIQUE constraint-inin əvəzi deyil. Konkret fayllar [təhlükəsizlik sənədində](SECURITY.md) göstərilir.

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
- media batch = storage ledger + metadata/association/Activity DB transaction-u + failure-da compensation cəhdi; cleanup failure ayrıca pending metadata/log ilə görünür.

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

Sadə nümunə: `TaskStatusService::change()` icazəli keçidi və versiyanı yoxlayır; `EloquentTaskRepository::lockForRankMutation()` məlumatı lock ilə alır; `TaskResource` artıq hazırlanmış nəticəni JSON-a çevirir. Eyni query-ni controller-də qurmaq bu məsuliyyət bölgüsünü pozardı.

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
| Published learning entry və üç açıq PHP type | LearningCatalog — yalnız laboratoriya |
| Learning projection, listener və missing-only rebuild | LearningInsights — yalnız laboratoriya |

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

Bu birbaşa asılılıqlar cari məhsul üçün qəbul edilib və architecture test-ləri ilə icazəli siyahıya salınıb. Mövcud məhsulu contract/event arxasına keçirmək hələ [`ROADMAP.md`](../../ROADMAP.md) mövzusudur. R1 laboratoriyasında isə artıq məhdud contract/event praktikası var; bunu məhsula tətbiq edilmiş refaktor kimi oxumaq olmaz.

## R1 laboratoriyasının əlaqələri

```text
LearningCatalog -- LearningEntryPublished --> LearningInsights listener
LearningInsights -- PublishedLearningEntryFeed::all() --> LearningCatalog
                       list<PublishedLearningEntryData>
```

Catalog Insights-ı tanımır. Insights Catalog-un yalnız `PublishedLearningEntryFeed`, `PublishedLearningEntryData` və `LearningEntryPublished` type-larını istifadə edir; model, repository, publish service və input DTO açıq səth deyil. Bu **daxili PHP public API**-sidir, `/api/v1` HTTP REST API-si deyil.

Publish insert-i öz transaction-ında tamamlayır, sonra `ShouldDispatchAfterCommit` event-i dispatch edir. Outer transaction varsa listener ən xarici commit-i gözləyir; yoxdursa dərhal, eyni prosesdə işləyir. Listener queue-da deyil. Rebuild əvvəl public feed-i alır, sonra yalnız öz projection yazılarını bir transaction-da edir. Nə outbox/inbox, nə avtomatik retry, nə də modul silmə mexanizmi var. Detallar [R1](../labs/r1/README.md) və [transaction/xəta davranışında](TRANSACTIONS_AND_FAILURES.md) verilir.

## Error arxitekturası

Gözlənilən domain vəziyyətləri məqsədli exception-larla 409 və ya 422-yə map olunur. Actor üçün əlçatmaz, silinmiş, mövcud olmayan və parent-mismatch record-lar eyni safe 404 alır. Generic `LogicException`, model, DB, storage və programming failure-ları istifadəçiyə mesajını çıxarmır və 500 olaraq qalır. Status kodları [`API.md`](API.md), təhlükəsizlik səbəbləri [`SECURITY.md`](SECURITY.md) sənədindədir.

## Enforcement

Architecture test-ləri controller/Livewire repository istifadəsini, qat xarici Eloquent/DB/Storage query-lərini, Builder leakage-i, raw model API return-u, təhlükəli exception mapping-i, təsdiqlənməmiş module dependency və Livewire component-i, Tasks daxilində fiziki file ownership-i və path separator bypass-larını rədd edir.

`tests/Architecture/Support/SourceGuard.php` məhsul, `LearningBoundaryGuard.php` isə learning sərhədlərinin məqsədli statik guard-ıdır. `R1LearningBoundaryTest.php` public type allowlist-i, əks production dependency-ni, cədvəl sahibliyini, qatları və UI/route yoxluğunu müsbət/mənfi fixture-lərlə yoxlayır. Bu, bütün PHP dinamik davranışını sübut edən ümumi analizator deyil; runtime feature/integration testlərini əvəz etmir.

Kodda istiqamət tapmaq üçün: [kodbaza bələdçisi](CODEBASE_GUIDE.md).

