# R1 junior praktikası — Ahmad və Fidan üzrə review və main üçün implementasiya planı

Tarix: 8 oktyabr 2026.

Bu sənəd yalnız `roadmap-r1-junior-practice.md` tapşırığını qiymətləndirir. Ümumi TaskFlow refaktoru və ROADMAP.md-dəki bütün R1 işləri bu planın scope-u deyil. Aşağıdakı implementasiya addımları gələcək icra üçündür; bu review zamanı main-də learning modulları implementasiya edilməyib.

## 1. Araşdırmanın əsası

`git fetch --all` ilə remote branch-lər yeniləndi. Araşdırma aşağıdakı sabit snapshot-lar üzərində aparıldı:

| Ref | Commit |
|---|---|
| `main` və `origin/main` | `a68c3227070d1fb7d2c4a447e0af247d89946039` |
| `origin/ahmad` | `6f6e14861aa8f05da6468cf24f4901e5bb8fa852` |
| `origin/feature/fidan` | `8c4af41effebdf1d2ad739ab6f483a1679942b96` |

Əsas müqavilə `roadmap-r1-junior-practice.md`, köməkçi mənbələr `AGENTS.md`, `roadmap-concepts-draft.md`, ROADMAP.md-nin R1 hissəsi və `docs-ibp/technical/{ARCHITECTURE,ARCHITECTURE_DECISIONS,TESTING,ENVIRONMENT}.md` sənədləridir.

Main ilə junior branch-lərinin ortaq Git ancestor-u yoxdur. Repository shallow deyil. Buna görə `main...branch` merge-base diff-i əvəzinə `git diff origin/main origin/<branch>` ilə fayl snapshot-ları müqayisə edilib. Bu, kodu qiymətləndirməyə mane olmur, amma kor-koranə branch merge etməyi düzgün başlanğıc etmir.

Başlanğıc workspace təmiz idi. Review zamanı main checkout-u dəyişdirilmədi, merge/cherry-pick edilmədi və tətbiq koduna düzəliş yazılmadı. Branch-də görünməyən lokal və ya push edilməmiş işlər barədə nəticə çıxarılmır.

## 2. Ümumi nəticə

| Mövzu | Ahmad | Fidan |
|---|---|---|
| İki learning modulu | Fetch edilmiş branch-də yoxdur | Var |
| Public API və readonly DTO | Yoxdur | Var, sərhəd düzgündür |
| Typed local event və listener | Yoxdur | Var, əsas top-level flow düzgündür |
| Projection və DB unikallığı | Yoxdur | Var |
| Duplicate event testi | Yoxdur | Var |
| Public contract ilə rebuild | Yoxdur | Var, yalnız çatışmayan sətirləri tamamlayır |
| Production behavior-a müdaxilə | Main ilə eyni fayllar | Diff-də müdaxilə yoxdur |
| Test discovery | Yeni test yoxdur | SQLite/MySQL/Pest qeydiyyatı var |
| Real iki modulun birlikdə işləməsi üçün test | Yoxdur | Tam flow assertion-u çatışmır |
| Architecture qoruması | Yeni qayda yoxdur | Faydalı qaydalar var, bypass və coverage boşluqları qalır |

Main üçün baza Fidanın minimal implementasiyasıdır. Onun yaxşı hissələri saxlanılmalı, aşağıdakı konkret boşluqlar tamamlanmalıdır. Ahmad branch-indən R1 üçün daşınacaq kod görünmür.

## 3. Ahmad üzrə report

### 3.1. Təsdiqlənən vəziyyət

`git diff --name-status origin/main origin/ahmad` boş nəticə verir. Hər iki ref-in tree SHA-sı eynidir:

```text
774a08cd966ee0351d296a99cc494e880f018520
```

Ahmad branch-də yalnız `first` adlı bir commit görünür. `Modules/LearningCatalog`, `Modules/LearningInsights`, onların migration/testləri və yeni qeydiyyat yoxdur. `modules_statuses.json` əvvəlki beş modulu saxlayır.

`report-latest/Taskflow-Report-Ahmad.md` və `Taskflow-report-Ahmad-1.md` main-də də eyni məzmundadır. Bunlar əvvəlki QA hesabatlarıdır; bu praktikanın tamamlanmasına sübut deyil.

### 3.2. Nə düzgün qorunub?

Tracked fayllar main ilə eyni olduğuna görə cari sistemin koduna əlavə müdaxilə edilməyib. Bu, yalnız scope-un pozulmadığını göstərir; Public API və event praktikasının yerinə yetirildiyini göstərmir.

### 3.3. Nə təqdim edilməyib?

Praktikanın bütün implementasiya hissələri: iki modul, public contract/DTO, publish transaction-u, event/listener, projection, duplicate handling, rebuild və bunların testləri fetch edilmiş branch-də yoxdur.

