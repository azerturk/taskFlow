# RefreshDatabase və DatabaseMigrations

## Sual

Niyə R1 integration testləri qalan feature testlərindən fərqli DB trait-i istifadə edir?

## Sadə cavab

Əksər feature testlərə hər testdən sonra təmiz DB vəziyyəti kifayətdir. Real commit/rollback vaxtını sübut edən test isə görünməyən test transaction-u arxasında işləməməlidir. Eyni isolation vasitəsi hər ssenari üçün düzgün deyil.

## Cari vəziyyət

TaskFlow feature qovluqları `RefreshDatabase` istifadə edir. R1 integration qovluğu ayrıca `DatabaseMigrations` istifadə edir. SQLite default in-memory, MySQL isə fail-closed dedicated test DB profilidir.

## Terminləri sadələşdirək

**Isolation/izolyasiya** bir testin məlumatının başqa testə təsir etməməsidir. **Trait** PHP-də class-a ortaq davranış əlavə etmək vasitəsidir. **Wrapping transaction** test framework-ünün bütün testi əhatə edən xarici transaction-udur. **PDO** PHP-nin real DB connection qatıdır.

Məşq üçün çəkilən lövhəni hər şagirddən sonra silmək isolation-a bənzəyir. Amma məqsəd boyanın həqiqətən qurumasını yoxlamaqdırsa üstündə qoruyucu örtük olan lövhə yanlış müşahidə verə bilər. Real commit testi də test harness-in transaction təsirini ayırmalıdır.

## Həyat ssenarisi: listener hansı anda işləyir?

1. Əvvəl junior feature testində publish çağırıb projection yarandığını görür.
2. Test `RefreshDatabase` istifadə etdiyinə görə framework xarici transaction aça bilər.
3. Laravel testing transaction manager-i həmin wrapping transaction üçün callback davranışını uyğunlaşdırır.
4. Bu nəticə biznes callback-in işlədiyini göstərə bilər, amma real PDO commit timing-i üçün tək sübut deyil.
5. R1 integration qovluğu `DatabaseMigrations` ilə test edir, ilkin transaction səviyyəsini sıfır yoxlayır.
6. Listener callback-də səviyyə sıfır və PDO `inTransaction=false` assert edilir.
7. Sonra real outer begin/commit/rollback ayrıca ssenarilərdə sübut olunur.

```text
Adi feature sualı: əməliyyatın nəticəsi düzgündür?
Real commit sualı: nəticə hansı transaction anında yarandı?
Fərqli sual → uyğun test isolation vasitəsi
```

## Real konfiqurasiya

`tests/Pest.php`-də R1 integration üçün:

```php
pest()->extend(TestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('../Modules/LearningInsights/tests/Integration');
```

Real event callback testində:

```php
expect(DB::transactionLevel())->toBe(0)
    ->and(DB::connection()->getPdo()->inTransaction())->toBeFalse();
```

Beləliklə yalnız row count deyil, listener zamanı real DB transaction-un bitməsi də yoxlanılır.

## Fərq

| Vasitə | Bu kodbazada məqsəd |
|---|---|
| `RefreshDatabase` | Migration setup-dan sonra test-i wrapping transaction ilə izolə etmək; geniş feature suite |
| `DatabaseMigrations` | Hər test üçün migration setup/cleanup, test-i wrapping transaction-a salmadan real commit yoxlaması |

Laravel-in test transaction manager-i wrapping transaction-u after-commit callback-ləri üçün xüsusi nəzərə alır. Buna görə RefreshDatabase altında callback-in işləməsi təkbaşına “PDO commit bitdi” sübutu deyil.

## Axın

```text
Feature: test setup → wrapping transaction → action/assertions → rollback isolation
R1 integration: migration setup → real begin/commit/rollback → PDO/event assertions → migration cleanup
```

## Təhlükəsizlik

Bu trait-lər migration işlədir. Onları real application DB-yə qarşı çalışdırmaq olmaz. MySQL bootstrap `taskflow_test` və ya `taskflow_test_...` adına uyğun olmayan database üçün dayanır. Testləri generic `artisan migrate:fresh` əmri ilə əvəz etmə; canonical test profillərindən istifadə et.

## Kodun vacib sətirlərini açaq

- `extend(TestCase::class)`: Laravel tətbiqini boot edən test bazası seçilir.
- `use(DatabaseMigrations::class)`: bu qovluq üçün migration əsaslı setup/cleanup seçilir.
- `in('...Integration')`: qayda yalnız həmin test yoluna tətbiq edilir.
- `DB::transactionLevel()`: Laravel connection-un açıq transaction sayını göstərir.
- `getPdo()->inTransaction()`: real PDO transaction vəziyyətini yoxlayır.
- `toBeFalse()`: listener anında DB transaction-un açıq olmadığını gözləyir.

Bir trait-i dəyişmək təkcə test performansı seçimi deyil; müşahidə etdiyimiz transaction sərhədini də dəyişə bilər.

## Niyə bütün testləri DatabaseMigrations etmirik?

Hər testdə migration setup/cleanup daha çox işdir. Geniş feature suite-in əksər ssenarilərində wrapping transaction isolation kifayətdir. Real commit lazımdırsa ayrıca qovluq/trait ilə dəqiq sübut qurulur; ağır yolu bütün suite-ə yaymaq məqsəd deyil.

## Konkret təhlükəli nümunə

Junior yanlış DB connection ilə migration trait-li test işlədirsə migration real data-ya toxuna bilər. Buna görə canonical SQLite və dedicated MySQL bootstrap seçilir; generic manual `migrate:fresh` əmri ilə yoxlama edilməz.

MySQL-də adı uyğun dedicated DB seçilməsi ilə yanaşı inherited `DB_URL` da nəzərə alınmalıdır: cari bootstrap onu özü boşaltmır. [Mühit sənədinin](../technical/ENVIRONMENT.md) təhlükəsiz komandasına əməl et; `.env.testing` dəyərlərini output etmə.

## Özünü yoxla

1. RefreshDatabase pis test vasitəsidirmi? **Xeyr; adi feature isolation üçün uyğundur.**
2. Event fake real commit-i sübut edirmi? **Xeyr.**
3. DatabaseMigrations production DB-də işlədilməlidirmi? **Xeyr; yalnız təhlükəsiz test profilində.**

[Test izolyasiya diagramının izahı](../diagrams/flows/test-isolation.md) resurs və trait seçimini birlikdə göstərir.

## Kod və yoxlama

- [Pest trait bölgüsü](../../tests/Pest.php)
- [Real transaction testləri](../../Modules/LearningInsights/tests/Integration/R1LearningFlowTest.php)
- [SQLite bootstrap](../../tests/bootstrap/sqlite.php)
- [MySQL fail-closed bootstrap](../../tests/bootstrap/mysql.php)
- [Test strategiyası](../technical/TESTING.md)
- [Mühit qaydaları](../technical/ENVIRONMENT.md)
