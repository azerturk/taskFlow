# After-commit: əvvəl məlumat, sonra xəbər

## Sual

`ShouldDispatchAfterCommit` bizi hansı problemdən qoruyur?

## Sadə cavab

Transaction daxilində yazılmış məlumat hələ qalıcı olmaya bilər. Listener həmin anda “yeni qeyd yarandı” xəbəri alsa, sonrakı rollback-dən sonra olmayan qeyd üçün nəticə yarada bilər. After-commit xəbəri faktiki commit-dən sonraya saxlayır.

## Cari vəziyyət

R1 event-i Laravel-in native marker interface-ini implementasiya edir. Adi publish-də daxili transaction əvvəl bitir. Publish-i çağıran tərəf artıq outer transaction açıbsa, event outer commit-ə qədər gözləyir.

## Terminləri sadələşdirək

**Transaction** bir neçə DB yazısını birlikdə tamamlamaq üçün sərhəddir. **Commit** həmin yazıların qalıcı təsdiqidir; **rollback** həmin transaction-un yazılarından imtinadır. **Outer transaction** isə service-i çağıran kodun daha əvvəl açdığı xarici sərhəddir.

Bank nümunəsini düşün: “köçürmə tamamlandı” mesajını hesab yazıları təsdiqlənməmiş göndərmək olmaz. Əks halda son addım fail olsa pul köçməyib, amma xəbər gedib. After-commit xəbəri əvvəl qalıcı faktın olmasını gözləyir.

## Həyat ssenarisi: Əhmədin işi hələ bitməyib

İzah üçün qurulmuş ssenaridə Əhməd testdə əvvəl outer transaction açır, sonra Catalog publish çağırır, ardınca başqa addım edir. Bu hekayə real junior report-u deyil; təsvir edilən commit/rollback davranışı source-da ayrıca integration testləri ilə yoxlanılır.

1. Əvvəl transaction səviyyəsi sıfırdır; Catalog-da yeni row yoxdur.
2. Caller outer transaction açır.
3. Publish öz daxili transaction closure-unda entry yaradır.
4. Daxili çağırış qayıtsa da caller-in xarici transaction-u hələ açıqdır.
5. Event dispatch cəhdi callback kimi gözləyir; Insights projection-u hələ yoxdur.
6. Caller commit edərsə listener işləyir; rollback edərsə row və gözləyən callback ləğv olunur.

```text
Əvvəl: entry yoxdur
Aralıq: entry cari transaction-da görünür, projection yoxdur
Commit yolu: entry qalıcıdır → listener → projection
Rollback yolu: entry yoxdur → listener işləmir → projection yoxdur
```

## Real kod

```php
final readonly class LearningEntryPublished implements ShouldDispatchAfterCommit
```

Service-də ardıcıllıq:

```php
$entry = DB::transaction(fn (): LearningEntry => $this->entries->createPublished($data));

event(new LearningEntryPublished(
    eventId: (string) Str::uuid(),
    entryId: $entry->id,
    title: $entry->title,
    publishedAt: $entry->published_at->toDateTimeImmutable(),
));
```

## İki axın

```text
Outer yoxdur: insert → daxili commit → dispatch → listener
Outer var: outer begin → publish/insert → dispatch təxirə düşür → outer commit → listener
Outer rollback: insert geri alınır → gözləyən event atılır → listener işləmir
```

Daxili `DB::transaction()`-ın qayıtması outer transaction-un commit olması demək deyil. Buna görə yalnız “event-i daxili closure-dan sonra yazdım” yanaşması outer transaction problemini təkbaşına həll etmir.

## Nəyi qorumur?

After-commit durable event storage, queue və retry yaratmır. Commit-dən sonra process dayansa event itə bilər. Listener exception atsa Catalog qeydi qalır və xəta caller-ə yayılır. Catalog-u geri qaytarmaq üçün artıq gecdir; projection public feed-dən rebuild edilir.

## Kodun vacib hissələrini açaq

- `DB::transaction(...)`: insert-i DB transaction sərhədində edir.
- `$entry = ...`: insert uğurlu olarsa service entry obyektini alır; bu, hər zaman outer commit demək deyil.
- `implements ShouldDispatchAfterCommit`: dispatcher-ə açıq transaction olduqda commit-i gözləmək qaydasını bildirir.
- `event(...)`: həmin qaydaya tabe olan dispatch cəhdidir.
- `publishedAt`: məlumat faktının tarixidir; listener-in sonradan başladığı vaxtla əvəz edilmir.

Kodda event-i sadəcə closure-dan sonraya keçirmək outer transaction-u özü-özünə həll etməz. Marker-in vacibliyi məhz caller-in xarici sərhədində görünür.

## Niyə bu variant seçilib?

Listener əsas məlumatın həqiqətən təsdiqləndiyini görməlidir. Catalog rollback olub Insights row-u qalsa surət olmayan fakta istinad edər. After-commit bu konkret yanlış nəticəni qoruyur; bütün delivery problemlərini həll etmir.

## Konkret xəta nümunəsi

Outer transaction içində publish-dən sonra `RuntimeException` atılır. Düzgün nəticə Catalog=0, Insights=0 və listener çağırışı=0-dır. Test həm bu nəticəni, həm sonrakı ayrı commit-də əvvəlki event-in sızmamasını yoxlayır.

Başqa failure-də Catalog commit edir, listener fail olur. Burada nəticə fərqlidir: Catalog row-u qalır. “After-commit var, exception olarsa hamısı rollback olar” düşüncəsi yanlışdır; commit artıq baş verib.

## Özünü yoxla

1. Daxili transaction-un qayıtması həmişə real commit deməkdirmi? **Xeyr; outer transaction açıq qala bilər.**
2. Outer rollback-də R1 listener işləyirmi? **Xeyr.**
3. After-commit event-in diskdə durable saxlanmasını təmin edirmi? **Xeyr.**

[Publish diagramının izahını](../diagrams/labs/publish.md) real commit integration testi ilə yanaşı oxu.

## Kod və yoxlama

- [Event](../../Modules/LearningCatalog/app/Events/LearningEntryPublished.php)
- [Publish service](../../Modules/LearningCatalog/app/Services/LearningEntryService.php)
- [Real outer commit/rollback testləri](../../Modules/LearningInsights/tests/Integration/R1LearningFlowTest.php)
- [Test trait fərqi](refresh-database-vs-database-migrations.md)
- [Outbox ilə fərq](outbox-vs-inbox.md)