Düzgün qiymətləndirmə: **“Fetch edilmiş Ahmad branch-də R1 implementasiyası təqdim edilməyib.”** Repository əsasında “Ahmad lokalda heç bir iş görməyib” demək olmaz.

## 4. Fidan üzrə report

Fidan snapshot-unun main-dən fərqi 29 fayldır: 24 yeni modul faylı və 5 qeydiyyat/test infrastructure faylı. Mövcud production modul, host business kodu, dependency lockfile-ları, `docs-ibp` və roadmap sənədləri dəyişdirilməyib.

Aşağıdakı fayl və sətir istinadları Fidanın `8c4af41effebdf1d2ad739ab6f483a1679942b96` commit-inə aiddir. Main-də hələ həmin fayllar yoxdur.

### 4.1. Düzgün görülən işlər

**Modul sahibliyi aydındır.** Catalog `r1_learning_entries`, Insights isə `r1_learning_insight_entries` cədvəlinə sahibdir. Insights Catalog modelinə, repository-sinə və cədvəlinə birbaşa müraciət etmir. Cross-module foreign key əlavə edilməyib.

**Public API konkret və kiçikdir.** `Modules/LearningCatalog/app/Contracts/PublishedLearningEntryFeed.php:7–10` yalnız `all(): array` və `list<PublishedLearningEntryData>` müqaviləsini verir. `Services/EloquentPublishedLearningEntryFeed.php:14–23` daxili modeldən public DTO yaradır; consumer-ə Eloquent model çıxarmır.

**DTO və event dəyişməzdir.** `PublishedLearningEntryData.php:7–13` və `LearningEntryPublished.php:7–14` `final readonly` class-lardır, tarix `DateTimeImmutable`-dır. Event yalnız event ID, entry ID, title və publish tarixini daşıyır.

**Producer consumer-i tanımır.** `LearningEntryService.php:18–25` öz repository-si ilə yazır və public event dispatch edir. Insights class-ını import etmir.

**Listener persistence-i repository-yə verir.** `Modules/LearningInsights/app/Listeners/RecordPublishedLearningEntry.php:13–20` event-i qəbul edib yalnız öz repository contract-ını çağırır. Listener-də Eloquent query yoxdur.

**Provider-lər real binding və registration verir.** Catalog provider-i repository/public feed binding-lərini və migration-ları qeydiyyatdan keçirir. Insights provider-i öz binding/migration-larını və `Event::listen(LearningEntryPublished::class, RecordPublishedLearningEntry::class)` əlaqəsini qurur (`LearningInsightsServiceProvider.php:16–22`).

**Duplicate-dən qorunma DB səviyyəsindədir.** Insights migration-ında `entry_id UNIQUE` və nullable `source_event_id UNIQUE` var (`2026_10_03_000001_create_r1_learning_insight_entries_table.php:13–14`). Repository `firstOrCreate(['entry_id' => ...], ...)` istifadə edir (`EloquentLearningInsightRepository.php:12–20`). Eyni entry/event-in təkrarı ikinci sətir yaratmır.

**Rebuild consumer contract-ından keçir.** `RebuildLearningInsightsService.php:11–30` yalnız Catalog public feed/DTO-su və Insights repository-si ilə işləyir. Rebuild-dən yaranan sətirdə `source_event_id = null` saxlamaq düzgündür: real event emal edilmədiyi halda saxta event ID uydurulmur.

**Test discovery əlavə edilib.** `phpunit.xml:30–31`, `phpunit-mysql.xml:30–31` və `tests/Pest.php:15–16` yeni feature testlərini daxil edir. Mövcud assertion-lar zəiflədilməyib.

**Faydalı testlər var.** Duplicate event, eyni entry üçün başqa event, readonly DTO, listener olmadığı hal, təkrar rebuild, nullable event ID və mövcud projection-un qorunması yoxlanılır.

### 4.2. Tamamlanmalı test: real publish → listener → projection

**Sübut:** `LearningInsightsProjectionSpecificationTest.php:36–61` listener-i birbaşa `handle()` edir. Catalog testləri isə publish-dən sonra Insights projection-unun yarandığını assert etmir.

**Nəticə:** provider-də listener registration-ı səhvən silinsə, ayrı-ayrı publish və direct-listener testləri yenə uğurlu qala bilər. Hazırkı provider kodunda registration var; problem həmin əlaqənin testlə tam qorunmamasıdır.

**Düzəliş:** Event fake və direct `handle()` istifadə etmədən real publish service çağırılsın. Sonra Catalog və Insights nəticələrinin `entry_id`, title, tarix və real event ID üzrə uyğunluğu yoxlanılsın.

### 4.3. Tamamlanmalı test: real Public API ilə recovery

