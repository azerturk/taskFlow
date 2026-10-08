# LearningInsights — bərpa edilə bilən projection

**Vəziyyət: implementasiya edilmiş R1 tədris modulu.** Öz səhifəsi, REST endpoint-i, queue worker-i və məhsul modulları ilə əlaqəsi yoxdur.

## Sadə dillə: surət niyə ayrıca saxlanır?

Bu lab-da iki modulun ayrı məsuliyyətini öyrənmək istəyirik. Catalog əsas qeydə sahibdir; Insights həmin qeyddən oxu projection-u düzəldir. Buradakı əlavə cədvəl performans ehtiyacının sübutu deyil, tədris seçimidir.

Projection çatışmırsa source-dan tamamlamaq olar. Amma mövcud projection-u hər event-də overwrite etmirik: create-only lab qaydası ilk metadata-nı saxlamaqdır. Ona görə duplicate qorunması ilə tam məlumat sinxronizasiyası eyni şey deyil.

Əvvəl [entry/event ID və duplicate dərsini](../../diagrams/labs/duplicate.md), sonra [rebuild dərsini](../../diagrams/labs/rebuild.md) oxu. Bu iki mövzunu ayırdıqdan sonra aşağıdakı repository və transaction kodu daha aydın olacaq.

## Məqsəd

Catalog entry-si publish ediləndə həmin faktın kiçik oxu surətini saxlamaq. Əsas məlumat Catalog-dadır; Insights məlumatı yenidən qurula bilən projection-dur. Consumer Catalog-un publish əməliyyatını idarə etmir.

## Fayl xəritəsi

| Fayl | Məsuliyyət |
|---|---|
| [RecordPublishedLearningEntry](../../../Modules/LearningInsights/app/Listeners/RecordPublishedLearningEntry.php) | Typed event-i öz repository-sinə ötürür |
| [Repository contract](../../../Modules/LearningInsights/app/Repositories/Contracts/LearningInsightRepositoryInterface.php) | `recordPublishedIfMissing(...)` yazı sərhədi |
| [Eloquent repository](../../../Modules/LearningInsights/app/Repositories/Eloquent/EloquentLearningInsightRepository.php) | `entry_id` üzrə `firstOrCreate` |
| [RebuildLearningInsightsService](../../../Modules/LearningInsights/app/Services/RebuildLearningInsightsService.php) | Public feed ilə missing-only recovery və atomik write-lar |
| [Service provider](../../../Modules/LearningInsights/app/Providers/LearningInsightsServiceProvider.php) | Repository binding-i, event listener və migration qeydiyyatı |

Listener Eloquent query qurmur və transaction açmır. Repository də transaction-neutral-dır; rebuild-in vahid write transaction-una service sahibdir.

## Normal event axını

```text
Catalog real commit
  → LearningEntryPublished
  → qeydiyyatlı synchronous listener
  → recordPublishedIfMissing(entryId, eventId, title, publishedAt)
  → Insights-un öz cədvəlinə yalnız yoxdursa INSERT
```

Listener `ShouldQueue` implementasiya etmir. Prosesin dayanması və ya listener failure üçün avtomatik retry zəmanəti yoxdur.

## Duplicate nə deməkdir?

Repository-nin real yanaşması:

```php
LearningEntryInsight::query()->firstOrCreate(
    ['entry_id' => $entryId],
    [
        'source_event_id' => $sourceEventId,
        'title' => $title,
        'published_at' => $publishedAt,
    ],
);
```

Bu kod yalnız Insights repository-sinə məxsusdur. Projection identity-si `entry_id`-dir; event ID deyil. Mövcud sətir tapılıbsa title, tarix və source-event dəyəri dəyişdirilmir.

| Gələn məlumat | Nəticə |
|---|---|
| Yeni entry və yeni event | Bir projection yaranır |
| Eyni entry/event təkrar gəlir | Əvvəlki projection qalır |
| Mövcud entry üçün fərqli event gəlir | Yenə bir projection, ilk metadata qalır |
| Rebuild-dən sonra həmin entry üçün event gəlir | Rebuild sətri qalır; null event ID dəyişdirilmir |
| Yeni entry əvvəl başqa entry-də saxlanmış event ID-ni istifadə edir | UNIQUE constraint INSERT-i rədd edir; DB exception-u gizlədilmir |

