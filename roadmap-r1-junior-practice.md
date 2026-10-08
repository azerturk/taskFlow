# R1 junior praktikası — iki izolyasiya olunmuş modul ilə Public API və Event

## Bu sənədin məqsədi

Bu tapşırıq junior developer-lərin `roadmap-concepts-draft.md` sənədindəki iki əsas anlayışı praktikada görməsi üçündür:

1. modul başqa modulun daxilinə girmədən onun **Public API / Contract** sərhədindən necə istifadə edir;
2. bir modul baş vermiş faktı **Event** kimi necə yayımlayır və başqa modul həmin event-i necə dinləyir.

Bu, TaskFlow-un cari məhsuluna yeni funksiya əlavə etmək tapşırığı deyil. `Projects`, `Tasks`, `Media`, `Activity`, `Dashboard` və host tətbiqin işləyən axınları dəyişdirilməməlidir.

Tapşırıq ayrıca branch/worktree-də yerinə yetirilməli və review-dan əvvəl əsas branch-ə birləşdirilməməlidir. Git əməliyyatı üçün ayrıca icazə yoxdursa AI agent Git əmri işlətməməlidir.

Bu qayda junior təqdimatları üçündür. Repository sahibinin açıq göstərişi ilə review edilmiş yekun variant local `main` üzərində tətbiq edilə bilər. Bu, remote push və ya başqa branch-lərin merge edilməsi icazəsi deyil.

---

## Tövsiyə edilən iki modul

```text
LearningCatalog
    ↓ Public API və public event
LearningInsights
```

### `LearningCatalog`

Sadə öyrənmə qeydlərinin sahibidir.

Məsələn:

```text
1. "Module Boundary" adlı qeyd publish edilir.
2. LearningCatalog qeydi öz cədvəlinə yazır.
3. LearningEntryPublished event-i yayımlayır.
```

### `LearningInsights`

Publish edilmiş qeydlər üçün çox sadə projection saxlayır.

Məsələn:

```text
LearningEntryPublished event-i gəlir
        ↓
LearningInsights öz projection cədvəlində bir sətir yaradır
        ↓
Published learning entry sayı hesablana bilir
```

Bu adların məqsədi kodun production məhsul modulu deyil, R1 öyrənmə praktikası olduğunu aydın göstərməkdir.

---

## Niyə iki modul?

Bir modul daxilində interface və event yaratmaq cross-module sərhədi tam göstərmir. İki kiçik modul olduqda junior real olaraq bunları görür:

- məlumatın sahibi hansı moduldur;
- consumer hansı class-ları import edə bilər;
- internal model/repository başqa moduldan niyə görünməməlidir;
- synchronous contract çağırışı ilə event listener arasında fərq nədir;
- dependency direction necə qorunur;
- producer consumer-i tanımadan necə işləyə bilir.

İki modulun heç biri mövcud TaskFlow modullarından asılı olmayacaq. Mövcud modullar da bu iki moduldan asılı olmayacaq.

---

## Dəqiq scope

Bu praktikada yalnız aşağıdakı davranış qurulmalıdır:

1. `LearningCatalog` yeni learning entry publish edir.
2. Entry `r1_learning_entries` cədvəlində saxlanılır.
3. Faktiki transaction commit-dən sonra typed `LearningEntryPublished` event-i dispatch edilir; açıq outer transaction varsa onun commit-i gözlənilir.
4. `LearningInsights` listener-i event-i qəbul edir.
5. Listener `r1_learning_insight_entries` projection cədvəlində həmin entry-ni qeyd edir.
6. Eyni `event_id` ikinci dəfə gəlsə duplicate projection yaranmır.
7. `LearningInsights` lazım olduqda `LearningCatalog` Public API contract-ı vasitəsilə bütün publish edilmiş entry-ləri oxuyub çatışmayan projection-ları vahid write transaction-u ilə bərpa edə bilir.
8. `LearningInsights` söndürülsə və ya listener qeydiyyatda olmasa belə `LearningCatalog` entry publish edə bilməlidir.
9. Title trim edilir, boş/yalnız whitespace və 255 simvoldan uzun title DB-yə çatmadan `InvalidLearningEntryTitle` ilə rədd edilir.