**Sübut:** `LearningInsightsProjectionSpecificationTest.php:64–117` rebuild-i anonymous fake `PublishedLearningEntryFeed` ilə yoxlayır.

**Nəticə:** consumer-in contract-dan istifadəsi yaxşı yoxlanılır, amma Catalog real feed binding-i ilə missing-event recovery axını bütöv sübut olunmur.

**Düzəliş:** listener ayrıldıqda Catalog-a real publish edilsin, projection-un olmadığını test təsdiqləsin. Sonra real `PublishedLearningEntryFeed` binding-i ilə rebuild çağırılsın və məlumat uyğunluğu yoxlanılsın. Təkrar rebuild duplicate yaratmamalıdır.

### 4.4. Transaction: verilmiş nümunə doğrudur, outer transaction halı qorunmur

**Sübut:** `LearningEntryService.php:18` `DB::transaction(...)`, `20–25` isə bundan sonra immediate event dispatch edir. Bu, praktika sənədində verilmiş sadə nümunəyə uyğundur.

Top-level çağırışda həmin transaction commit edir və sonra listener işləyir. Amma service başqa transaction daxilindən çağırılsa, daxili transaction-un bitməsi real outer commit demək deyil:

```text
Outer transaction başlayır
  Catalog publish → daxili transaction tamamlanır
  Event və listener artıq işləyir
Outer transaction sonradan rollback olur
```

Laravel-in yerli `ManagesTransactions::transaction()` kodu PDO commit-i yalnız transaction level `1` olduqda edir. Buna görə bu hal ehtimal əsasında uydurulmuş deyil; hazırkı immediate dispatch-in real sərhədidir.

**Test boşluğu:** `LearningCatalogPublishSpecificationTest.php:24–35` listener daxilində eyni connection-dan `exists()` edir. `tests/Pest.php` feature testlərinə `RefreshDatabase` outer transaction-u tətbiq edir. Eyni connection uncommitted sətiri də görə bildiyi üçün bu assertion real commit-i sübut etmir.

**Qiymətləndirmə:** bunu verilmiş tapşırığın nümunəsinə zidd implementasiya kimi qiymətləndirmək düzgün olmaz. Yekun variantda transaction semantikasını gücləndirmək və testin adını/sübutunu dəqiqləşdirmək lazımdır.

**Plan qərarı:** public event Laravel-in `Illuminate\Contracts\Events\ShouldDispatchAfterCommit` interface-ini implementasiya edəcək. Bu queue əlavə etmir; listener yenə local və synchronous işləyəcək, amma açıq transaction varsa dispatch faktiki outer commit-ə qədər təxirə salınacaq. Rollback olduqda listener çağırılmayacaq.

### 4.5. Failed-write testi real rollback-i yoxlamır

**Sübut:** `LearningCatalogPublishSpecificationTest.php:38–48` repository-ni heç nə yazmadan exception atan mock-la əvəz edir.

**Nəticə:** event-in göndərilməməsi yoxlanılır, amma artıq yazılmış məlumatın rollback olunması sübut edilmir.

**Düzəliş:** test double real repository ilə insert edib dərhal sonra exception atsın. Catalog sətri, event və Insights projection-u yaranmış kimi qalmamalıdır.

### 4.6. Architecture testləri iki learning modulunun bütün asılılıqlarını əhatə etmir

**Sübut:** `ControllerBoundaryGuardTest.php:214–235` və `292–310` əvvəlki beş modulu skan edir. Yeni `315–324` yoxlaması Catalog üçün yalnız Insights import-unu qadağan edir; `327–347` isə Insights-un Catalog public API istinadlarını yoxlayır.

Məsələn, Catalog-a belə import əlavə edilməsi bu yeni qaydalarda ayrıca bloklanmır:

```php
use Modules\Tasks\Models\Task;
```

**Nəticə:** hazırkı branch-də belə dependency yoxdur. Ancaq “learning modulları mövcud sistemdən təcrid olunacaq” qaydası tam enforce edilmir.

**Düzəliş:** Catalog-un öz modulundan kənar TaskFlow module dependency-si olmasın. Insights yalnız Catalog public contract/DTO/event-inə bağlı olsun. Production modulların və host business kodunun learning modullarını çağırması da bloklansın. Mövcud production allowlist dəyişməsin.

### 4.7. Public API guard namespace alias ilə bypass edilir

**Sübut:** `ControllerBoundaryGuardTest.php:335` regex-i `Modules\\LearningCatalog\\...` şəklində tam reference axtarır. Aşağıdakı nümunə həmin regex-in yoxlamasından yayınır:

```php
use Modules\LearningCatalog as Catalog;

$entry = new Catalog\Models\LearningEntry;
```

Dəqiq regex ilə yoxlamada match siyahısı boşdur. Bu, hazırkı application kodunda internal model istifadəsi deyil; guard-un mənfi nümunə ilə təsdiqlənən boşluğudur.

