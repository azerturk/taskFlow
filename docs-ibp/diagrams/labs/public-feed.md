# Bir modul başqa moduldan məlumatı necə oxuyur?

## Bu izahın məqsədi

Tutaq ki, LearningInsights çatışmayan qeydlərini tamamlamaq istəyir. Bunun üçün LearningCatalog-da hansı qeydlərin olduğunu bilməlidir. Amma Catalog-un cədvəlini özbaşına oxumamalıdır.

Catalog ona belə bir imkan verir: «`all()` metodumu çağır, sənə icazə verdiyim məlumatları qaytarım». Bu, **modulun public API-si**dir: başqa modulun istifadə etməsinə razılıq verilmiş PHP müqaviləsi. Burada internetə açıq endpoint nəzərdə tutulmur.

Bu səhifədən sonra şəkildəki hər oxun hansı çağırış və ya cavab olduğunu izah edə bilməlisən.

## Əvvəlcə sözləri anlayaq

- **Consumer** məlumatdan istifadə edən tərəfdir. Bu nümunədə Insights-dır.
- **Contract** iki tərəfin razılaşdığı metod və nəticə formasıdır. Burada `PublishedLearningEntryFeed` interface-idir.
- **Implementation** həmin vədi real kodla yerinə yetirən class-dır. Burada `EloquentPublishedLearningEntryFeed`-dir.
- **Repository** öz modulunun database sorğularını yerinə yetirən qatdır.
- **DTO** yalnız lazım olan sahələri daşıyan məlumat obyektidir. Bu nəticə DTO-su database modeli deyil.
- **Synchronous** çağırışda caller cavabı həmin prosesdə gözləyir; məlumat sonradan worker ilə gəlmir.

## Şəkli aç və yuxarıdan aşağıya izlə

![Public feed ilə modul sərhədindən oxuma](public-feed.svg)

Bu, zaman ardıcıllığı şəklidir. Yuxarıdakı dörd qutu iştirakçıları göstərir. Onların altındakı şaquli kəsik xətlər boyunca aşağı endikcə əməliyyat irəliləyir. Bu şəkildə sağdan sola qayıdan kəsik oxlar cavabdır; ayrıca queue deyil.

1. Soldakı **LearningInsights** rebuild service-idir. «Mənə bütün publish edilmiş qeydləri ver» deyərək `all()` çağırır.
2. Çağırış **Public feed contract** sərhədindən keçir. Insights konkret Eloquent class-ını seçmir.
3. Laravel container əvvəlcədən qeydiyyata alınmış uyğunluğu tapır: bu interface üçün **Catalog feed** class-ı qurulur.
4. Feed öz modulunun **Catalog repository**-sinə `allPublished()` çağırır. Database query-ni Insights deyil, Catalog yazır.
5. Repository `id` üzrə sıralanmış daxili modelləri feed-ə qaytarır.
6. Feed hər modeli `PublishedLearningEntryData` obyektinə çevirir.
7. Insights yalnız həmin readonly DTO siyahısını alır. Modelin `save()`, `delete()` və relation imkanları sərhəddən keçmir.

Aşağıdakı iki böyük qutu qaydanı xatırladır: consumer üçün icazəli üç type var, Catalog-un modeli/repository-si/service-i isə onun daxili işidir.

## Kiçik ssenari

Bu rəqəmlər izah üçündür, real bazadan çıxarış deyil. Catalog-da iki qeyd olduğunu düşün:

| Catalog ID-si | Başlıq |
|---:|---|
| 7 | Module Boundary |
| 8 | Public API |

Insights `all()` çağıranda iki DTO alır. Hər DTO-da `id`, `title`, `publishedAt` olur. Insights bundan öz projection-unu yaratmaq üçün istifadə edə bilər, amma alınan obyekt Catalog-a yazı etmək üçün vasitə deyil.

Yəni «məlumatı oxumağa icazə verilib» ilə «digər modulun daxili vəziyyətini dəyişməyə icazə verilib» eyni deyil.

## Müqavilənin real kodu

[PublishedLearningEntryFeed](../../../Modules/LearningCatalog/app/Contracts/PublishedLearningEntryFeed.php) daxilindən:

```php
interface PublishedLearningEntryFeed
{
    /** @return list<PublishedLearningEntryData> */
    public function all(): array;
}
```

`interface` nəticənin necə alınmasını göstərmir. Yalnız `all()` adlı metodun olacağını deyir. `array` PHP tipidir; şərhdəki `list<PublishedLearningEntryData>` siyahıdakı elementlərin formasını dəqiqləşdirir. Bu, «istənilən məlumatı qaytar» müqaviləsi deyil.

[Nəticə DTO-su](../../../Modules/LearningCatalog/app/Data/PublishedLearningEntryData.php):

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

`readonly` qurulandan sonra sahələrə başqa dəyər yazmağı qadağan edir. `DateTimeImmutable` də tarixi yerində dəyişməyə imkan vermir. Bunlar database sətrini kilidləmir: sadəcə consumer-ə verilən obyektin sabit məlumat daşımasını təmin edir.

## Interface özü işləmirsə real class necə seçilir?

[Catalog provider](../../../Modules/LearningCatalog/app/Providers/LearningCatalogServiceProvider.php) bu uyğunluğu qeyd edir:

```php
$this->app->bind(
    PublishedLearningEntryFeed::class,
    EloquentPublishedLearningEntryFeed::class,
);
```

Sadə dillə: «Kimsə `PublishedLearningEntryFeed` istəsə, ona `EloquentPublishedLearningEntryFeed` ver». Insights service-inin constructor-u interface tələb edir; Laravel ona bu real obyekti ötürür. Buna dependency injection deyilir.

## Nəticə və xəta zamanı nə olur?

Feed oxuması özü heç bir Catalog və ya Insights sətri yaratmır. Yazı ayrıca rebuild mərhələsindədir.

Catalog repository-si oxuma zamanı xəta atsa caller nəticə almır. Rebuild public feed-i öz write transaction-undan **əvvəl** çağırdığı üçün bu xətada yeni projection yazısı başlamır.

`all()` bütün siyahını yaddaşa alır. Kiçik laboratoriya üçün bu sadə seçimdir; böyük məlumat həcmi üçün pagination artıq ayrıca dizayn qərarı olardı. Mövcud kodda pagination varmış kimi düşünmə.

## Tez qarışan suallar

**Public API-dirsə Postman-da hansı URL-i açaq?** Heç birini. Bu laboratoriyada HTTP route yoxdur. Public sözü burada «digər PHP modullarına açıq» deməkdir.

**Catalog modelini qaytarmaq daha asan deyilmi?** Asan görünə bilər, amma consumer-i daxili field/relation/persistence üsuluna bağlayar. DTO lazım olan məlumatı verir, artıq imkanları vermir.

**Insights Catalog-un publish service-ini də çağıra bilərmi?** Xeyr. Bu lab üçün açıq siyahı yalnız feed contract-ı, nəticə DTO-su və `LearningEntryPublished` event-idir. Input `PublishLearningEntryData` bu siyahıya daxil deyil.

## Özünü yoxla

1. Database sorğusunu kim edir? **Catalog repository-si.**
2. Consumer nəticə üçün gözləyirmi? **Bəli, bu direct synchronous çağırışdır.**
3. DTO-nun title-ını dəyişməklə Catalog yenilənərmi? **Xeyr; readonly buna imkan vermir və DTO model deyil.**

## Kodu izləmək üçün

- [Rebuild caller-i](../../../Modules/LearningInsights/app/Services/RebuildLearningInsightsService.php)
- [Model → DTO çevrilməsi](../../../Modules/LearningCatalog/app/Services/EloquentPublishedLearningEntryFeed.php)
- [Catalog repository-si](../../../Modules/LearningCatalog/app/Repositories/Eloquent/EloquentLearningEntryRepository.php)
- [Feed sırası və payload testləri](../../../Modules/LearningCatalog/tests/Feature/LearningCatalogPublishSpecificationTest.php)
- [Public sərhəd testləri](../../../tests/Architecture/R1LearningBoundaryTest.php)
- [REST API ilə müqayisə](../../extended/rest-api-vs-module-public-api.md)

Növbəti mövzu: [publish və after-commit](publish.md). [Bütün diagramların xəritəsi](../README.md).