Bu mərhələdə Web səhifəsi, REST endpoint və istifadəçi interfeysi yaradılmır. Flow Pest testləri və application service-lər üzərindən göstərilir.

---

## Modul sərhədləri

### LearningCatalog-un public hissəsi

Başqa modul yalnız bu üç public class/interface-i istifadə edə bilər:

```text
Modules\LearningCatalog\Contracts\PublishedLearningEntryFeed
Modules\LearningCatalog\Data\PublishedLearningEntryData
Modules\LearningCatalog\Events\LearningEntryPublished
```

Public contract:

```php
interface PublishedLearningEntryFeed
{
    /** @return list<PublishedLearningEntryData> */
    public function all(): array;
}
```

Public readonly DTO:

```php
final readonly class PublishedLearningEntryData
{
    public function __construct(
        public int $id,
        public string $title,
        public DateTimeImmutable $publishedAt,
    ) {}
}
```

Public event:

```php
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class LearningEntryPublished implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $eventId,
        public int $entryId,
        public string $title,
        public DateTimeImmutable $publishedAt,
    ) {}
}
```

Buradakı Public API HTTP API demək deyil. Bu, eyni modular monolith daxilində başqa modulun istifadə edə bildiyi typed PHP sərhədidir.

### LearningCatalog-un internal hissəsi

Aşağıdakılar başqa modul tərəfindən import edilməməlidir:

```text
Modules\LearningCatalog\Models\*
Modules\LearningCatalog\Repositories\*
Modules\LearningCatalog\Services\*
```

`LearningInsights` Catalog modelini, repository-sini, Eloquent relation-ını və cədvəlini birbaşa istifadə edə bilməz.

### LearningInsights-un sahibliyi

`LearningInsights` yalnız öz projection cədvəlinə yazır. O:

- `r1_learning_entries` cədvəlinə birbaşa query etmir;
- Catalog modelini update etmir;
- Catalog repository-sini inject etmir;
- event listener daxilində Catalog-a geri write çağırışı etmir.

---

## İki fərqli communication flow-u

### Flow 1 — synchronous Public API

Projection rebuild zamanı nəticə dərhal lazımdır:

```text
LearningInsights rebuild service
        ↓ PublishedLearningEntryFeed contract
LearningCatalog public implementation
        ↓ internal repository
r1_learning_entries
        ↓ readonly DTO list
LearningInsights öz projection-unu rebuild edir
```

Burada direct synchronous contract düzgündür, çünki rebuild nəticəsi call tamamlanmadan hazır olmalıdır.

### Flow 2 — local event

Yeni entry artıq publish edildikdən sonra başqa modul bu faktdan xəbərdar olur:

```text
LearningCatalog publish transaction
        ↓ commit
LearningEntryPublished
        ↓
LearningInsights listener
        ↓
r1_learning_insight_entries
```

Producer `LearningInsights` class-ını və listener-in nə etdiyini bilməməlidir.

Event əmr deyil. `LearningEntryPublished` “projection yarat” demir. Sadəcə “entry publish edildi” faktını bildirir.

---

## Transaction qaydası

Əvvəl Catalog write transaction-u tamamlanmalıdır. Event native `Illuminate\Contracts\Events\ShouldDispatchAfterCommit` interface-ini implementasiya edir. Buna görə başqa caller transaction-u açıqdırsa listener yalnız həmin outer transaction commit etdikdən sonra işləyir; rollback olduqda işləmir:

```php
$entry = DB::transaction(function () use ($data) {
    return $this->entries->createPublished($data);
});

event(new LearningEntryPublished(
    eventId: (string) Str::uuid(),
    entryId: $entry->id,
    title: $entry->title,
    publishedAt: $entry->published_at->toDateTimeImmutable(),
));
```

Yalnız `DB::transaction(...)` çağırışından sonra `event(...)` yazmaq nested transaction halında faktiki commit zəmanəti vermir. After-commit interface-i bu sərhədi qoruyur; queue yaratmır və listener-i asynchronous etmir.

```text
Outer transaction varsa
  publish → event callback gözləyir
  commit  → synchronous listener işləyir
  rollback → listener işləmir
```