**Düzəliş:** reference yoxlaması import alias-lərini də nəzərə alsın və ya bu kiçik lab üçün broad namespace import-larını açıq qadağan etsin. Allowed və forbidden nümunələr ayrıca fixture testləri ilə yoxlanılsın. Sadəcə daha geniş regex yazıb mənfi nümunəsiz buraxmaq kifayət deyil.

### 4.8. Yekun variantda dəqiqləşdiriləcək kiçik davranışlar

**Input validation:** `PublishLearningEntryData.php:7` yalnız `string $title` qəbul edir. `LearningEntryService.php:16–18` və repository də title üçün boşluq/uzunluq yoxlamır. Migration isə `string('title')` saxlayır. Yekun variantda boş/yalnız whitespace title və 255-dən uzun title DB-yə çatmadan rədd edilməlidir. Bu qayda ilk sənəddə detallı verilmədiyinə görə əlavə möhkəmləndirmə kimi qeyd olunur.

**Rebuild-in mənası:** hazırkı `RebuildLearningInsightsService.php:16–20` yalnız çatışmayan sətirləri əlavə edir; mövcud yanlış title-ı dəyişmir, artıq sətirləri silmir. `LearningInsightsProjectionSpecificationTest.php:99–117` bunu qəsdən qoruyur. Bu, create-only laboratoriyanın missing-event recovery məqsədinə uyğundur; tam snapshot replacement kimi təqdim edilməməlidir.

**Rebuild failure:** foreach daxilində top-level transaction yoxdur. İkinci write fail etsə birinci sətir qalmış ola bilər. Təkrar rebuild bunu tamamlaya bilər, amma yekun plan kiçik batch üçün vahid transaction seçir. Mövcud tapşırıq rebuild üçün ayrıca all-or-nothing tələb etməmişdi; bu, yeni planın aydın recovery qərarıdır.

**Read count service:** tövsiyə olunan fayl ağacında `LearningInsightsQueryService` adı var, branch-də isə yoxdur. Məcburi qəbul meyarları bunun ayrıca API-sini müəyyən etmir. Buna görə bunu tamamlanmamış əsas feature kimi qiymətləndirmirik və sırf fayl ağacını doldurmaq üçün pass-through service yaratmırıq.

### 4.9. Bug sayılmayan məhdudiyyətlər

- Queue, Redis, Outbox, Inbox və DLQ-nun olmaması verilmiş scope-a uyğundur.
- UI və HTTP endpoint-in olmaması düzgündür.
- Tam delivery guarantee yoxdur; bu, local-event laboratoriyasının əvvəlcədən sənədləşdirilmiş sərhədidir.
- `firstOrCreate` ilə eyni entry-nin təkrarı təhlükəsizdir. Laravel-in quraşdırılmış implementation-u unikallıq yarışında uyğun entry-ni yenidən oxuya bilir; bunu avtomatik “race condition bug” adlandırmaq düzgün deyil.
- Eyni event ID-ni fərqli entry ID ilə göndərmək etibarlı duplicate deyil, ziddiyyətli payload-dır. DB constraint belə ikinci sətiri rədd edir. Repository bütün DB xətalarını duplicate adı ilə udmamalıdır.
- Rebuild-created sətirdə sonradan həmin entry üçün event gəlsə entry unikallığı duplicate-i bloklayır; `source_event_id`-nin null qalması bu laboratoriyada məlumat itkisi sayılmır.

## 5. İcra edilmiş yoxlamalar və sübutun sərhədi

Git snapshot müqayisəsi, bütün yeni modul faylları, yeni testlər, registration, test discovery və architecture dəyişiklikləri oxunub. `git diff --check origin/main origin/feature/fidan` uğurludur.

Runtime yoxlamaları Windows + Herd PHP `8.4.24` ilə Fidan commit-inin ayrıca archive snapshot-unda aparıldı. Main checkout-u dəyişdirilmədi. Mövcud vendor ayrıca kopyalandı və yalnız snapshot-da `composer dump-autoload --no-scripts --no-interaction` ilə autoload yaradıldı; dependency install/update edilmədi.

Git archive ignored `public/build` fayllarını daşımadığı üçün ilk full-suite cəhdində frontend manifest-in olmaması mühit xətaları yaratdı. Frontend source-u branch-lərdə eyni olduğundan main-dəki mövcud build fixture-i snapshot-a kopyalandı və suite yenidən işlədildi. Aşağıdakı full-suite nəticəsi həmin tamamlanmış təkrar icradır; yeni Vite build-in təsdiqi deyil.

