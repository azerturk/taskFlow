# Modulun aktivliyi və listener-in optional olması

## Sual

Catalog listener olmadan publish edə bilirsə Insights qovluğunu istənilən vaxt silə bilərik?

## Sadə cavab

Xeyr. “Bu event-in consumer-i yoxdur” ilə “modul təhlükəsiz çıxarıla bilər” ayrı məsələlərdir. Registration, autoload, provider, migration və qalan kodun dependency-ləri də yoxlanılmalıdır.

## Cari vəziyyət

`modules_statuses.json` hər iki R1 modulunu aktiv göstərir. Insights provider-i öz repository binding-ini, migration discovery-ni və listener-i qeyd edir. Catalog service-i Insights class-ına import etmir. Amma Insights Catalog-un üç public type-ına bağlıdır.

## Terminləri sadələşdirək

**Registration/qeydiyyat** Laravel-ə hansı provider/binding/listener-in mövcud olduğunu bildirməkdir. **Autoload** PHP class adını müvafiq fayldan yükləmək mexanizmidir. **Optional consumer** producer-in müəyyən listener olmadan əsas işini edə bilməsidir; generic uninstall zəmanəti deyil.

Bir xəbəri oxuyan abunəçinin siyahıdan çıxması ilə poçt şöbəsinin binasını sökmək ayrı hərəkətlərdir. Birinci halda xəbər başqa tərəflərə gedə bilər; ikinci halda ünvanlar, registration və əlaqələr də araşdırılmalıdır.

## Həyat ssenarisi: listener-i ayırırıq

1. Əvvəl iki lab modulu aktivdir və Insights provider-i listener qeyd edib.
2. Test yalnız dispatcher-də həmin event listener qeydiyyatını çıxarır.
3. Catalog publish edilir; öz DB row-u commit olur.
4. Event üçün consumer tapılmadığından Insights write başlamır.
5. Catalog publish uğurlu bitir.
6. Sonra real public feed-dən rebuild projection-u tamamlayır.

```text
Əvvəl: iki module registration + listener
Test addımı: yalnız yaddaşdakı listener-i ayır
Nəticə: source qalır, projection yoxdur
Recovery: feed → missing projection
```

Bu test physical folder delete, Composer autoload regeneration və deploy cache cleanup ssenarisi deyil. Onların təhlükəsiz olduğu nəticəsini bu testdən çıxarmaq olmaz.

## Real kod

Provider-də:

```php
$this->loadMigrationsFrom(module_path('LearningInsights', 'database/migrations'));

Event::listen(LearningEntryPublished::class, RecordPublishedLearningEntry::class);
```

Catalog listener class-ını çağırmır; dispatcher yalnız qeydiyyatdan keçmiş listener-ləri çağırır.

## Test nəyi sübut edir?

No-listener ssenarisi event registration-u test içində silir və publish-in Catalog row-u yaratdığını yoxlayır. Bu, qovluğun fiziki silinməsi, package manifest/cache və deploy cleanup ssenarisinin testi deyil.

```text
Listener qeydiyyatda yoxdur → Catalog commit → dispatch üçün listener yoxdur → publish uğurlu
Listener qeydiyyatdadır, exception atır → Catalog commit qalır → caller xəta görür
```

Bu iki nəticə eyni deyil. Optional consumer failure-ın udulması demək deyil.

## Dependency istiqaməti

```text
LearningInsights → Catalog public contract/event
LearningCatalog ✕ LearningInsights internal class
Production/host ✕ learning lab dependency
```

Catalog-u söndürüb Insights-i işlək saxlamanı bu praktikadan nəticə çıxarmaq olmaz. Insights-in feed və event type-ları Catalog-dan gəlir. Həmçinin modul statusunu dəyişmək DB cədvəllərini avtomatik silmək müqaviləsi deyil. Bu layihə generic runtime plugin uninstall sistemi deyil.

## Kodun vacib hissələrini açaq

- `loadMigrationsFrom(...)`: enabled provider öz migration yolunu discovery-yə əlavə edir.
- `Event::listen(...)`: hansı event-in hansı listener-ə bağlandığını bildirir.
- Catalog-un Insights import etməməsi: producer həmin consumer class-ına birbaşa bağlı deyil.
- Insights-in Catalog event/feed import etməsi: consumer yenə public müqavilədən asılıdır.

Modul aktivliyinin config-də göstərilməsi onun cədvəllərinin hər boot zamanı yaradılması demək deyil; migration icrası ayrıca əməliyyatdır.

## Niyə “modulu sil” düyməsi nəticəsini çıxarmırıq?

Runtime optional plugin sistemi üçün dependency registry, boot qərarları, data retention və cache/autoload davranışı kimi əlavə mövzular lazımdır. R1 bu platformanı implementasiya etmir. Folder sərhədi ilə hot-uninstall sistemi eyni concept deyil.

## Konkret failure nümunəsi

Junior Catalog-u söndürür, Insights-in provider/rebuild imkanını olduğu kimi saxlayır. Insights public event və feed type-larına bağlı olduğu üçün bu kombinasiya avtomatik təhlükəsiz sayılmır. “Catalog consumer-i tanımır” faktı tərs istiqamətdə dependency olmadığını demir.

Listener qeydiyyatdadır, amma onun DB write-ı exception atırsa producer caller-i xəta alır. Optional consumer “xətasını həmişə ud” qaydası deyil; no-listener ilə failed-listener ayrı testlərdir.

## Özünü yoxla

1. No-listener testi module folder delete-ni sübut edirmi? **Xeyr.**
2. Status false DB cədvəlini avtomatik silirmi? **Belə müqavilə yoxdur.**
3. Insights Catalog-a bağlıdırmı? **Bəli, yalnız üç public type vasitəsilə.**

[Sistem sərhədi](../diagrams/system/context.md), [public feed](../diagrams/labs/public-feed.md) və [listenersiz publish](../diagrams/labs/publish.md) izahlarını müqayisə et.

## Kod və yoxlama

- [Modul aktivlikləri](../../modules_statuses.json)
- [Insights provider](../../Modules/LearningInsights/app/Providers/LearningInsightsServiceProvider.php)
- [Listener-siz publish testi](../../Modules/LearningCatalog/tests/Feature/LearningCatalogPublishSpecificationTest.php)
- [Listener olmadıqda public feed ilə bərpa](../../Modules/LearningInsights/tests/Integration/R1LearningFlowTest.php)
- [Dependency guard](../../tests/Architecture/R1LearningBoundaryTest.php)
- [R1 sərhədləri](../labs/r1/README.md)
