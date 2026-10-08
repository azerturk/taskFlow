# Projection və əsas məlumat mənbəyi

## Sual

Title iki cədvəldə saxlanırsa hansına inanırıq?

## Sadə cavab

**Source of truth** məlumatın əsas sahibidir. **Projection** həmin məlumatın başqa oxu və ya analiz məqsədi üçün hazırlanmış surətidir. Surətin olmaması əsas faktın olmaması demək deyil.

## Cari vəziyyət

R1-də Catalog `r1_learning_entries` cədvəlinin sahibidir. Insights yalnız `r1_learning_insight_entries` cədvəlinin sahibidir. Catalog publish faktı əsasdır; Insights row-u sonradan yarana bilər. Bu laboratoriyada entry update/delete axını yoxdur.

## Terminləri sadələşdirək

Məktəbin əsas qeydiyyat kitabı ilə dəhlizdəki elan siyahısını düşün. Qeydiyyat kitabı əsas məlumatdır, elan siyahısı isə müəyyən məqsəd üçün çıxarılmış surətdir. Elan siyahısı düşübsə tələbənin qeydiyyatı ləğv olunmur.

Projection da belə hazırlanmış oxu görünüşüdür. Bu lab-da çox sadə surətdir; termin daha mürəkkəb sistemlərdə hesabat/hesablanmış görünüş üçün də istifadə oluna bilər. Burada əlavə hesabat platforması nəzərdə tutulmur.

## Həyat ssenarisi: source var, surət yoxdur

1. Əvvəl hər iki lab cədvəli boşdur.
2. Əhməd Catalog-da `Module Boundary` entry-si publish edir.
3. Catalog write commit olur; entry=7 əsas fakt kimi qalır.
4. Listener həmin vaxt yoxdur, ona görə Insights row-u yaranmır.
5. Fidan public feed vasitəsilə entry=7-ni oxuyur.
6. Rebuild entry=7 üçün çatışmayan projection-u yaradır; yeni Catalog entry-si yaranmır.

```text
Əvvəl: Catalog=0, Insights=0
Publish və listenersiz vəziyyət: Catalog=1, Insights=0
Rebuild-dən sonra: Catalog=1, Insights=1
```

## Real kod

Catalog public feed internal modeli readonly DTO-ya çevirir:

```php
static fn (LearningEntry $entry): PublishedLearningEntryData => new PublishedLearningEntryData(
    id: $entry->id,
    title: $entry->title,
    publishedAt: $entry->published_at->toDateTimeImmutable(),
)
```

Insights model və cədvələ birbaşa getmir. Contract vasitəsilə alınmış DTO-dan öz projection-unu yaradır.

## Axın

```text
Catalog entry (əsas fakt)
  ├─ event → Insights projection
  └─ public feed → rebuild → çatışmayan Insights projection
```

Listener olmadığı anda Catalog row-u mövcud, projection isə yox ola bilər. Bu, iki cədvəlin vahid transaction-da həmişə eyni anda görünməsi zəmanətinin olmadığını göstərir. Sinxron event də commit ilə listener arasındakı boşluğu aradan qaldırmır.

## Rebuild nəyi etmir?

Cari rebuild yalnız çatışmayan row-ları yaradır. Mövcud projection-u silmir, title/date-ni yeniləmir, source event ID-ni dəyişmir. Ona görə əl ilə korlanmış mövcud row-u “rebuild hər şeyi düzəldəcək” deyərək gözləmək olmaz.

Bu məhdud model publish-only laboratoriya üçün seçilib. Əgər gələcəkdə update/delete əlavə edilərsə yeni event və projection qaydaları ayrıca dizayn edilməlidir.

## Kod parçasını sətir-sətir açaq

- `LearningEntry $entry`: Catalog-un daxildə oxuduğu persistence obyektidir.
- `new PublishedLearningEntryData(...)`: həmin modeldən consumer üçün məhdud məlumat hazırlanır.
- `id: $entry->id`: surət hansı əsas fakta aiddir.
- `title: $entry->title`: oxu üçün lazım olan mətn surəti.
- `toDateTimeImmutable()`: consumer-ə dəyişdirilməyən tarix obyekti verilir.

Consumer bu DTO ilə Catalog row-unu `save()` edə bilməz. DTO persistence modeli deyil. Bu ayrılıq məlumat sahibliyini qoruyur.

## Niyə iki cədvəl var?

Bu laboratoriya modul əlaqələrini göstərmək üçün surəti ayrıca saxlayır. Hər real sistemdə eyni məlumatı iki dəfə yazmaq məcburiyyəti yoxdur. Burada iki cədvəl “microservice hazırdır” və ya “istənilən datanı duplicate saxla” tövsiyəsi deyil.

Ayrı ownership bərpa qərarını da aydın edir: Insights çatışmırsa source Catalog-dan oxunur, Catalog isə Insights-dən geri doldurulmur.

## Konkret yanlış gözlənti

Junior Insights row-unun title-ını testdə əl ilə dəyişir, sonra rebuild çağırır və title-ın düzələcəyini gözləyir. Cari rebuild existing row-u yeniləmir; nəticə dəyişməz qala bilər. Bu, tam reconciliation — iki siyahını tam eyniləşdirmək — əməliyyatı deyil.

Source row feed-dən oxunduqdan sonra yeni entry yaranarsa əvvəlki snapshot-da yoxdur. Onun projection-u event və ya növbəti rebuild ilə yarana bilər. Oxu və yazı vahid cross-module snapshot transaction-u deyil.

## Özünü yoxla

1. Insights row-u yoxdursa Catalog fact-i yoxdurmu? **Xeyr; source ayrıca mövcud ola bilər.**
2. DTO modeli dəyişmək qapısıdırmı? **Xeyr; readonly məlumat daşıyır.**
3. Rebuild korlanmış mövcud title-ı mütləq düzəldirmi? **Cari missing-only davranışda xeyr.**

[Lab məlumat modelinin izahı](../diagrams/labs/lab-data.md) ownership ilə logical identity əlaqəsini göstərir.

## Kod və yoxlama

- [Public feed](../../Modules/LearningCatalog/app/Services/EloquentPublishedLearningEntryFeed.php)
- [Readonly oxu DTO-su](../../Modules/LearningCatalog/app/Data/PublishedLearningEntryData.php)
- [Missing-only rebuild](../../Modules/LearningInsights/app/Services/RebuildLearningInsightsService.php)
- [Mövcud row-un dəyişmədiyini yoxlayan testlər](../../Modules/LearningInsights/tests/Feature/LearningInsightsProjectionSpecificationTest.php)
- [R1 məlumat modeli](../labs/r1/README.md)