| Yoxlama | Nəticə |
|---|---|
| İki modulun təqdim edilmiş feature testləri | **13 PASS, 27 assertion** |
| Architecture suite | **29 PASS, 94 assertion** |
| Static gate: architecture + API route/Postman contract testləri | **32 PASS, 143 assertion** |
| Tam SQLite suite, review diagnostic-ləri xaric | **272 PASS, 1 937 assertion** |
| Dəyişən PHP faylları üçün Pint | **PASS** |
| Ayrıca review diagnostic testləri | **3 PASS, 10 assertion** |
| Git diff whitespace yoxlaması | **PASS** |

Tam SQLite əmri:

```text
php vendor/bin/pest --configuration phpunit.xml --exclude-group reviewdiagnostic --compact
```

Üç diagnostic test yalnız audit snapshot-unda yazılıb; main və Fidan branch-inə əlavə edilməyib, yuxarıdakı 272 branch testinə daxil deyil. Bu testlərin PASS olması aşağıdakı mövcud davranışları təsdiqləyir, onların düzgün yeni qəbul davranışı olduğunu deyil:

1. Caller outer transaction-u rollback etdikdən sonra Catalog sətri yoxdur, amma event artıq dispatch edilib.
2. SQLite üzərində boş və 256 simvolluq title yazıla bilir.
3. Rebuild-in ikinci write-ı exception atdıqda birinci projection qalır.

Architecture regex bypass-ı da ayrıca mənfi source nümunəsi ilə təsdiqlənib. Mövcud architecture suite-in keçməsi guard-un həmin boşluğunu aradan qaldırmır.

İşlədilməyən yoxlamalar:

- **MySQL:** bu read-only review üçün ayrıca audit test bazası hazırlanmadı və `.env.testing` snapshot-a daşınmadı. MySQL compatibility bu reportla təsdiqlənmir; yekun implementasiyanın məcburi gate-idir.
- **Yeni `npm run build`:** frontend source/dependency dəyişmədiyindən review zamanı ayrıca build alınmadı. Mövcud compiled fixture yalnız full-suite UI testlərinin hazırlanması üçün istifadə edildi.
- **Playwright:** yeni HTTP/UI flow-u yoxdur; bu review-da browser automation işə salınmadı.
- **Tam repository Pint:** yalnız bu branch-də dəyişən PHP faylları yoxlanıldı; bütün repository üçün format nəticəsi iddia edilmir.

`.env` və `.env.testing` oxunmadı/kopyalanmadı; real application migration və seeder işlədilmədi. Runtime DB SQLite `:memory:` idi. Beləliklə branch-in mövcud testləri keçir, amma §4-də göstərilən acceptance və guard boşluqları yenə tamamlanmalıdır.

## 6. Main üçün implementasiya qərarı

Fidanın iki modulu, contract/DTO/event quruluşu, repository ownership-i və testlərinin faydalı hissələri əsas götürüləcək. Ahmad branch-indən R1 kodu daşınmayacaq, çünki həmin snapshot-da belə kod yoxdur.

Unrelated history-ləri birləşdirmək əvəzinə Fidanın main-dən 29 fayllıq məzmun fərqi nəzərdən keçirilərək main üzərində tətbiq ediləcək. Bu review sənədi dəyişiklik paketinə qarışdırılmayacaq. Əsas branch-in həmin anda aktual vəziyyəti yenidən yoxlanılacaq; yeni əlaqəsiz dəyişikliklər overwrite edilməyəcək.

Bu mərhələnin nəticəsi testlərlə qorunan, UI və HTTP səthi olmayan iki kiçik R1 lab modulu olacaq. Beş production modulun və host business axınlarının refaktoru bu planın hissəsi deyil.

### 6.1. İcazəli fayllar

- `Modules/LearningCatalog/**` və `Modules/LearningInsights/**`;
- `modules_statuses.json` və iki modulun metadata-sı;
- `phpunit.xml`, `phpunit-mysql.xml`, `tests/Pest.php` — yalnız explicit test discovery;
- `tests/Architecture/ControllerBoundaryGuardTest.php` və lazım gəlsə `tests/Architecture/Support/SourceGuard.php`;
- `roadmap-r1-junior-practice.md` — aşağıda seçilən transaction/rebuild/validation davranışını və qəbul testlərini dəqiqləşdirmək üçün.

Mövcud route/controller/business service/repository-lər, cədvəllər, navigation, auth, media və production `docs-ibp` müqaviləsi dəyişdirilməyəcək. Yeni dependency, queue worker, generic bus və broker əlavə edilməyəcək. ROADMAP.md-də bütöv R1 tamamlandı kimi işarələnməyəcək.

## 7. Yekun publish flow-u