Bu kiçik praktikada queue, Redis, RabbitMQ, Kafka və outbox qurulmur. Local Laravel event kifayətdir.

Vacib qeyd: bu sadə local-event praktikası tam delivery guarantee vermir. Outbox/inbox növbəti ayrıca praktikanın mövzusudur.

---

## Input və recovery müqaviləsi

`PublishLearningEntryData` readonly input DTO-su title-ı trim edir və uzunluğu 1–255 simvol olduqda qəbul edir. Etibarsız title məqsədli `InvalidLearningEntryTitle` exception-u yaradır; Catalog write və event başlamır. HTTP səthi olmadığı üçün bu praktikaya Form Request və error renderer əlavə edilmir.

Rebuild-in mənası **yalnız çatışmayan projection-ların tamamlanmasıdır**. Mövcud title/tarix/source-event məlumatı dəyişdirilmir və əlavə sətirlər silinmir. Update/delete və tam snapshot reconciliation bu task-a daxil deyil.

```text
RebuildLearningInsightsService
  → PublishedLearningEntryFeed::all() ilə readonly DTO-ları al
  → Insights write transaction-u başlat
  → hər DTO üçün recordPublishedIfMissing(..., sourceEventId: null)
  → hamısı uğurlu: commit
  → bir write fail: həmin rebuild-in yeni write-larını rollback et
```

Feed write transaction-dan əvvəl oxunur. Feed fail edərsə heç bir projection write-ı başlamır. Rebuild yeni event göndərmir, Catalog-a write etmir və real event olmadığı halda saxta event ID yaratmır.

Listener-in olmaması ilə xəta atması eyni hal deyil. Listener olmadıqda publish uğurludur. Synchronous listener commit-dən sonra xəta atdıqda caller exception alır, amma Catalog entry-si committed qalır. Bu halda publish-i kor-koranə təkrarlamaq ikinci Catalog entry yarada bilər; çatışmayan projection public feed ilə rebuild edilməlidir. Avtomatik retry və xətanı gizlədən blanket `catch` yoxdur.

---

## Queue, Outbox və Inbox niyə tətbiq edilmir?

Bu praktikada məqsəd production səviyyəli messaging sistemi qurmaq deyil. Məqsəd əvvəlcə Public API, module boundary, event, listener və idempotency anlayışları arasındakı fərqi sadə kod üzərindən görməkdir.

Laravel event queue olmadan da işləyir. `event(...)` çağırıldıqda Laravel event dispatcher qeydiyyatda olan listener-i həmin PHP prosesi və request daxilində dərhal, yəni synchronous olaraq çağırır:

```text
LearningCatalog transaction commit
        ↓
LearningEntryPublished dispatch edilir
        ↓
Laravel Event Dispatcher
        ↓
LearningInsights listener-i dərhal işləyir
```

Queue istifadə edilsəydi listener eyni request daxilində işləməzdi. Event queue-ya göndərilər, ayrıca worker onu daha sonra emal edərdi. Queue uzun və ya gecikdirilə bilən işləri request-dən ayırmaq üçün faydalıdır, amma təkbaşına transaction ilə message delivery arasında tam zəmanət yaratmır.

Bu praktikadakı `entry_id` və `source_event_id` üçün `UNIQUE` constraint-lər və `recordPublishedIfMissing(...)` metodu yalnız sadə projection idempotency nümunəsidir. Eyni event listener-ə iki dəfə verilsə, ikinci projection yaranmır. Bu yanaşma full Inbox Pattern deyil.

Fərqlər belədir:

| Anlayış | Nəyi həll edir? | Bu praktikada vəziyyət |
|---|---|---|
| Synchronous local event | Eyni tətbiq prosesi daxilində producer-in baş vermiş faktı listener-ə bildirməsi | Tətbiq edilir |
| Queue | Listener işini ayrıca worker-ə ötürür, request-dən ayırır və retry imkanı yarada bilər | Tətbiq edilmir |
| Idempotency | Eyni event təkrar emal ediləndə duplicate nəticənin yaranmasının qarşısını alır | Projection identity-si `entry_id`, DB constraint-ləri və `firstOrCreate` ilə tətbiq edilir |
| Transactional Outbox | Business write ilə event qeydini eyni transaction-da saxlayaraq commit-dən sonra event-in itməsi riskini azaldır | Tətbiq edilmir, yalnız anlayış kimi öyrənilir |
| Inbox Pattern | Consumer-in qəbul etdiyi message/event-ləri ayrıca qeyd edib hər birini bir dəfə məntiqi olaraq emal etməsini qoruyur | Tətbiq edilmir, yalnız anlayış kimi öyrənilir |
| Dead Letter Queue | Təkrar cəhdlərdən sonra yenə işlənməyən message-ləri ayrıca saxlayır | Tətbiq edilmir |

Outbox və Inbox olmadığı üçün aşağıdakı zəmanətlər verilmir:

- Catalog transaction commit etdikdən sonra, event dispatch edilməzdən əvvəl proses dayanarsa event itə bilər;
- listener xəta verərsə avtomatik retry və dead-letter mexanizmi yoxdur;
- event-in ən azı bir dəfə mütləq çatdırılacağına zəmanət yoxdur;
- Catalog məlumatı yazıldığı halda Insights projection-u müvəqqəti olaraq yaranmamış qala bilər.

Bu laboratoriyada sonuncu uyğunsuzluq `PublishedLearningEntryFeed` Public API-si ilə projection rebuild edilərək bərpa oluna bilər. Bu, Outbox/Inbox əvəzi deyil; yalnız kiçik praktikanın təhlükəsiz və başa düşülən recovery yoludur.

Junior bu task-da queue, Outbox, Inbox və Dead Letter Queue implementasiya etməməlidir. Onların fərqini və hansı problemi həll etdiyini izah edə bilməsi kifayətdir. Etibarlı asynchronous delivery ayrıca gələcək praktikanın mövzusudur.

---

## Idempotency qaydası

Eyni event iki dəfə emal ediləndə iki projection yaranmamalıdır.

`r1_learning_insight_entries` cədvəlində ən azı bunlar olmalıdır:

```text
id
entry_id            UNIQUE
source_event_id     UNIQUE, nullable
title
published_at
created_at
updated_at
```

Listener purpose-specific repository metodu çağırmalıdır:

```php
recordPublishedIfMissing(...)
```

Listener daxilində Eloquent query yazılmamalıdır. Duplicate event testdə eyni `event_id` ilə listener-ə iki dəfə verilməli və projection count yenə `1` qalmalıdır.

Əsas projection identity-si `entry_id`-dir. Repository `firstOrCreate(['entry_id' => ...], ...)` ilə mövcud sətiri qoruyur. Rebuild-dən sonra event gəlsə də ikinci sətir yaranmır; ilk dəfə rebuild ilə yaradılmış sətirdə `source_event_id` null qala bilər. Bu sahə bütün emal edilmiş event-lərin ledger-i deyil.

Eyni event ID fərqli entry ID ilə göndərilərsə bu, düzgün duplicate deyil, ziddiyyətli payload-dır. DB constraint ikinci sətiri rədd etməlidir; bütün DB xətaları duplicate adı ilə udulmamalıdır.

---

## Tövsiyə edilən fayl quruluşu

```text
Modules/
├── LearningCatalog/
│   ├── app/
│   │   ├── Contracts/PublishedLearningEntryFeed.php
│   │   ├── Data/PublishLearningEntryData.php
│   │   ├── Data/PublishedLearningEntryData.php
│   │   ├── Events/LearningEntryPublished.php
│   │   ├── Exceptions/InvalidLearningEntryTitle.php
│   │   ├── Models/LearningEntry.php
│   │   ├── Repositories/Contracts/LearningEntryRepositoryInterface.php
│   │   ├── Repositories/Eloquent/EloquentLearningEntryRepository.php
│   │   ├── Services/LearningEntryService.php
│   │   ├── Services/EloquentPublishedLearningEntryFeed.php
│   │   └── Providers/LearningCatalogServiceProvider.php
│   ├── database/migrations/
│   ├── tests/Feature/
│   ├── module.json
│   └── composer.json
│
└── LearningInsights/
    ├── app/
    │   ├── Listeners/RecordPublishedLearningEntry.php
    │   ├── Models/LearningEntryInsight.php
    │   ├── Repositories/Contracts/LearningInsightRepositoryInterface.php
    │   ├── Repositories/Eloquent/EloquentLearningInsightRepository.php
    │   ├── Services/RebuildLearningInsightsService.php
    │   └── Providers/LearningInsightsServiceProvider.php
    ├── database/migrations/
    ├── tests/Feature/
    ├── tests/Integration/R1LearningFlowTest.php
    ├── module.json
    └── composer.json
```

