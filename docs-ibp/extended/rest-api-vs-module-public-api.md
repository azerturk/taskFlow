# REST API ilə modulun public API-si eyni deyil

## Sual

`/api/v1/tasks` da API-dir, `PublishedLearningEntryFeed` də API adlanır. Fərq nədir?

## Sadə cavab

API başqa tərəfin sənin imkanından necə istifadə edəcəyini müəyyən edən müqavilədir. **REST API** şəbəkə üzərindən HTTP request/JSON ilə çağırılır. **Modulun public API-si** isə eyni PHP tətbiqində başqa modulun istifadə etməsinə icazə verilən type-lardır.

“Public” burada “internetə açıq və authorization-suz” demək deyil.

## Cari vəziyyət

- Məhsulun REST səthi `/api/v1`-dir; qorunan əməliyyatlar Sanctum və policy-lərdən keçir.
- R1-də LearningCatalog PHP public səthi təqdim edir; HTTP route/controller/UI təqdim etmir.
- LearningInsights üçün icazəli Catalog type-ları yalnız `PublishedLearningEntryFeed`, `PublishedLearningEntryData`, `LearningEntryPublished`-dir.

## Termini gündəlik dillə düşünək

**Contract** razılaşmadır: çağıran tərəf hansı metodu çağıracağını və hansı nəticəni alacağını bilir. **Interface** bu razılaşmanın PHP-də metod imzaları ilə yazılmış formasıdır. **DTO** isə burada nəticə məlumatını daşıyan sadə obyektdir; özü DB query-si etmir.

Restoran nümunəsində menyu public müqavilədir, mətbəxin bütün dolabları deyil. Menyudan yemək sifariş edə bilməyin mətbəxdə istənilən alətə toxunmaq hüququ vermir. Catalog-un public feed-i də consumer-ə bütün internal repository/model imkanlarını vermir.

## Həyat ssenarisi: eyni title, iki fərqli yol

Fidan Tasks siyahısını xarici API client-də görmək istəyir. Əhməd isə R1-də itmiş Insights surətlərini bərpa edir. İkisinin ehtiyacı məlumat oxumaqdır, amma çağırış yolu fərqlidir.

1. Əvvəl Fidanın client-i tətbiqdən kənardadır; HTTP request göndərməlidir.
2. Client `/api/v1/tasks` çağırır; server token, ability və record access yoxlamalarını edir.
3. Cavab Resource-un seçdiyi JSON sahələridir; client PHP model almır.
4. Əhmədin rebuild service-i isə artıq eyni Laravel tətbiqindədir.
5. O, container-dən verilən `PublishedLearningEntryFeed` ilə `all()` çağırır.
6. Sonra əlində readonly DTO siyahısı olur; HTTP response və token issuance baş vermir.

```text
Əvvəl: xarici client / daxili modul
Kim nə edir: client HTTP göndərir / modul PHP contract çağırır
Sonra: JSON response / PHP DTO siyahısı
```

## Real kod

Catalog-un oxu müqaviləsi:

```php
interface PublishedLearningEntryFeed
{
    /** @return list<PublishedLearningEntryData> */
    public function all(): array;
}
```

Insights həmin müqavilədən PHP çağırışı ilə istifadə edir:

```php
$entries = $this->entries->all();
```

Burada URL, token, JSON serialize və HTTP round-trip yoxdur. Container `PublishedLearningEntryFeed` üçün provider-də qeyd edilmiş implementasiyanı verir. DTO-lar mutable Eloquent model deyil.

## Axın

```text
HTTP client → /api/v1/tasks → auth/ability/policy → service → JSON Resource
Insights → PublishedLearningEntryFeed::all() → Catalog repository → readonly DTO-lar
```

## Yanlış nəticə

“Catalog modeli public PHP class-dır, deməli import edə bilərəm” düzgün deyil. PHP visibility ilə **arxitektura üzrə public səth** eyni şey deyil. Insights-in Catalog model/repository/service-inə müraciəti architecture guard ilə qadağandır.

Public contract da öz-özünə auth vermir. R1 HTTP səthi olmayan izolə laboratoriyadır; onu məhsul endpoint-inə çevirmək ayrıca dizayn və authorization işi tələb edər.

## Kodun vacib hissələrini açaq

- `interface PublishedLearningEntryFeed`: consumer konkret Eloquent class-ının adını bilməyə məcbur deyil.
- `all()`: bu lab-ın təqdim etdiyi oxu əməliyyatıdır; publish etmək və modeli dəyişmək imkanı deyil.
- `array`: nəticənin PHP array olduğunu bildirir.
- `list<PublishedLearningEntryData>`: comment alətlərə/developer-ə hər elementin hansı DTO olduğunu göstərir; HTTP schema deyil.
- `$this->entries->all()`: artıq inject edilmiş contract obyektinə lokal çağırışdır; URL açmır.

Provider contract-a hansı implementasiyanın veriləcəyini qeyd edir. Bunu kitabxana masasından kitab istəməyə bənzət: kitabın hansı rəfdən gətirilməsini çağıran tərəf idarə etmir.

## Niyə internal model vermirik?

Model verilsə consumer onun relation-larına, persistence metodlarına və daxili sahələrinə bağlana bilər. Sonra Catalog daxildə struktur dəyişəndə Insights də qırıla bilər. Read DTO yalnız lazım olan `id`, `title`, `publishedAt` məlumatını verir.

Bu da “heç vaxt dəyişməyəcək müqavilə” demək deyil. Public DTO sahəsi dəyişərsə consumer və testlərin uyğunluğu yenə nəzərdən keçirilməlidir.

## Konkret uğursuz nümunə

Junior Insights-ə `LearningEntry::query()->get()` əlavə edir. Nəticə hazırda oxuna bilər, amma consumer internal model və cədvəlin formasından asılı olur. R1 architecture testi bunu rədd etməlidir. Düzgün yol feed contract-ını istifadə etməkdir, qadağan import-u alias ilə gizlətmək deyil.

Digər yanlışlıq: contract adında public sözünü görüb onu authorization-suz HTTP route kimi elan etmək. Cari R1-də belə route yoxdur; əlavə edilməsi bu sənədin göstərişi deyil.

## Özünü yoxla

1. Public modul API-si mütləq internet endpoint-idirmi? **Xeyr; burada PHP contract/DTO/event sərhədidir.**
2. `all()` nəticəsi Eloquent model siyahısıdırmı? **Xeyr; readonly `PublishedLearningEntryData` siyahısıdır.**
3. REST API-də token ability-si policy-ni əvəz edirmi? **Xeyr; ayrıca access qatları qalır.**

Vizualı mətnlə birlikdə oxu: [public feed diagramının addımları](../diagrams/labs/public-feed.md), [request qatları](../diagrams/system/request-layers.md).

## Kod və yoxlama

- [REST müqaviləsi](../technical/API.md)
- [Feed contract](../../Modules/LearningCatalog/app/Contracts/PublishedLearningEntryFeed.php)
- [Feed implementasiyası](../../Modules/LearningCatalog/app/Services/EloquentPublishedLearningEntryFeed.php)
- [Rebuild caller](../../Modules/LearningInsights/app/Services/RebuildLearningInsightsService.php)
- [Public səth allowlist-i](../../tests/Architecture/Support/LearningBoundaryGuard.php)
- [Modul sərhədi testləri](../../tests/Architecture/R1LearningBoundaryTest.php)