```text
PublishLearningEntryData
  title trim edilir və 1–255 simvol yoxlanılır
        ↓
LearningEntryService::publish()
        ↓
Catalog transaction
  LearningEntryRepositoryInterface::createPublished()
  r1_learning_entries INSERT
        ↓
LearningEntryPublished hazırlanır
  ShouldDispatchAfterCommit
        ↓
Açıq outer transaction varsa onun commit-i gözlənilir
  rollback → listener işləmir
  commit   → local synchronous dispatch
        ↓
RecordPublishedLearningEntry::handle()
        ↓
LearningInsightRepositoryInterface::recordPublishedIfMissing()
        ↓
r1_learning_insight_entries
  entry_id UNIQUE
  source_event_id UNIQUE, nullable
```

`ShouldDispatchAfterCommit` event class-ının native Laravel interface-idir. Synchronous listener queue-ya çevrilmir. Top-level publish zamanı transaction bitdikdən sonra listener həmin PHP çağırışında işləyir. Event payload əvvəlki dörd sahəni saxlayır; interface metadata üçün əlavə payload sahəsi yaratmır.

Title validation purpose-specific readonly input DTO-da qorunacaq: constructor daxilində trim, 1–255 simvol yoxlaması və məqsədli `InvalidLearningEntryTitle` exception-u. Yoxlama uğursuz olduqda transaction, insert və event yaranmır. HTTP olmadığı üçün Form Request/controller yaratmağa ehtiyac yoxdur.

Catalog publish metodu daxili use case-dir; başqa modulun write API-si kimi expose edilməyəcək. Insights yalnız read feed və public event-dən istifadə edəcək.

## 8. Duplicate handling flow-u

```text
İlk event: eventId=A, entryId=42
        ↓
Projection yoxdur → bir sətir yaranır

Eyni event yenə gəlir: eventId=A, entryId=42
        ↓
entry_id=42 mövcuddur → yeni sətir yaranmır

Rebuild entryId=42 üçün sətir yaratmışdı, event sonradan gəldi
        ↓
entry_id=42 mövcuddur → yenə tək sətir qalır
```

Fidanın `firstOrCreate` və DB constraint yanaşması saxlanılacaq. Repository listener və rebuild üçün transaction-neutral qalacaq; özbaşına ayrıca opaque transaction açmayacaq. Ziddiyyətli entry/event identity-si və başqa DB xətaları udulmayacaq.

Bu, projection səviyyəsində idempotency-dir. Ayrıca processed-message cədvəli, Inbox və exactly-once delivery iddiası əlavə edilməyəcək.

## 9. Missing projection recovery flow-u

```text
RebuildLearningInsightsService::rebuild()
        ↓
PublishedLearningEntryFeed::all()
        ↓
Catalog internal repository → readonly DTO siyahısı
        ↓
Insights top-level write transaction
  hər DTO üçün recordPublishedIfMissing(..., sourceEventId: null)
        ↓
hamısı uğurlu → commit
hər hansı write fail → bu rebuild-in yeni write-ları rollback
```

Feed oxunması write transaction-dan əvvəl tamamlanacaq. Feed fail edərsə projection write-ı başlamayacaq. Kiçik create-only dataset üçün pagination, batch worker və locking platforması əlavə edilməyəcək.

Burada rebuild yalnız çatışmayan projection-ları tamamlayır. Mövcud sətir/title/source-event metadata-sı qorunur, Catalog-da olmayan sətirlər silinmir. Bu məna praktika sənədində açıq yazılacaq. Update/delete və tam snapshot reconciliation ayrıca scope tələb edir.

Rebuild özü event publish etməyəcək və Catalog-a write etməyəcək. Əsas məlumat həmişə Catalog-da qalacaq.

## 10. Failure davranışı

| Hal | Catalog | Insights | Davranış |
|---|---|---|---|
| Title etibarsızdır | Yazılmır | Yazılmır | Məqsədli input exception-u |
| Catalog write-dan sonra xəta | Transaction rollback | Listener işləmir | Exception caller-ə qaytarılır |
| Outer transaction rollback | Rollback | Listener işləmir | After-commit callback icra edilmir |
| Listener qeydiyyatda yoxdur | Commit | Projection yoxdur | Publish işləyir; sonra rebuild mümkündür |
| Commit-dən sonra listener fail edir | Commit qalır | Projection çatışmaya bilər | Exception yayılır; avtomatik retry yoxdur |
| Commit ilə dispatch arasında proses dayanır | Commit qala bilər | Projection çatışmaya bilər | Outbox yoxdur; sonradan rebuild mümkündür |
| Eyni event təkrarlanır | Dəyişmir | İkinci sətir yaranmır | Idempotent no-op |
| Rebuild ortada fail edir | Dəyişmir | Həmin rebuild-in write-ları rollback | Problem aradan qalxandan sonra təkrar rebuild |

