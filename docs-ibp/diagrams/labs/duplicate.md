# Eyni xəbər iki dəfə gələndə niyə iki projection yaranmır?

## Həll etdiyimiz problem

Bir entry haqqında xəbər listener-ə təkrar verilə bilər. Əgər hər dəfə sadə `create()` işləsə, Insights-da eyni entry üçün iki sətir yaranar. Sonradan həmin sətirləri saysaq bir qeydi iki dəfə saymış olarıq.

Bu laboratoriyada qayda sadədir: **bir Catalog entry-si üçün ən çox bir Insights projection-u**. Bunun açarı `entry_id`-dir.

Bu səhifə «duplicate alınmır» cümləsinin hansı konkret davranışa aid olduğunu və hansı daha böyük zəmanətləri vermədiyini izah edir.

## Üç ID-ni qarışdırma

- Catalog entry-sinin `id`-si əsas qeydi tanıdır. Məsələn, `7`.
- Insights sətrinin `id`-si öz cədvəlinin primary key-idir. Catalog ID-si ilə eyni olmaq məcburiyyətində deyil.
- Insights-un `entry_id`-si «bu projection Catalog-dakı hansı qeydə aiddir?» sualını cavablandırır.
- `source_event_id` projection-u ilk yaradan event-in UUID-si ola bilər. Bu, entry ID-si deyil; rebuild zamanı `null` olur.

**Duplicate** eyni nəticəni təkrar yaratmaq cəhdidir. **Idempotent** isə əməliyyatın təkrarlanmasının həmin nəticəni bir daha çoxaltmamasıdır. Buradakı idempotentlik projection yazısına aiddir, Catalog publish əməliyyatına yox.

## Şəkildəki nümunəni izləyək

![Entry identity-si və duplicate davranışı](duplicate.svg)

Şəkildə `E1` və `E2` real UUID-lər əvəzinə oxunaqlı qısa adlardır. `7` və `8` də izah üçün seçilmiş entry ID-ləridir.

### İlk sətir: E1 → entry 7

1. Listener `entryId=7`, `eventId=E1`, title və tarix alır.
2. Repository `entry_id=7` olan projection axtarır.
3. Tapmırsa yeni sətir yazır.
4. Bu yeni sətirdə ilk title, tarix və `source_event_id=E1` qalır.

### İkinci sətir: E1 yenə entry 7 üçün gəlir

Repository artıq `entry_id=7` tapır. Yeni sətir yaratmır və mövcud metadata-nı yeniləmir. İki çağırışdan sonra da bir projection var.

### Üçüncü sətir: E2 də entry 7 üçün gəlir

Event ID-si fərqlidir, amma projection identity-si yenə `entry_id=7`-dir. Repository əvvəlki sətri saxlayır. E2 ayrıca emal jurnalı sətri kimi yazılmır.

### Qırmızı sətir: E1 bu dəfə entry 8 üçün gəlir

E1 artıq entry 7-nin sətrində saxlanıbsa onu yeni entry 8 üçün yenidən yazmaq ziddiyyətdir. `source_event_id` UNIQUE qaydası INSERT-i rədd edir. Bu xəta normal duplicate kimi udulmur.

## Əsas kod cəmi bir seçim edir

[Insights repository-si](../../../Modules/LearningInsights/app/Repositories/Eloquent/EloquentLearningInsightRepository.php):

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

Birinci array **axtarış şərti**dir: entry-yə uyğun sətir varmı?

İkinci array **yalnız yeni sətir lazım olduqda** yazılacaq dəyərlərdir. Əvvəlki sətir tapılıbsa bu dəyərlərlə update edilmir. Buna görə event-də gələn fərqli title əvvəlki projection title-ını dəyişdirmir.

Bu davranış `updateOrCreate()` ilə eyni deyil. Onu sadəcə ad oxşarlığına görə əvəz etsək «ilk metadata qalır» qaydasını dəyişmiş olarıq.

## Database də qaydanı qoruyur

[Migration](../../../Modules/LearningInsights/database/migrations/2026_10_03_000001_create_r1_learning_insight_entries_table.php) daxilindən:

```php
$table->unsignedBigInteger('entry_id')->unique();
$table->uuid('source_event_id')->nullable()->unique();
```

`entry_id` UNIQUE eyni entry üçün ikinci sətri database səviyyəsində qadağan edir. Bu vacibdir: iki caller eyni vaxtda «sətir yoxdur» görə bilər; yalnız əvvəlcədən `exists()` yoxlamasına güvənmək yetərli deyil.

`source_event_id` üçün `nullable` rebuild-in event olmadan projection yarada bilməsinə imkan verir. `unique` isə saxlanmış eyni non-null UUID-nin başqa yeni sətirdə istifadəsini rədd edir. Null dəyər «bu qeyd üçün event tarixçəsi itdi» hökmü deyil: sətir feed əsasında yaradılmış ola bilər.

## Əvvəl → çağırış → sonra

| Əvvəlki vəziyyət | Çağırış | Sonrakı vəziyyət |
|---|---|---|
| Entry 7 projection-u yoxdur | Entry 7, E1 | Bir yeni sətir, E1 saxlanır |
| Entry 7 projection-u var | Entry 7, E1 təkrar | Eyni sətir qalır |
| Entry 7 projection-u var | Entry 7, E2, başqa title | İlk title/tarix/event ID qalır |
| Rebuild entry 7-ni yaradıb | Entry 7, E1 | Mövcud sətir qalır, event ID null qalır |
| E1 entry 7-də saxlanıb | Yeni entry 8, E1 | UNIQUE conflict, yeni sətir yaranmır |

Bu cədvəl [Feature testlərində](../../../Modules/LearningInsights/tests/Feature/LearningInsightsProjectionSpecificationTest.php) yoxlanılan konkret qaydaların kiçik təsviridir.

## Bu niyə tam Inbox Pattern deyil?

Inbox adətən emal edilmiş message-ləri ayrıca tanımaq və təkrar emalı idarə etmək üçün nəzərdə tutulur. Burada isə bir projection sətrində yalnız ilk source event ID-si saxlanıla bilər. E2-nin ayrıca jurnalı yoxdur; rebuild sətrində heç event ID-si olmaya bilər.

Deməli, «bir projection təkrarlanmır» doğrudur. «Hər event mütləq çatır», «bütün event-lərin tarixçəsi saxlanır» və «exactly-once delivery var» doğru deyil. Delivery xəbərin çatmasıdır, idempotent projection isə çatan xəbərin nəticəsini çoxaltmamaqdır. Bunlar ayrı problemlərdir.

## Xəta zamanı necə düşünməli?

Normal duplicate üçün yeni sətir tələb olunmadığından əvvəlki nəticə qalır. Ziddiyyətli UUID üçün exception görünməlidir: onu `catch` edib uğur saymaq yanlış payload problemini gizlədər.

Listener commit-dən sonra bu xətanı verərsə Catalog entry-si artıq qalmış ola bilər. Source-u təkrar publish etməkdənsə əvvəl səbəbi araşdırmaq, sonra uyğun [rebuild](rebuild.md) yolu ilə çatışmayan projection-u tamamlamaq lazımdır. Bu sənəd real bazada əməliyyat başlatmaq göstərişi deyil.

## Özünü yoxla

1. Projection-un əsas identity-si nədir? **`entry_id`.**
2. Eyni entry üçün fərqli event title-ı yeniləyirmi? **Xeyr, `firstOrCreate` əvvəlki sətri dəyişmir.**
3. Duplicate qorunması event-in itə bilməyəcəyini göstərirmi? **Xeyr. Çatdırılma zəmanəti ayrıca mövzudur.**

## Davam üçün

- [İki cədvəlin əlaqəsi](lab-data.md)
- [Idempotentlik ayrıca izah](../../extended/idempotency-vs-exactly-once.md)
- [Outbox və Inbox fərqi](../../extended/outbox-vs-inbox.md)
- [LearningInsights texniki müqaviləsi](../../labs/r1/LEARNING_INSIGHTS.md)
- [Real listener və bərpa testləri](../../../Modules/LearningInsights/tests/Integration/R1LearningFlowTest.php)

[Bütün diagramlar](../README.md).