Adlar mövcud repository convention-lərinə uyğunlaşdırıla bilər, amma ownership və public/internal sərhədi dəyişdirilməməlidir.

---

## AI agent üçün icra ardıcıllığı

AI agent bu tapşırığı alanda aşağıdakı ardıcıllıqla işləməlidir:

1. Kök `AGENTS.md` faylını tam oxu.
2. `roadmap-concepts-draft.md` faylını tam oxu.
3. `ROADMAP.md` daxilində R1 hissəsini oxu.
4. `AGENTS.md`-də tələb olunan bütün authoritative sənədləri göstərilən ardıcıllıqla, o cümlədən architecture, qərarlar, testing və environment sənədlərini oxu.
5. Dirty workspace-i yoxla və əlaqəsiz dəyişikliklərə toxunma.
6. Junior işi üçün ayrıca branch/worktree-ni, maintainer-in yekun tətbiqi üçün isə sənədin əvvəlindəki açıq local `main` istisnasını təsdiqlə. Git əməliyyatı üçün icazə yoxdursa Git əmri işlətmə və mövcud checkout-u dəyişməzdən əvvəl istifadəçiyə risk barədə məlumat ver.
7. Bu task-ın açıq şəkildə R1 laboratoriyasını başlatdığını və iki learning modulunun mövcud beş production moduluna müvəqqəti, izolyasiya olunmuş istisna olduğunu qeyd et.
8. Əvvəl testləri və architecture sərhədini planlaşdır, sonra iki modulu implementasiya et.
9. Mövcud business modulların kodunu refaktor etmə.
10. Tam test və build nəticələrindən sonra bir yekun report ver.

Agent dayanmadan özbaşına əlavə scope yaratmamalıdır. Bu sənəddə olmayan queue, outbox, UI, API və mövcud modul refaktoru ayrıca təsdiq tələb edir.

---

## Dəyişdirilməsi icazəli sahələr

Əsasən aşağıdakı sahələr dəyişə bilər:

- yeni `Modules/LearningCatalog/**`;
- yeni `Modules/LearningInsights/**`;
- modul registration üçün tələb olunan `modules_statuses.json` və module metadata;
- yeni modul test discovery-si üçün `phpunit.xml` və `phpunit-mysql.xml`;
- Feature/Integration test trait ayrımı üçün `tests/Pest.php`;
- dependency graph/boundary qaydasını sərtləşdirən architecture testləri.

Architecture testində yalnız bu dependency əlavə edilə bilər:

```text
LearningCatalog  -> heç bir TaskFlow business modulu
LearningInsights -> LearningCatalog-un yalnız Contracts/Data/Events hissəsi
```

Mövcud module allowlist boşaldılmamalı və geniş wildcard icazəsi verilməməlidir.

---

## Dəyişdirilməsi qadağan sahələr

Bu praktika zamanı:

- `Projects`, `Tasks`, `Media`, `Activity`, `Dashboard` behavior-u dəyişdirilməsin;
- host auth, user, PAT və notification kodu dəyişdirilməsin;
- mövcud route, controller, service və repository refaktor edilməsin;
- mövcud cədvəllərə column/FK əlavə edilməsin;
- yeni modul mövcud cədvəlləri birbaşa query etməsin;
- yeni Web/API route yaradılmasın;
- navigation və Blade UI dəyişdirilməsin;
- `Core`, `Shared`, generic `EventBus` və service locator yaradılmasın;
- Redis, RabbitMQ, Kafka, queue worker, outbox/inbox əlavə edilməsin;
- yeni Composer və npm dependency əlavə edilməsin;
- event payload-a Eloquent model, relation, request, user credential və secret qoyulmasın;
- mövcud test assertion-ları zəiflədilməsin və test skip edilməsin;
- `docs-ibp` bu laboratoriyanı cari production həqiqəti kimi göstərmək üçün dəyişdirilməsin;
- `ROADMAP.md`-də R1 tamamlanmış kimi qeyd edilməsin.