Consumer-in olmaması ilə consumer-in xəta atması fərqlidir. Optional listener qeydiyyatda olmayanda producer uğurludur. Synchronous listener exception atanda isə caller exception alır, baxmayaraq Catalog artıq commit edib. Bu hal testlə göstəriləcək; exception-u səssizcə udan blanket `catch` əlavə edilməyəcək. Caller-in publish-i kor-koranə təkrar etməsi ikinci Catalog entry yarada bilər; recovery projection rebuild ilə aparılacaq.

After-commit event queue/outbox zəmanəti vermir. Queue, Outbox, Inbox və DLQ yalnız mövcud məlumat bölməsində nəzəri şəkildə qalacaq.

## 11. Architecture qaydaları və yoxlanma flow-u

```text
LearningCatalog
  öz internals + Laravel/PHP
  başqa TaskFlow moduluna dependency yoxdur

LearningInsights
  öz internals + Laravel/PHP
  → Catalog Contracts\PublishedLearningEntryFeed
  → Catalog Data\PublishedLearningEntryData
  → Catalog Events\LearningEntryPublished

Production modullar və host business kodu
  → learning modullarına dependency yoxdur
```

Catalog-un öz public contract implementation-u öz internal repository/modelini istifadə edə bilər. Bu, cross-module sərhəd pozuntusu deyil. Insights-un öz migration/repository-si yalnız öz cədvəlinə müraciət edə bilər.

Test helper tam class reference, `use ... as ...`, namespace alias və grouped import nümunələrini nəzərə almalıdır. Bunun üçün yeni parser dependency quraşdırılmayacaq. Mövcud `SourceGuard` üzərində lab-a aid yeni, kiçik helper metodu əlavə ediləcək; əvvəlki production guard-ların semantikası saxlanılacaq. PHP token-ləri ilə kiçik reference yoxlaması və ya lab üçün açıq broad-import qadağası seçilə bilər; aşağıdakı mənfi fixture-lər hansı implementation seçilsə də fail etməlidir:

- Catalog → Insights;
- Catalog/Insights → Projects/Tasks/Media/Activity/Dashboard;
- Insights → Catalog model/repository/service/input DTO;
- namespace alias ilə eyni qadağan reference;
- tam qualified class və container resolution ilə qadağan reference;
- production modul/host business kodu → learning modulu;
- Insights → `r1_learning_entries` birbaşa table access;
- yeni learning route/controller/Livewire/Blade və provider daxilində birbaşa Route registration-ı.

Allowed contract/DTO/event üçün müsbət fixture-lər də olmalıdır. Guard həm qanuni əlaqəni qəbul etməli, həm qadağan əlaqəni aşkar etməlidir. Mövcud production dependency allowlist-i wildcard ilə genişləndirilməyəcək.

## 12. İcra addımları və bitmə şərtləri

### Addım 1 — Fidanın minimal bazasını main üzərinə uyğunlaşdır

Main-in aktual vəziyyətini və dirty workspace-i yoxla. İki modul, migration, registration və test discovery fərqini nəzərdən keçirərək tətbiq et. Branch history-lərini zorla merge etmə. Köhnə production koduna dəyişiklik daşınmadığını fayl diff-i ilə təsdiqlə.

Bitmə şərti: iki provider boot edir, repository/public feed binding-ləri həll olunur, SQLite test profili iki cədvəli və iki modulun testlərini discover edir.

### Addım 2 — Input və after-commit semantikasını tamamla

Readonly input DTO üçün trim/1–255 qaydasını və məqsədli exception-u əlavə et. `LearningEntryPublished` `ShouldDispatchAfterCommit` implementasiya etsin. Event payload, Catalog ownership və synchronous listener saxlanılsın.

Bitmə şərti: etibarsız input heç bir write/event yaratmır; top-level commit-dən sonra listener işləyir; outer rollback-də işləmir.

### Addım 3 — Projection və atomic missing-only rebuild

Fidanın idempotent repository-sini saxla. Rebuild DTO siyahısını public feed-dən alsın, projection write-larını vahid top-level transaction-da etsin. Listener/repository əlavə transaction açmasın. Mövcud projection-lar qorunsun, saxta event ID yaranmasın.

Bitmə şərti: duplicate no-op, missing rows bərpa olunur, təkrar rebuild tək sətir saxlayır, ikinci write failure birinci yeni write-ı rollback edir.

### Addım 4 — Real inteqrasiya və failure testlərini əlavə et

Mövcud izolə contract/listener testlərini saxla və aşağıdakı matrix-i tamamla. Integration testində producer consumer internals-ını application koduna import etməməlidir; test harness-in iki modulun açıq davranışını birlikdə yoxlaması normaldır.

### Addım 5 — Architecture guard və mənfi fixture-lər

Tam dependency direction, public API allowlist və alias bypass yoxlamalarını əlavə et. Production dependency qaydalarını zəiflətmə. Müsbət və mənfi fixture-ləri birlikdə işlə.

