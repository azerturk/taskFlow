# Outbox və inbox fərqli problemləri həll edir

## Sual

Duplicate-dən qorunmaq üçün outbox lazımdır, yoxsa inbox?

## Sadə cavab

**Outbox** producer tərəfində “məlumat commit oldu, amma xəbər göndərilmədi” boşluğunu azaltmaq üçündür: biznes yazısı ilə göndəriləcək event eyni lokal transaction-da saxlanılır. **Inbox** consumer tərəfində əvvəldən işlənmiş message identity-ni saxlayıb təkrar processing-i idarə etməyə kömək edir.

Bu iki anlayış fərqli sərhədlərdədir. Outbox təkbaşına duplicate processing-i yox etmir; retry zamanı eyni event yenidən çatdırıla bilər. Producer-in DB write/göndəriş problemi və duplicate consumer ehtiyacı üçün [AWS-in rəsmi outbox izahına](https://docs.aws.amazon.com/prescriptive-guidance/latest/cloud-design-patterns/transactional-outbox.html) bax. Oradakı AWS xidmətləri TaskFlow dependency-si deyil.

## Cari vəziyyət

R1-də nə transactional outbox, nə də inbox implementasiya edilib. Event durable event cədvəlinə yazılmır. Insights-in nullable `source_event_id` sahəsi tam inbox deyil: processing statusu, retry tarixi və ayrıca qəbul ledger-i yoxdur.

## Terminləri sadələşdirək

**Producer** faktı yaradan/göndərən tərəfdir, **consumer** həmin fakta reaksiya verən tərəfdir. **Delivery** xəbərin çatmasıdır. **Processing** consumer-in xəbərlə öz işini görməsidir. Çatmaq ilə uğurlu işlənmək eyni hadisə deyil.

Gündəlik poçt misalında outbox göndərilməli məktubların producer-də saxlanan siyahısına, inbox isə qəbul edilmiş məktub identity-lərinin consumer-də izlənməsinə bənzəyir. Bu yalnız anlayışı ayırmaq üçün bənzətmədir; real etibarlılıq üçün write/commit sərhədləri də düzgün qurulmalıdır.

## Həyat ssenarisi: iki müxtəlif boşluq

1. Əvvəl producer biznes məlumatını DB-də commit edir.
2. Proses xəbəri göndərməmiş dayanır: biznes faktı var, consumer xəbərsizdir.
3. Outbox anlayışı bu producer boşluğunda göndərilməli xəbəri biznes write ilə birlikdə saxlamağı nəzərdə tutur.
4. Ayrı ssenaridə xəbər consumer-ə çatır, nəticə yazılır, amma göndərən tərəf bunu bilmədiyi üçün təkrar çatdırır.
5. Inbox/idempotent processing anlayışı consumer-də həmin identity-nin əvvəl işlənməsini nəzərə almağa kömək edir.
6. Bu iki mexanizm ayrı tərəflərdəki problemlər üçündür; biri digərini avtomatik əvəz etmir.

```text
Producer boşluğu: məlumat commit → xəbər göndərilmədi
Consumer boşluğu: nəticə yazıldı → xəbər yenidən gəldi
```

Bu ssenarilər **nəzəri izahdır**. TaskFlow-da outbox publisher, inbox processing ledger-i və onların retry mexanizmi yaradılmayıb.

## Real kod

Projection sxemi:

```php
$table->unsignedBigInteger('entry_id')->unique();
$table->uuid('source_event_id')->nullable()->unique();
```

Rebuild event olmadan public feed-dən gəlir:

```php
$this->insights->recordPublishedIfMissing(
    entryId: $entry->id,
    sourceEventId: null,
    title: $entry->title,
    publishedAt: $entry->publishedAt,
);
```

`entry_id` projection identity-sidir. `source_event_id` varsa event mənşəyini və onun təkrar başqa entry-yə bağlanmamasını qoruyan əlavə unique constraint-dir.

## Axın müqayisəsi

```text
Bizdə: Catalog commit → yaddaşdakı dispatch → listener → projection
Outbox anlayışı: biznes + outbox eyni commit → ayrıca publisher → consumer
Inbox anlayışı: message gəlir → identity/işlənmə yoxlanır → business nəticə + ledger
```

Son iki sətir yalnız öyrənmə izahıdır; TaskFlow-da belə publisher/ledger yoxdur. R1-in recovery-si çatışmayan projection-u source-of-truth-dan yaratmaqdır, itmiş event-ləri replay etmək deyil.

## Yanlış nəticə

“Unique UUID var, deməli inbox və exactly-once hazırdır” demə. Constraint yalnız müəyyən DB duplicate formasını qadağan edir. Event-in mütləq çatdırılması ayrıca problemdir.

## Bizim kod parçası nəyi edir?

- `entry_id->unique()`: bir Catalog entry üçün bir Insights row-u saxlanmasını məcbur edir.
- `source_event_id->nullable()`: projection event olmadan rebuild yolu ilə də yarana bilər.
- `source_event_id->unique()`: saxlanmış non-null event ID-nin başqa projection-a verilməsini rədd edir.
- `sourceEventId: null`: rebuild saxta dispatch faktı yaratmır.

Bu schema “event qəbul edildi, işlənir, fail oldu, retry edildi” statuslarını saxlamır. Ona görə həmin iki sahəni tam inbox kimi təqdim etmək olmaz.

## Niyə R1-də bunu əlavə etmirik?

Cari məqsəd iki lokal modulun contract/event fərqini başa düşməkdir. Missing projection public feed-dən bərpa edilə bilir. Yeni outbox/inbox platforması əlavə etmək ayrı scope, əməliyyat nəzarəti və daha çox failure testləri tələb edər. Burada onun dizayn tapşırığını vermirik.

## Konkret xəta nümunəsi

Catalog row-u commit etdi və proses dispatch-dən əvvəl dayandı. `source_event_id` unique olsa da heç bir event gəlmədiyinə görə bu constraint recovery edə bilməz. Sonrakı rebuild source row-u oxuyub projection-u `null` event ID ilə yarada bilər; bu, itmiş event-in inbox-a çatdırılması deyil.

Digər tərəfdən outbox olduğu nəzəri sistemdə eyni xəbər iki dəfə göndərilə bilər. Outbox sözünü görüb “artıq consumer duplicate yoxlaması lazım deyil” demək düzgün deyil.

## Özünü yoxla

1. Outbox əsasən hansı tərəfin boşluğunu hədəfləyir? **Producer-də commit ilə göndəriş arasındakı boşluğu.**
2. Inbox ilə projection row-u eynidirmi? **Xeyr; məqsəd və saxlanan processing məlumatı fərqlənə bilər.**
3. R1-də outbox/inbox implementasiya edilibmi? **Xeyr.**

Cari sistemdə olanı [publish](../diagrams/labs/publish.md), [duplicate](../diagrams/labs/duplicate.md) və [rebuild](../diagrams/labs/rebuild.md) izahlarından izlə.

## Kod və yoxlama

- [Projection migration](../../Modules/LearningInsights/database/migrations/2026_10_03_000001_create_r1_learning_insight_entries_table.php)
- [Rebuild](../../Modules/LearningInsights/app/Services/RebuildLearningInsightsService.php)
- [Duplicate/late event testləri](../../Modules/LearningInsights/tests/Feature/LearningInsightsProjectionSpecificationTest.php)
- [Idempotency fərqi](idempotency-vs-exactly-once.md), [R1 müqaviləsi](../labs/r1/README.md)