Əgər agent bu qadağalardan birini pozmadan task-ı bitirə bilmədiyini düşünürsə, implementasiyanı genişləndirmədən əvvəl istifadəçiyə konkret səbəbi bildirməlidir.

---

## Yazılmalı testlər

Minimum test siyahısı:

### LearningCatalog

1. Entry publish ediləndə Catalog cədvəlində yaranır.
2. `LearningEntryPublished` faktiki commit-dən sonra dispatch edilir; outer rollback-də listener işləmir.
3. Event payload typed, minimum və secretsizdir.
4. `PublishedLearningEntryFeed` yalnız readonly DTO qaytarır; Eloquent model qaytarmır.
5. Event listener olmasa belə publish use case uğurlu qalır.

### LearningInsights

6. Listener event-dən projection yaradır.
7. Eyni `event_id` iki dəfə verildikdə ikinci projection yaranmır.
8. Rebuild service Catalog public feed-i ilə projection-u bərpa edir.
9. Rebuild Catalog model/repository/table-ına birbaşa müraciət etmir.

### Architecture

10. `LearningCatalog` daxilində `LearningInsights` import-u qadağandır.
11. `LearningInsights` Catalog-un `Models`, `Repositories` və `Services` namespace-lərini import edə bilmir.
12. `LearningInsights` yalnız Catalog `Contracts`, təsdiqlənmiş `Data` və `Events` class-larını istifadə edə bilir.
13. Mövcud beş modul yeni learning modullarını import etmir.
14. Yeni modulların controller/route/UI yaratmadığı təsdiqlənir.

### Yekun variant üçün əlavə qəbul testləri

- Boş, whitespace və 256 simvolluq title heç bir write/event yaratmır; 1 və 255 simvolluq title qəbul edilir, trim qorunur.
- Event fake olmadan real publish → dispatcher → qeydiyyatlı listener → projection flow-u ID/title/tarix/event ID üzrə yoxlanılır.
- Real feed binding-i ilə listener olmadığı və listener fail etdiyi hallardan sonra projection bərpa edilir.
- Real write edib sonra exception atan repository double-ı Catalog rollback və event/projection yoxluğunu sübut edir.
- Təkrar rebuild, boş feed, gecikmiş event və ziddiyyətli event ID halları yoxlanılır.
- Rebuild-in ikinci write-ı fail edəndə birinci yeni write rollback olur; mövcud projection-lar qorunur.
- İki yeni cədvəlin migration və rollback davranışı hər iki DB profilində yoxlanılır.
- Architecture mənfi fixture-ləri namespace alias, grouped import, fully qualified/container reference və qadağan table access-i rədd edir; qanuni class alias-ləri qəbul edir.
- Learning modulları production modullarından və host business class-larından asılı ola bilməz; host/production da learning modullarını çağırmır.

Real commit testləri `Modules/LearningInsights/tests/Integration/R1LearningFlowTest.php` daxilində `DatabaseMigrations` ilə işləyir. `tests/Pest.php` bu qrupa `RefreshDatabase` tətbiq etmir. Feature testlər əvvəlki `RefreshDatabase` profilində qalır. Testlər SQLite `:memory:` və dedicated MySQL bootstrap-dan kənara çıxmır; listener olmadığı hal in-memory event registration ilə yoxlanılır, diskdə modul enable/disable edilmir.

Mövcud bütün testlər də keçməlidir.

---

## Qəbul meyarları

Tapşırıq yalnız aşağıdakıların hamısı ödənəndə hazır sayılır:

- iki modulun ownership-i aydındır;
- Catalog consumer modulunu tanımır;
- Insights Catalog-un daxili class-larını tanımır;
- public contract konkret və typed-dir;
- public DTO Eloquent model deyil;
- event keçmiş zamanda adlandırılıb: `LearningEntryPublished`;
- event faktiki outer commit-dən sonra dispatch edilir, rollback-də listener işləmir;
- event payload yalnız `event_id`, `entry_id`, `title`, `published_at` saxlayır;
- duplicate event idempotent işləyir;
- listener olmadıqda Catalog publish flow-u işləyir;
- rebuild yalnız public feed ilə işləyir;
- rebuild missing-only-dır və yeni write-ları atomikdir;
- title canonical və 1–255 simvoldur;
- real publish və recovery flow-ları integration testlə sübut edilir;
- mövcud TaskFlow business flow-ları və testləri dəyişmədən keçir;
- architecture test icazəsiz import-u fail etdirir;
- SQLite və dedicated Herd MySQL suite-ləri keçir;
- format, static və architecture gate-ləri keçir;
- yeni dependency yoxdur;
- roadmap və production `docs-ibp` həqiqəti dəyişdirilməyib.

---

## İşlədiləcək yoxlamalar

```text
composer test
composer test:architecture
composer test:static
composer test:mysql
composer format:check
npm run build
```

Bu praktikada route və UI olmadığı üçün yeni Playwright journey tələb edilmir. Mövcud E2E mənbəyinə toxunulmamalıdır.

MySQL testi yalnız adı `taskflow_test` ilə başlayan dedicated test bazasında və mövcud fail-closed bootstrap ilə işlədilməlidir. `.env` və `.env.testing` məzmunu output edilməməlidir.

---

## Juniorun yekun report formatı

```text
Outcome:
LearningCatalog nə edir:
LearningInsights nə edir:
Public API necə quruldu:
Event flow necə işləyir:
Idempotency necə qorundu:
Architecture qaydaları:
Changed files:
Checks run and results:
Checks skipped and reasons:
Existing system impact:
Remaining risks:
```

Reportda “best practice etdik” kimi ümumi cümlə kifayət deyil. Hər nəticə konkret class, test və davranışla göstərilməlidir.

---

## AI agentə veriləcək hazır prompt

Aşağıdakı mətni bu sənədlə birlikdə AI agentə vermək olar:

```text
Kök AGENTS.md, ROADMAP.md-də R1, roadmap-concepts-draft.md və
roadmap-r1-junior-practice.md sənədlərini tam oxu.

roadmap-r1-junior-practice.md daxilindəki iki izolyasiya olunmuş learning modulunu
dəqiq scope və qəbul meyarlarına uyğun implementasiya et. Bu tapşırıq R1 laboratoriyasını
açıq şəkildə başladır, amma mövcud Projects, Tasks, Media, Activity, Dashboard və host
behavior-unu dəyişməyə icazə vermir.

LearningCatalog producer və public API sahibi, LearningInsights isə yalnız public contract
consumer-i və LearningEntryPublished listener-i olmalıdır. Mövcud modulları refaktor etmə,
UI/API/queue/outbox əlavə etmə, yeni dependency quraşdırma və docs-ibp-ni cari production
həqiqəti kimi dəyişmə.

Əvvəl scope və gözlənən faylları bildir, sonra implementasiya və bütün testləri tamamla.
Yalnız bütün iş bitdikdən sonra sənəddəki formatla bir yekun report ver.
```

---

## Bu praktikadan sonra nə öyrənilməlidir?

Junior task bitəndə aşağıdakı suallara kod üzərindən cavab verə bilməlidir:

1. Public API ilə internal service arasında fərq nədir?
2. Niyə Insights Catalog modelini import etmir?
3. Direct contract call nə vaxt event-dən daha uyğundur?
4. Event niyə keçmiş zamanda adlandırılır?
5. Niyə event payload-a Eloquent model qoymuruq?
6. Eyni event iki dəfə gələndə niyə problem yarana bilər?
7. Producer niyə consumer-i tanımamalıdır?
8. Listener olmayanda producer niyə işləməyə davam etməlidir?
9. Outbox bu nümunədə hansı delivery problemini gələcəkdə həll edə bilər?
10. Architecture test olmasa bu sərhəd zamanla necə pozula bilər?

Bu sualların cavabı aydın deyilsə, tapşırıq yalnız kod yazmaqla tamamlanmış sayılmır.