![Projection identity-si və təkrar event](../../diagrams/labs/duplicate.svg)

Bu, full Inbox Pattern deyil. `source_event_id` bütün emal edilmiş message-lərin jurnalı deyil və exactly-once delivery zəmanəti vermir.

## Rebuild addımları

```php
$entries = $this->entries->all(); // Public feed; write transaction-dan əvvəl.

DB::transaction(function () use ($entries): void {
    foreach ($entries as $entry) {
        $this->recordMissingProjection($entry);
    }
});
```

1. Catalog public feed-i readonly DTO-ları qaytarır.
2. Feed exception atarsa Insights write transaction-u başlamır.
3. Service hər DTO üçün öz repository-sini `sourceEventId: null` ilə çağırır.
4. Bütün write-lar alınarsa commit edilir.
5. Bir write fail edərsə həmin rebuild-in yeni write-ları rollback olur; əvvəlki projection-lar qorunur.

Rebuild Catalog cədvəlini birbaşa oxumur, Catalog-a yazmır və yeni event göndərmir. Yeni event baş vermədiyinə görə saxta event UUID-si yaradılmır.

![Listener failure və atomik missing-only recovery](../../diagrams/labs/rebuild.svg)

## Rebuild nə etmir?

- Mövcud title və publish tarixini yeniləmir.
- Catalog-da olmayan əlavə Insights sətirlərini silmir.
- Publish əməliyyatını təkrarlamır.
- Avtomatik scheduler/queue retry-si yaratmır.
- Feed oxunması ilə write-ları vahid cross-module snapshot transaction-una çevirmir.

Feed-dən sonra yeni entry yaranarsa onun listener-i və ya növbəti rebuild projection-u tamamlayır. Bu kiçik create-only lab-da pagination, event ordering, tam reconciliation və broker yoxdur.

## Listener exception-u zamanı recovery

Catalog write commit etdikdən sonra Insights listener-i fail edə bilər. Caller exception alır, amma source entry qalır. Problemi aradan qaldırdıqdan sonra izolyasiya edilmiş test/application çağırışı ilə `RebuildLearningInsightsService::rebuild()` həmin entry üçün projection yarada bilər. Publish-i yenidən çağırmaq eyni entry-ni bərpa etmir; yeni entry yaradır.

Bu bərpa yolu outbox əvəzi deyil. Proses commit ilə dispatch arasında dayanarsa event itə bilər; yalnız source entry-nin feed-də qalması sonrakı rebuild-i mümkün edir.

## Sxem və sərhəd

[Migration](../../../Modules/LearningInsights/database/migrations/2026_10_03_000001_create_r1_learning_insight_entries_table.php) `r1_learning_insight_entries` cədvəlini yaradır:

- `id`: öz primary key-i;
- `entry_id`: unikal, Catalog entry identity-si;
- `source_event_id`: nullable və unikal;
- `title`, `published_at`, timestamps.

`entry_id` üçün foreign key yoxdur. Bunu bütün production modulları üçün FK qadağası kimi başa düşmək olmaz; [məlumat modeli](../../technical/DATA_MODEL.md) məhsul əlaqələrini ayrıca göstərir.

## Test sübutları

- [Insights Feature testləri](../../../Modules/LearningInsights/tests/Feature/LearningInsightsProjectionSpecificationTest.php): duplicate metadata qorunması, ziddiyyətli event ID, təkrar/boş rebuild, late event, feed failure və transaction-neutral repository.
- [R1 Integration testləri](../../../Modules/LearningInsights/tests/Integration/R1LearningFlowTest.php): real listener wiring, outer commit/rollback, listener failure sonrası real feed recovery, ikinci write rollback və migration indeksləri.
- [Architecture testləri](../../../tests/Architecture/R1LearningBoundaryTest.php): Insights yalnız təsdiqlənmiş Catalog contract/DTO/event istifadə edir, başqa modul cədvəlinə query etmir.

[Laboratoriyanın ümumi xəritəsi](README.md) · [LearningCatalog](LEARNING_CATALOG.md).