### Addım 6 — Qəbul gate-ləri və praktika sənədi

SQLite, dedicated Herd MySQL, architecture, static, format və build yoxlamalarını işlə. `roadmap-r1-junior-practice.md` daxilində yeni dəqiqləşdirmələri və test siyahısını yenilə. Bu lab-ı production `docs-ibp` business funksiyası və ya bütöv R1-in tamamlanması kimi göstərmə.

## 13. Test matrix-i

| Qat | Ssenari | Sübut |
|---|---|---|
| Input | Boş, whitespace və 256 simvol | Exception; Catalog/event/projection yoxdur |
| Input | 1 və 255 simvol, ətraf whitespace | Qəbul; canonical trimmed title |
| Integration | Real publish və qeydiyyatlı listener | Hər iki modulda uyğun ID/title/tarix; event ID mövcuddur |
| Contract | Real feed | Readonly DTO list; model/builder yoxdur; sıralama sabitdir |
| Transaction | Insert-dən sonra exception | Catalog rollback; event/projection yoxdur |
| Transaction | Real top-level commit | Listener commit-dən əvvəl işləmir, sonra işləyir |
| Transaction | Outer commit və outer rollback | Yalnız outer commit listener-i işlədir |
| Consumer | Listener yoxdur | Catalog publish uğurludur; projection yoxdur |
| Consumer | Listener exception-u | Catalog committed qalır; exception görünür |
| Recovery | Listener yoxdur/fail edir, sonra real feed ilə rebuild | Çatışmayan projection bərpa olunur |
| Idempotency | Eyni event iki dəfə | Bir sətir, metadata dəyişmir |
| Idempotency | Eyni entry üçün ayrı event | Bir sətir |
| Idempotency | Rebuild, sonra gecikmiş event | Bir sətir, null source-event metadata-sı qorunur |
| Constraint | Eyni event ID, fərqli entry ID | Ziddiyyətli ikinci sətir DB tərəfindən rədd olunur |
| Rebuild | Boş feed və təkrar çağırış | No-op/idempotent nəticə |
| Rebuild | Mövcud və çatışmayan sətirlər | Mövcud qorunur, yalnız çatışmayan əlavə edilir |
| Rebuild | İkinci write fail edir | Həmin rebuild-in yeni write-ları rollback |
| Migration | Fresh və rollback | İki yeni cədvəl/index yaranır və təhlükəsiz silinir |
| Architecture | Qanuni və qadağan reference fixture-ləri | Allowed pass, forbidden fail |
| Regression | Mövcud suite | Əvvəlki business assertion-lar dəyişmədən keçir |

Real commit testləri `RefreshDatabase`-in gizli outer test transaction-u ilə qarışdırılmamalıdır. Bunun üçün `Modules/LearningInsights/tests/Integration/R1LearningFlowTest.php` daxilində `Tests\TestCase` + `DatabaseMigrations` istifadə edən bir focused test qrupu yaradılacaq və hər iki PHPUnit profilində discover ediləcək. `tests/Pest.php` həmin qovluğa `RefreshDatabase` tətbiq etməyəcək. Bu qrupa yalnız real commit tələb edən testlər daxil ediləcək; matrix-in hər sətri üçün ayrıca fayl yaratmaq lazım deyil. SQLite `:memory:` və dedicated MySQL fail-closed bootstrap saxlanılacaq.

Feature testlərində Event fake istifadə etmək olar, amma real wiring/commit/recovery sübutları Event fake olmadan yazılmalıdır. Real module disable yerinə in-memory listener qeydiyyatının ayrılması kifayətdir; `modules_statuses.json` test zamanı diskdə dəyişdirilməyəcək. Production runtime plugin sistemi bu task-a daxil deyil.

## 14. Son verification və təhvil

```text
composer test
composer test:architecture
composer test:static
composer test:mysql
composer format:check
npm run build
git diff --check
```

MySQL yalnız `taskflow_test` prefix-li dedicated database və mövcud fail-closed bootstrap ilə işlədiləcək. Real application migration/seeder işə salınmayacaq. Yeni HTTP/UI səthi olmadığı üçün bu lab üçün yeni Playwright journey yaradılmayacaq.

Təhvil report-u test/assertion sayı, runtime, işlədilən və skipped gate-lər, dəyişən fayllar, iki yeni cədvəlin təsiri və local-event delivery məhdudiyyətlərini göstərməlidir. Təhlükəsizlik təsiri: yeni route/permission səthi yoxdur; payload secretsizdir; module/table ownership testlə qorunur.

Praktika yalnız real iki modulun flow-u, after-commit/rollback, idempotency, missing-event recovery və forbidden-boundary testləri keçdikdən sonra hazır sayılır. Main-ə push və ya merge bu review/plan işinin hissəsi deyil.
