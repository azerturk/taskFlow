# Idempotency exactly-once demək deyil

## Sual

Eyni event iki dəfə gələndə bir projection yaranırsa event bir dəfə çatdırılıb?

## Sadə cavab

Xeyr. **Idempotency** eyni identity üçün əməliyyatı təkrarladıqda əlavə nəticə yaranmaması deməkdir. **Exactly-once** iddiası isə hansı delivery/processing sərhədində “bir dəfə” zəmanəti verildiyini dəqiqləşdirməyi tələb edir. Bizim unique row nəticəmiz end-to-end delivery zəmanəti deyil.

## Cari vəziyyət

R1-də projection hər Catalog `entry_id` üçün ən çox bir row saxlayır. Eyni entry üçün ikinci event gəlsə ilk projection-un title, tarix və event ID-si dəyişmir. Event-in mütləq gəlməsi və avtomatik retry təmin edilmir.

## Termini gündəlik dillə düşünək

İşığı söndürmə düyməsini iki dəfə basmaq eyni “sönülü” nəticəni verə bilər. Amma “işığı əks vəziyyətə keçir” düyməsini iki dəfə basmaq əvvəlki vəziyyətə qaytarar. Birinci davranış idempotency-ni anlamağa kömək edir: təkrar eyni əməliyyat əlavə nəticə yaratmır.

DB-də **identity** “bu hansı şeydir?” sualının açarıdır. R1 projection üçün cavab `entry_id`-dir. Event-in UUID-si isə konkret xəbəri tanıdır; iki identity eyni məqsəd daşımır.

## Həyat ssenarisi: Fidan eyni xəbəri iki dəfə işləyir

1. Əvvəl entry=7 üçün Insights row-u yoxdur.
2. Listener `eventId=E1`, `entryId=7` alır.
3. Repository entry=7 tapmadığı üçün ilk row-u yaradır.
4. Eyni payload yenidən işlənir.
5. Repository entry=7-ni tapır və yeni row yaratmır.
6. Sonra row sayı bir qalır; ilkin title, tarix və event ID dəyişmir.

```text
Əvvəl: projection yoxdur
Birinci processing: row yarat
İkinci processing: row-u tap, toxunma
Sonra: bir row, ilkin metadata
```

Bu ssenari eyni xəbərin iki dəfə processing cəhdindən sonra əlavə projection yaranmadığını göstərir. Xəbərin həqiqətən bir dəfə çatdığını göstərmir.

## Real kod

```php
LearningEntryInsight::query()->firstOrCreate(
    ['entry_id' => $entryId],
    [
        'source_event_id' => $sourceEventId,
        'title' => $title,
        'published_at' => $publishedAt,
    ],
);
```

Birinci array axtarış identity-sidir. İkinci array yalnız yeni row yaradılarkən istifadə edilir. Bu `updateOrCreate()` deyil.

## Nümunə

```text
event A, entry=7 → entry_id=7 olan projection yaranır
event A, entry=7 → həmin entry_id=7 projection-u qalır
event B, entry=7 → yenə həmin projection qalır, ilkin metadata dəyişmir
event A, entry=8 → source_event_id unique pozulur, exception gizlədilmir
```

Rebuild `entry=7, source_event_id=null` row-u əvvəl yaradıbsa, sonrakı event də onu yeniləmir. Null ID “event inbox-da qeyd olunub” mənası daşımır.

Projection-un öz primary key `id`-si ayrıca dəyərdir; source `entry_id=7` olması projection-un öz `id=7` olması demək deyil.

## İki vacib sərhəd

- DB constraint paralel duplicate yazını qadağan edir; code-level yoxlama ilə kifayətlənmirik.
- `publish()`-i təkrarlamaq idempotent deyil: ikinci dəfə yeni Catalog entry yaradır. Projection duplicate qorunmasını publish retry mexanizmi saymaq olmaz.

## `firstOrCreate` sətirlərini açaq

- `['entry_id' => $entryId]`: əvvəl bu entry identity-si ilə row axtarılır.
- İkinci array: row yoxdursa yeni yazının dəyərləridir.
- `source_event_id`: ilk event yolu ilə yaradılarsa mənşə identity-si saxlanır.
- `title` və `published_at`: yeni row-un ilkin surət məlumatıdır.
- Metod adı `firstOrCreate`: “tap və ya yarat”dır; “tap və yenilə” deyil.

DB unique constraint də lazımdır. Təkcə “əvvəl select etdim, yox idi” demək paralel iki write üçün təhlükəsiz deyil: iki proses eyni anda yox nəticəsi görə bilər. Constraint duplicate row-u DB səviyyəsində qadağan edir.

## Niyə `updateOrCreate` deyil?

Bu lab ilk projection metadata-sını saxlayır. Təkrar və ya ziddiyyətli payload ilkin title-ı səssiz dəyişməməlidir. Update/delete projection müqaviləsi ayrıca qurulmayıb. Metodu dəyişmək sadə kosmetika deyil, qəbul edilmiş davranışı dəyişər.

## Konkret xəta nümunəsi

E1 əvvəl entry=7-də saxlanılıb. E1 ilə yeni entry=8 yaratmaq cəhdi non-null event ID unique constraint-inə ilişir; bu, normal duplicate kimi udulmur. Əksinə, entry=7 üçün E2 gəlsə row artıq var və ilk metadata qalır; E2 ayrıca inbox ledger-inə yazılmır.

Həmçinin publish-i iki dəfə çağırmaq iki fərqli entry yaradır. “Listener idempotentdir” faktını “publish də təhlükəsiz retry edilə bilər” nəticəsinə çevirmə.

## Özünü yoxla

1. Projection-un identity-si event ID-dirmi? **Xeyr; entry ID-dir.**
2. Təkrar event title-ı yeniləyirmi? **Cari kodda xeyr.**
3. Bir row qalması exactly-once delivery-ni sübut edirmi? **Xeyr.**

[Duplicate diagramının izahında](../diagrams/labs/duplicate.md) eyni entry və ziddiyyətli event ID yollarını ayrıca izlə.

## Kod və yoxlama

- [Repository](../../Modules/LearningInsights/app/Repositories/Eloquent/EloquentLearningInsightRepository.php)
- [Unique constraint-lər](../../Modules/LearningInsights/database/migrations/2026_10_03_000001_create_r1_learning_insight_entries_table.php)
- [Eyni event, eyni entry, collision və late event testləri](../../Modules/LearningInsights/tests/Feature/LearningInsightsProjectionSpecificationTest.php)
- [Rebuild və republish](rebuild-vs-republish.md)
- [Outbox/inbox sərhədi](outbox-vs-inbox.md)
