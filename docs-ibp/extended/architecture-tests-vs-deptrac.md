# Architecture testləri ilə Deptrac arasındakı fərq

## Sual

Bizim architecture testlərimiz varsa Deptrac nə üçündür?

## Sadə cavab

Bizim testlər TaskFlow üçün yazılmış konkret qaydaları yoxlayır. Deptrac isə konfiqurasiya olunan qatlar və dependency qaydaları üzrə PHP kodunu statik analiz edən ayrıca alətdir. Biri digərinin bütün işlərini avtomatik əvəz etmir. [Rəsmi Deptrac sənədi](https://deptrac.github.io/deptrac/)

## Cari vəziyyət

TaskFlow-da Deptrac quraşdırılmayıb. `composer test:architecture` mövcud Pest testlərini işlədir. İzah üçün yeni dependency əlavə etmək lazım deyil.

| Yoxlama | TaskFlow yanaşması | Deptrac ilə əsas fokus |
|---|---|---|
| Modul/qat asılılığı | Layihəyə məxsus allowlist və source guard | Konfiqurasiya olunmuş qatlar/dependency graph |
| Lab-da route/UI qadağası | Fayl yolları və məqsədli pattern-lər | Ayrıca uyğun qayda/əlavə yoxlama lazım ola bilər |
| Cədvəl adı və xüsusi SQL qadağası | Məqsədli guard | Class dependency qaydası bunu təkbaşına sübut etmir |
| İşin biznes nəticəsi | Feature/integration testləri | Statik dependency analizi bunu yoxlamır |

## Terminləri sadələşdirək

**Dependency/asılılıq** bir kod hissəsinin başqa class və ya imkandan istifadə etməsidir. **Static analysis/statik analiz** proqramı biznes əməliyyatı kimi işlətmədən source koduna baxmaqdır. **Allowlist** isə yalnız əvvəlcədən icazə verilmiş əlaqələrin siyahısıdır.

Architecture qaydası binanın planındakı divara bənzəyir: “bu otaqdan ora keçid var, buradan yox”. Funksional test isə otağın içində işığın işləməsini yoxlayır. Divarın düzgün olması işığın işlədiyini sübut etmir.

## Həyat ssenarisi: qadağan import review-a düşür

Fidan Insights daxilində Catalog modelini import edir, çünki title-a tez çatmaq istəyir.

1. Əvvəl Insights yalnız üç public type istifadə edir.
2. Yeni import consumer-i Catalog-un internal modelinə bağlayır.
3. Architecture test source-u oxuyub public allowlist ilə müqayisə edir.
4. `LearningEntry` allowlist-də olmadığı üçün test fail olur.
5. Fidan import-u alias etsə də R1-in məlum alias formaları negative fixture-lərlə yoxlanılır.
6. Düzəlişdən sonra consumer public feed/DTO istifadə edir və sərhəd qorunur.

```text
Əvvəl: icazəli əlaqə → dəyişiklik: internal import → guard: pozuntu → nəticə: implementasiya yenidən qurulur
```

## Real kod

R1 guard-ın public contract allowlist-i:

```php
public const PUBLIC_CATALOG_CLASSES = [
    'Modules\\LearningCatalog\\Contracts\\PublishedLearningEntryFeed',
    'Modules\\LearningCatalog\\Data\\PublishedLearningEntryData',
    'Modules\\LearningCatalog\\Events\\LearningEntryPublished',
];
```

`LearningBoundaryGuard` PHP token-lərini oxuyur, import/alias/FQCN və müəyyən source pattern-lərini yoxlayır. `SourceGuard` production qatları üçün məqsədli source yoxlamaları verir. Bunlar ümumi PHP semantik analiz mühərriki deyil.

## Axın

```text
Bizdə: source faylları → token/pattern qaydaları → Pest assertion → PASS/FAIL
Deptrac: source faylları → konfiqurasiya olunmuş qat/dependency analizi → pozuntu hesabatı
```

## Məhdudiyyət

Guard keçirsə hər mümkün dinamik çağırışın təhlükəsizliyi sübut olunmur. String ilə class qurmaq və runtime resolution kimi formalar source analizi üçün çətindir. Bizdə məlum bypass formaları negative fixture-lərlə yoxlanılır; dəyişən sintaksisə uyğun testlər də yenilənməlidir.

Deptrac seçilsə belə authorization, transaction, output, migration və real listener testləri qalmalıdır. Statik icazəli dependency düzgün biznes davranışı demək deyil.

## Kodun vacib hissələrini açaq

- `PUBLIC_CATALOG_CLASSES`: “Catalog-dan istənilən class” deyil, tam class adlarının exact siyahısıdır.
- `Contracts\PublishedLearningEntryFeed`: consumer-in oxu qapısıdır.
- `Data\PublishedLearningEntryData`: həmin qapıdan çıxan məlumat formasıdır.
- `Events\LearningEntryPublished`: producer-in bildirdiyi fakt formasıdır.
- Token/pattern yoxlaması: guard source-un müəyyən sintaksis formalarını tanıyır; bütün mümkün runtime davranışını icra etmir.

**Fixture** burada guard-a verilən kiçik nümunə source mətnidir. Positive fixture icazəli formanın keçdiyini, negative fixture isə qadağan formanın tutulduğunu göstərir. Təkcə real kodda pozuntu olmaması guard-ın işlədiyini sübut etməz; guard-ın “pis nümunəni” tutması da yoxlanılır.

## Deptrac niyə ayrıca düşünülə bilər?

Deptrac qatları və icazəli dependency istiqamətlərini konfiqurasiya etməyə imkan verir. Layihə böyüdükdə dependency graph-ı ayrıca alətlə idarə etmək faydalı ola bilər. Bu fikir alətin indi quraşdırıldığı və ya mövcud xüsusi testləri silməli olduğumuz mənasına gəlmir.

Bizim guard “R1-də route/UI yaratma”, “bu cədvələ toxunma” kimi xüsusi qaydaları da yoxlayır. Ümumi dependency alətinə keçid olsa bu acceptance qaydalarının harada qorunacağı ayrıca göstərilməlidir.

## Konkret yanlış sübut nümunəsi

Controller repository import etmir və architecture test keçir. Amma controller yanlış actor ilə service çağırırsa real access bug ola bilər. Bu xətanı permission/policy feature testi tutmalıdır. Static PASS-dan “sistem təhlükəsizdir” nəticəsi çıxarmaq olmaz.

Həmçinin tamamilə dinamik class adı qurmaq source guard-dan yayınma riski yarada bilər. Bu, qəsdən bypass yazmağa icazə deyil; guard-ın əhatəsini dürüst başa düşməkdir.

## Özünü yoxla

1. Architecture testi workflow nəticəsini sübut edirmi? **Xeyr; struktur qaydasını yoxlayır.**
2. Deptrac hazırda dependency-lərimizdə varmı? **Xeyr.**
3. Negative fixture niyə lazımdır? **Guard-ın qadağan source formasını həqiqətən tutduğunu yoxlamaq üçün.**

Əlaqəli xəritələr: [dependency istiqamətləri](../diagrams/system/dependencies.md), [test qatları və izolyasiya](../diagrams/flows/test-isolation.md).

## Kod və yoxlama

- [Production guard](../../tests/Architecture/Support/SourceGuard.php)
- [R1 guard](../../tests/Architecture/Support/LearningBoundaryGuard.php)
- [Production qaydalar](../../tests/Architecture/ControllerBoundaryGuardTest.php)
- [R1 positive/negative fixture-ləri](../../tests/Architecture/R1LearningBoundaryTest.php)
- [Test strategiyası](../technical/TESTING.md)
