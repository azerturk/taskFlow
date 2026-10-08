# Rebuild ilə yenidən publish etmək fərqlidir

## Sual

Catalog qeydi var, Insights qeydi yoxdursa `publish()`-i yenidən çağıraq?

## Sadə cavab

Xeyr. Publish yeni əsas qeyd yaradır. Rebuild isə mövcud əsas qeydləri oxuyub çatışmayan surətləri yaradır. Recovery zamanı biznes əməliyyatını kor-koranə təkrarlamaq yeni duplicate biznes faktı yarada bilər.

## Cari vəziyyət

R1-in rebuild-i public feed-dən bütün entry-ləri oxuyur. Sonra Insights yazılarını bir transaction daxilində edir. Mövcud projection saxlanılır; yeni projection-da `source_event_id=null` olur, çünki bu nəticə konkret event processing-dən yaranmayıb.

## Terminləri sadələşdirək

**Recovery/bərpa** failure-dan sonra düzgün vəziyyətə qayıtmaqdır. **Rebuild** burada əsas siyahıdan çatışmayan surətləri tamamlayır. **Republish** isə publish əməliyyatını yenidən çağırmaqdır. Adları oxşar səslənsə də dəyişdirdikləri məlumat fərqlidir.

Əsas qeydiyyat kitabından itmiş elan siyahısını yenidən çıxarmaq rebuild-ə bənzəyir. Eyni şəxsi qeydiyyat kitabına ikinci dəfə yazmaq isə fərqli əməliyyatdır; surəti bərpa etmək üçün əsas faktı təkrar yaratmaq lazım deyil.

## Həyat ssenarisi: caller exception aldı

1. Əvvəl Əhməd bir Catalog entry-si publish edir.
2. Catalog commit olur; listener write zamanı fail edir.
3. Əhməd exception gördüyünə görə əməliyyatın tam rollback olduğunu düşünür.
4. Əvvəl real Catalog nəticəsini yoxlamaq lazımdır: source row-u qalır.
5. Fidan listener səbəbini aradan qaldırdıqdan sonra rebuild çağırır.
6. Rebuild mövcud source entry üçün projection yaradır və source row sayı dəyişmir.

```text
Əvvəl: source yoxdur
Failure-dan sonra: source var, projection yoxdur
Recovery-dən sonra: eyni source var, projection tamamlanıb
```

## Real kod

```php
public function rebuild(): void
{
    $entries = $this->entries->all();

    DB::transaction(function () use ($entries): void {
        foreach ($entries as $entry) {
            $this->recordMissingProjection($entry);
        }
    });
}
```

Feed oxusu yazı transaction-undan əvvəldir. Feed failure olarsa Insights write başlamır. İkinci projection write fail olsa həmin rebuild-in əvvəlki yeni yazısı da rollback olur.

## Axın

```text
Catalog entry=1 commit → listener fail → Catalog=1, Insights=0
Rebuild → public feed entry=1 → missing projection yarat → Catalog=1, Insights=1
Rebuild təkrar → mövcud row toxunulmaz → saylar eyni
```

Yanlış flow:

```text
listener fail → publish(title) təkrar → Catalog-da entry=2 də yaranır
```

## Recovery-nin sərhədi

Rebuild bütün event-ləri replay etmir və mövcud projection-u yenidən hesablamır. Sistem hər request-də avtomatik rebuild etmir; bu, laboratoriya service-idir, ayrıca CLI/HTTP endpoint yoxdur. Rebuild bitdikdən sonra yaranan yeni entry üçün növbəti event və ya növbəti rebuild lazımdır.

## Kodun vacib sətirlərini açaq

- `$this->entries->all()`: source məlumatı public müqavilədən oxuyur.
- Oxunun `DB::transaction`-dan əvvəl olması: feed fail olsa write dövrünə çatılmır.
- `function () use ($entries)`: alınmış siyahı write closure-una ötürülür; yenidən hər row üçün feed query-si edilmir.
- `foreach`: hər source DTO ayrıca missing projection üçün yoxlanılır.
- `recordMissingProjection`: service-in private wrapper-idir; öz repository-sinə `sourceEventId: null` verir.
- `DB::transaction`: bu rebuild-in yeni write-ları birlikdə commit/rollback olur.

“Atomik” burada yalnız bu DB yazı qrupuna aiddir. Feed oxusu ilə bütün gələcək Catalog dəyişikliklərinin vahid snapshot-a çevrildiyi mənası yoxdur.

## Niyə əvvəl mövcud row-ları silmirik?

Cari məqsəd itmiş surətləri tamamlamaqdır. Əvvəl hamısını silsək working məlumatı riskə atar və metadata-nı dəyişərik. Missing-only seçim existing projection-u saxlayır. Buna görə bu əməliyyatı “bütün yanlış məlumatı sıfırla” kimi istifadə etmək olmaz.

## Konkret failure nümunəsi

Feed üç entry qaytarır. Birinci yeni projection yazılır, ikinci write exception atır. Düzgün nəticə birinci yeni row-un da rollback olmasıdır; yarımçıq yeni batch qalmır. Əvvəldən mövcud projection-lar saxlanılır.

Feed özü exception atsa write transaction-u başlamır. Rebuild-in sonunda uğur görmək üçün xətanı udmaq və dövrü davam etdirmək qəbul edilmiş davranış deyil.

## Özünü yoxla

1. Rebuild yeni Catalog entry yaradırmı? **Xeyr.**
2. Rebuild-created row-un event ID-si niyə null-dır? **Bu row konkret yeni event dispatch-dən yaranmayıb.**
3. İkinci write fail olsa birinci yeni write qalırmı? **Xeyr; həmin rebuild transaction-u rollback olur.**

[Rebuild diagramının izahı](../diagrams/labs/rebuild.md) success, feed failure və write failure yollarını ayrı göstərir.

## Kod və yoxlama

- [Rebuild service](../../Modules/LearningInsights/app/Services/RebuildLearningInsightsService.php)
- [Publish service](../../Modules/LearningCatalog/app/Services/LearningEntryService.php)
- [Listener failure → real feed recovery, write failure rollback testləri](../../Modules/LearningInsights/tests/Integration/R1LearningFlowTest.php)
- [Təkrar rebuild/metadata qorunması testləri](../../Modules/LearningInsights/tests/Feature/LearningInsightsProjectionSpecificationTest.php)
- [Transaction izahı](transaction-vs-compensation.md)
