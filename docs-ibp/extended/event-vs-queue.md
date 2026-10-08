# Event ilə queue eyni deyil

## Sual

Queue worker yoxdursa event necə işləyir?

## Sadə cavab

Event dispatcher uyğun listener-ləri tapıb çağıra bilər. Listener həmin PHP prosesində dərhal işləyirsə queue lazım deyil. Queue isə işi sonradan worker-in icra etməsi üçün ayrıca mexanizmdir.

## Cari vəziyyət

R1 listener-i `ShouldQueue` implementasiya etmir. Provider onu adi Laravel listener-i kimi qeyd edir. `ShouldDispatchAfterCommit` queue göstərişi deyil: yalnız transaction bitənədək event-in dispatch vaxtını dəyişir.

## Terminləri sadələşdirək

**Synchronous/sinxron**: çağıran proses işi gözləyir, sonra davam edir. **Asynchronous/asinxron**: işi başqa vaxt/proses icra edə bilər; caller-in dərhal bitməsi işin tamamlanması deyil. **Worker** queue-da gözləyən işi götürüb icra edən prosesdir.

Qapını döyüb cavabı gözləmək sinxron əlaqəyə, işi növbə kağızına yazıb sonradan operatorun götürməsi queue yanaşmasına bənzəyir. Kağızın saxlanması və operatorun işləməsi ayrıca tələbdir. Event sözü təkbaşına bu növbəni yaratmır.

## Həyat ssenarisi: worker açmadan publish

1. Əvvəl Catalog və Insights provider-ləri Laravel tətbiqində boot olunub.
2. Fidan title ilə publish service-i çağırır.
3. Catalog DB qeydi commit olur.
4. Dispatcher qeydiyyatlı `RecordPublishedLearningEntry` listener-ini tapır.
5. Listener həmin PHP prosesində repository-ni çağırır.
6. Projection write bitəndən sonra caller davam edir; ayrıca worker başlamayıb.

```text
Əvvəl: listener qeydiyyatlıdır
Kim nə edir: publish → commit → dispatcher → listener
Sonra: Catalog + projection; caller listener vaxtını da gözləmişdir
```

## Real kod

```php
Event::listen(LearningEntryPublished::class, RecordPublishedLearningEntry::class);
```

Listener:

```php
public function handle(LearningEntryPublished $event): void
{
    $this->insights->recordPublishedIfMissing(
        entryId: $event->entryId,
        sourceEventId: $event->eventId,
        title: $event->title,
        publishedAt: $event->publishedAt,
    );
}
```

## Axın

```text
Publish → Catalog commit → dispatcher → handle() → Insights write → caller davam edir
```

Outer transaction varsa dispatcher həmin outer commit-i gözləyir. Ondan sonra listener yenə eyni prosesdə sinxron işləyir; background worker yaratmır.

## Xəta və zəmanət

Listener yavaşdırsa caller də gözləyir. Listener exception atırsa caller xətanı görür, amma Catalog artıq commit edilib. Burada queue retry, dead-letter queue və davamlı event saxlanması yoxdur. Process commit-dən sonra dayanarsa projection çatışmaya bilər; recovery public feed-dən rebuild-dir.

Laravel layihəsində `jobs` cədvəlinin olması konkret R1 axınının queued olması demək deyil. Test profilinin `QUEUE_CONNECTION=sync` olması da listener-i `ShouldQueue` etməz.

## Kodun vacib sətirlərini açaq

- `Event::listen(...)`: event ilə hansı listener class-ının əlaqəli olduğunu qeyd edir.
- `handle(LearningEntryPublished $event)`: listener-in qəbul etdiyi typed xəbərdir.
- `$this->insights->recordPublishedIfMissing(...)`: listener öz modulunun repository sərhədindən yazır.
- `entryId`: hansı entry üçün surət yaratmaq lazımdır.
- `sourceEventId`: bu surət event yolu ilə yaranırsa xəbər identity-si.
- Listener-də `ShouldQueue` yoxdur: bu class queued listener kimi işarələnməyib.

`ShouldDispatchAfterCommit` başqa sualı cavablandırır: “DB commit-dən əvvəlmi, sonramı?” Bu marker “hansı worker işlədəcək?” sualına cavab deyil.

## Niyə burada queue əlavə etmirik?

Lab-ın məqsədi public contract, lokal event, duplicate və recovery fərqlərini kiçik nümunədə görməkdir. Queue əlavə etsək worker lifecycle, retry, failed job və proses monitorinqini də öyrənməli olarıq. Cari kod bunları implementasiya etmiş kimi təqdim edilmir.

## Konkret xəta nümunəsi

Insights write yavaşdırsa publish caller-i də gecikir. Listener DB exception atırsa `publish()` çağıran kod exception ala bilər; əsas Catalog row-u artıq commit olunub. “Worker yoxdur deyə heç nə baş verməyib” nəticəsi səhvdir.

Proses commit ilə dispatch arasında dayanarsa yaddaşdakı xəbər saxlanmaya bilər. `jobs` cədvəlinə baxıb bu event-i tapmağı gözləmək olmaz: R1 bu queue-yə iş yazmır.

## Özünü yoxla

1. R1 üçün ayrıca queue worker lazımdırmı? **Xeyr; listener lokal sinxron işləyir.**
2. After-commit background işləmək deməkdirmi? **Xeyr; yalnız transaction timing qaydasıdır.**
3. Listener fail olduqda avtomatik retry varmı? **Cari R1-də yoxdur.**

[Publish diagramının addım-addım izahı](../diagrams/labs/publish.md) eyni prosesdəki commit və listener sərhədini göstərir.

## Kod və yoxlama

- [Provider](../../Modules/LearningInsights/app/Providers/LearningInsightsServiceProvider.php)
- [Listener](../../Modules/LearningInsights/app/Listeners/RecordPublishedLearningEntry.php)
- [Event marker](../../Modules/LearningCatalog/app/Events/LearningEntryPublished.php)
- [Real commit və listener failure testləri](../../Modules/LearningInsights/tests/Integration/R1LearningFlowTest.php)
- Davamı: [after-commit](after-commit.md), [outbox/inbox](outbox-vs-inbox.md).
