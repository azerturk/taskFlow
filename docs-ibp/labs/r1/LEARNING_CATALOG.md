# LearningCatalog — qeydin əsas sahibi

**Vəziyyət: implementasiya edilmiş R1 tədris modulu.** Məhsulun Tasks və Projects axınlarına qoşulmur; istifadəçiyə açıq Web/REST səthi yoxdur.

## Sadə dillə: Catalog öz işi haqqında nə vəd edir?

Catalog title qəbul edir, yoxlayır, əsas qeydi yaradır və «publish olundu» faktını elan edir. Consumer-in sonradan nə edəcəyini öz daxilində yazmır. Beləliklə Catalog Insights modelini, cədvəlini və listener-in daxili məntiqini tanımır.

Amma «digər modul heç nəyi tanımır» da doğru deyil: Insights açıq contract/DTO/event type-larını tanıyır. Məqsəd əlaqəni sıfırlamaq yox, onu kiçik və aydın sərhəddə saxlamaqdır.

Əvvəl [publish dərsini](../../diagrams/labs/publish.md), sonra [public feed dərsini](../../diagrams/labs/public-feed.md) oxu. Buradakı texniki fayl xəritəsində hər dərsdə gördüyün addımın real class-ını tapa bilərsən.

## Məqsəd

Sadə öyrənmə qeydinin title və publish tarixini saxlamaq. Məsələn, `Module Boundary` adlı entry yaradılır. Başqa modul bu qeydi yalnız Catalog-un təsdiqlənmiş read contract-ı və event-i ilə tanıyır.

## Fayl xəritəsi

| Fayl | Məsuliyyət |
|---|---|
| [PublishLearningEntryData](../../../Modules/LearningCatalog/app/Data/PublishLearningEntryData.php) | Input title-ı trim edir və 1–255 simvol qaydasını qoruyur |
| [LearningEntryService](../../../Modules/LearningCatalog/app/Services/LearningEntryService.php) | Publish use case və transaction sahibliyi |
| [Repository contract](../../../Modules/LearningCatalog/app/Repositories/Contracts/LearningEntryRepositoryInterface.php) | Məqsədli create və sıralanmış oxu metodları |
| [Eloquent repository](../../../Modules/LearningCatalog/app/Repositories/Eloquent/EloquentLearningEntryRepository.php) | Öz cədvəlinə INSERT və `id` üzrə sabit sıralama |
| [LearningEntryPublished](../../../Modules/LearningCatalog/app/Events/LearningEntryPublished.php) | Readonly event və native after-commit işarəsi |
| [Public feed implementasiyası](../../../Modules/LearningCatalog/app/Services/EloquentPublishedLearningEntryFeed.php) | Internal modelləri readonly DTO siyahısına çevirir |
| [Service provider](../../../Modules/LearningCatalog/app/Providers/LearningCatalogServiceProvider.php) | Repository/feed binding-ləri və migration discovery |

## Input nümunəsi

Aşağıdakı nümunə izolyasiya edilmiş test daxilindəki application çağırışıdır; HTTP endpoint deyil:

```php
use Modules\LearningCatalog\Data\PublishLearningEntryData;
use Modules\LearningCatalog\Services\LearningEntryService;

$data = new PublishLearningEntryData("  Module Boundary  ");
$entry = app(LearningEntryService::class)->publish($data);
// Saxlanılan title: "Module Boundary"
```

DTO readonly-dir. `Str::trim` Unicode whitespace-i də təmizləyir; uzunluq `mb_strlen(..., 'UTF-8')` ilə ölçülür. Boş və ya 256 simvolluq title `InvalidLearningEntryTitle` yaradır. HTTP səthi olmadığı üçün bu modula Form Request və HTTP exception renderer əlavə edilməyib.

## Transaction və event

Service əvvəl `DB::transaction(...)` daxilində repository-ni çağırır. Həmin çağırış qayıtdıqdan sonra UUID ilə event qurur. Event `ShouldDispatchAfterCommit` implementasiya etdiyindən caller-in outer transaction-u hələ açıqdırsa onun commit-i gözlənilir.

```text
Top-level publish: INSERT → commit → synchronous listener
Outer transaction: INSERT → event gözləyir → outer commit → listener
Outer rollback: INSERT rollback → listener işləmir
```

![Publish və transaction sərhədi](../../diagrams/labs/publish.svg)

Event-in yalnız dörd public sahəsi var: `eventId`, `entryId`, `title`, `publishedAt`. Tarix `DateTimeImmutable`-dır. Eloquent model, user, request, binary və storage path payload-a daxil deyil.

Listener olmadıqda publish uğurludur. Listener exception atarsa Catalog artıq committed ola bilər; exception caller-ə yayılır. Bu əməliyyat rollback olundu demək deyil. [Recovery izahı](LEARNING_INSIGHTS.md).

## Public və internal sərhəd

Insights yalnız bu üç type-a müraciət edə bilər:

```text
Modules\LearningCatalog\Contracts\PublishedLearningEntryFeed
Modules\LearningCatalog\Data\PublishedLearningEntryData
Modules\LearningCatalog\Events\LearningEntryPublished
```

`PublishLearningEntryData` input DTO-su public consumer müqaviləsinə daxil deyil. `Models`, `Repositories`, `Services`, geniş namespace alias-ləri və Catalog cədvəlinə birbaşa query qadağandır. PHP-də folder məxfiliyi öz-özünə tətbiq olunmur; sərhədi [architecture testləri](../../../tests/Architecture/R1LearningBoundaryTest.php) qoruyur.

Public feed `all(): array` ilə `list<PublishedLearningEntryData>` qaytarır. Hər element readonly `id`, `title`, `publishedAt` saxlayır; model və ya Builder deyil. Siyahı repository-də `id` üzrə sıralanır. Kiçik lab üçün bütün entry-lər yaddaşa alınır; pagination/batch worker yoxdur.

![Public feed-dən internal repository-yə qədər oxu](../../diagrams/labs/public-feed.svg)

## Məlumat modeli

[Migration](../../../Modules/LearningCatalog/database/migrations/2026_10_03_000000_create_r1_learning_entries_table.php) yalnız `r1_learning_entries` yaradır: `id`, `title`, `published_at`, `created_at`, `updated_at`. Modeldə `published_at` immutable date cast-dır. Draft/update/delete use case-ləri yoxdur; bütün entry-lər publish olunmuş qeyddir.

## Nə ilə sübut olunur?

- [Catalog Feature testləri](../../../Modules/LearningCatalog/tests/Feature/LearningCatalogPublishSpecificationTest.php): title limitləri, Unicode trim, rollback, minimum event payload, feed sıralaması və listenersiz publish.
- [R1 Integration testləri](../../../Modules/LearningInsights/tests/Integration/R1LearningFlowTest.php): real commit, outer rollback, listener failure və migration davranışı.

Testi saxtalaşdırmaq üçün production registration faylı diskdə enable/disable edilmir. Listener olmaması testin yaddaşdakı dispatcher qeydiyyatından ayrılması ilə göstərilir.

[Laboratoriyanın ümumi xəritəsi](README.md) · [LearningInsights](LEARNING_INSIGHTS.md).
