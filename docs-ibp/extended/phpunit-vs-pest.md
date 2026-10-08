# PHPUnit və Pest

## Sual

Pest istifadə ediriksə niyə fayllar `phpunit.xml` adlanır?

## Sadə cavab

Pest PHPUnit üzərində qurulub. Pest rahat `test()`/`expect()` yazılışı verir, PHPUnit infrastrukturu isə test icrasında və konfiqurasiyada iştirak edir. Buna görə iki əlaqəsiz test sistemi düşünmək düzgün deyil. [Pest-in rəsmi izahı](https://pestphp.com/docs/migrating-from-phpunit-guide)

## Cari vəziyyət

TaskFlow-da testlər əsasən Pest yazılışındadır. Composer script-i Pest runner-ini `phpunit.xml` konfiqurasiyası ilə çağırır. MySQL eyni suite üçün ayrıca profil istifadə edir.

```text
composer test
  → php vendor/bin/pest --configuration phpunit.xml --compact

composer test:mysql
  → php vendor/bin/pest --configuration phpunit-mysql.xml --compact
```

## Terminləri sadələşdirək

**Test runner** testləri tapıb icra edən proqramdır. **Assertion** gözlədiyimiz nəticənin yoxlanmasıdır. **Dataset** eyni test məntiqini bir neçə input ilə təkrarlamaqdır. **Fixture/setup** isə testin başlaması üçün hazırlanan vəziyyətdir.

Müəllim eyni qaydanı bir neçə tapşırıqda yoxlayırsa bu dataset-ə bənzəyir. Hər tapşırığın cavabını “düzgün gözlənilən nəticə budur” ilə müqayisə etməsi assertion-dur. Testin gözəl syntax-ı deyil, yanlış cavabı tutması əsasdır.

## Həyat ssenarisi: title limitini yoxlayırıq

1. Əvvəl Catalog title-ı 1–255 simvol qəbul etməlidir.
2. Əhməd bir simvolluq title ilə test yazır və yaşıl nəticə alır.
3. Bu tək nümunə 255 sərhədinin işlədiyini sübut etmir.
4. Dataset ikinci input kimi 255 Unicode simvol verir.
5. Hər input üçün DTO normalize edilir, service publish edir, title və DB nəticəsi yoxlanılır.
6. Ayrı negative dataset boş və çox uzun input-ların rəddini yoxlayır.

```text
Qayda → müxtəlif input-lar → eyni test body-si → hər biri üçün assertion → ayrı PASS/FAIL
```

## Real kod

R1 testində Pest dataset yazılışı:

```php
test('Catalog accepts trimmed one and 255 character Unicode titles', function (string $title): void {
    Event::fake([LearningEntryPublished::class]);
    $data = new PublishLearningEntryData(" \t{$title}\r\n ");
    $entry = app(LearningEntryService::class)->publish($data);

    expect($data->title)->toBe($title)
        ->and($entry->title)->toBe($title)
        ->and((new ReflectionClass($data))->isReadOnly())->toBeTrue();
})->with([
    'one character' => 'Ə',
    '255 characters' => str_repeat('Ə', 255),
]);
```

Bu parça testin əsas məntiqini göstərir; tam testdə persistence assertion-u da var. Laravel test imkanları `Tests\TestCase`, Pest-Laravel plugin-i və `tests/Pest.php` ilə qoşulur.

## Nəyi harada axtarım?

| Ehtiyac | Fayl |
|---|---|
| Hansı test qovluqları icra edilir? | `phpunit.xml`, `phpunit-mysql.xml` |
| Hansı DB trait-i hansı qovluqdadır? | `tests/Pest.php` |
| Komandanın standart əmri nədir? | `composer.json` scripts |
| Ssenari və assertion | Müvafiq `*Test.php` |

## Yanlış yanaşma

“Adında PHPUnit var, Pest-dən silək” etmə. XML suite discovery və mühit konfiqurasiyasıdır. Həmçinin yalnız `test()` syntax-ı bilmək yetmir: fixture, transaction və assertion-un real davranış sübut edib-etmədiyini anlamaq lazımdır.

## Kodun vacib sətirləri nə deyir?

- `test('...', function (...) { ... })`: insanın oxuya biləcəyi ssenari adı və testin addımlarıdır.
- `Event::fake(...)`: bu feature ssenarisində real listener işlətmədən dispatch yoxlaması etməyə imkan verir.
- `new PublishLearningEntryData(...)`: real title normalization qaydasını işlədir.
- `app(LearningEntryService::class)`: Laravel container-dən service alır; əl ilə bütün dependency-ləri yığmır.
- `expect(...)->toBe(...)`: real nəticəni gözlənilən dəyərlə müqayisə edir.
- `->with(...)`: eyni test body-sini dataset dəyərləri üçün işlədir.

Event fake olunması bu testin pis olması deyil. Sadəcə sübutu “dispatch oldu” sərhədindədir; real listener/commit davranışı ayrıca integration testində yoxlanılır.

## Niyə XML və Pest birlikdədir?

XML hansı qovluqları tapacağını və hansı bootstrap/profilin işləyəcəyini göstərir. Pest faylları həmin profildəki ssenariləri rahat yazmağa kömək edir. Syntax ilə icra mühitini ayır: gözəl `expect()` ifadəsi yanlış DB profilini təhlükəsiz etməz.

## Konkret xəta nümunəsi

Junior yeni modul testini yazır, amma qovluğu `phpunit.xml` və MySQL profilinin discovery-sinə əlavə etmir. `composer test` yaşıl görünə bilər, çünki həmin test ümumiyyətlə tapılmayıb. Buna görə suite discovery architecture testi ilə də yoxlanılır.

Digər problem: test title-ın saxlandığını assert edir, amma invalid input zamanı row sayını yoxlamır. DTO exception-u olsa belə yan təsirin başlamadığını ayrıca sübut etmək lazımdır.

## Özünü yoxla

1. Pest ilə PHPUnit bir-birindən tam müstəqildirmi? **Xeyr; Pest PHPUnit üzərində qurulub.**
2. Dataset nəyə qənaət edir? **Eyni test məntiqini fərqli input-larla təkrar yazmağa.**
3. Yaşıl komanda yeni testin icra olunduğunu həmişə sübut edirmi? **Xeyr; discovery və test inventarı da yoxlanmalıdır.**

[Test izolyasiya diagramının izahı](../diagrams/flows/test-isolation.md) profil və test qatı fərqini ayrıca göstərir.

## Kod və yoxlama

- [Tam Unicode test nümunəsi](../../Modules/LearningCatalog/tests/Feature/LearningCatalogPublishSpecificationTest.php)
- [Pest qovluq qaydaları](../../tests/Pest.php)
- [SQLite profili](../../phpunit.xml), [MySQL profili](../../phpunit-mysql.xml)
- [Canonical komandalar](../technical/TESTING.md)
- Davamı: [test növləri](test-types.md).
