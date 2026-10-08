# Birbaşa çağırış və event

## Sual

Niyə bir yerdə service çağırırıq, başqa yerdə “baş verdi” event-i göndəririk?

## Sadə cavab

Birbaşa çağırış “bu işi et və nəticəni mənə qaytar” deməkdir. Event isə “bu fakt artıq baş verdi” xəbəridir. Bu seçim yaxşı/pis sıralaması deyil; nəticəyə dərhal ehtiyac və failure sərhədi ilə bağlıdır.

## Cari vəziyyət

Məhsul modullarında məqsədli birbaşa dependency-lər saxlanılır. R1 laboratoriyası iki əlaqəni ayrıca göstərir: publish event-i və recovery üçün sinxron public feed çağırışı. Bütün sistemi event-driven etmək nəzərdə tutulmur.

## Termini gündəlik dillə düşünək

**Caller** əməliyyatı çağıran tərəfdir. **Consumer/listener** xəbəri qəbul edib işləyən tərəfdir. **Payload** xəbər obyektinin daşıdığı məlumatdır. “Direct” caller-in bir konkret imkanın nəticəsini istəməsidir; event isə baş vermiş faktı bildirir.

Ofisdə “bu sənədi indi çap et, mənə ver” direct call-a bənzəyir. “Sənəd təsdiqləndi” xəbəri isə onu maraqlı tərəflərə bildirir: hər biri öz məsuliyyətinə uyğun reaksiya verir. Amma xəbərin necə və nə vaxt çatması ayrıca məsələdir.

## Həyat ssenarisi: sənəd yüklə, learning qeydi publish et

1. Əvvəl istifadəçi Task-a bağlı faylı download etmək istəyir.
2. Tasks association və actor access-i yoxlayır; Media-dan stream tələb edir.
3. Media nəticə vermədən HTTP download tamamlanmış sayıla bilməz.
4. Ayrı R1 əməliyyatında Əhməd `Public API` adlı learning qeydini publish edir.
5. Catalog əsas qeydi commit edir və onun yaradıldığı faktını bildirir.
6. Qeydiyyatlı Insights listener-i öz surətini yaradır; Catalog onun internal class-ını tanımır.

```text
Direct: tələb var → konkret nəticə istənir → nəticə caller-ə qayıdır
Event: fakt yarandı → fact payload göndərilir → listener öz işini görür
```

## Real nümunələr

Tasks media stream-i üçün nəticəni dərhal tələb edir:

```php
return $this->storage->download($this->visibleMedia($task, $attachment, $actor));
```

Catalog publish-dən sonra fact bildirir:

```php
event(new LearningEntryPublished(
    eventId: (string) Str::uuid(),
    entryId: $entry->id,
    title: $entry->title,
    publishedAt: $entry->published_at->toDateTimeImmutable(),
));
```

Catalog `LearningInsights` class-ını çağırmır. Listener-i Insights öz provider-ində qeydiyyatdan keçirir.

## Axın

```text
Download: Tasks → Media → stream response
Publish: Catalog write → commit → event dispatcher → Insights listener → projection
Recovery: Insights → Catalog public feed → Insights missing-only write
```

## Xəta zamanı fərq

Direct download uğursuzdursa çağıran tərəf stream nəticəsini ala bilməz. R1-də listener atdığı xəta caller-ə yayılır, amma artıq commit edilmiş Catalog qeydi geri alınmır. Listener ümumiyyətlə qeydiyyatda yoxdursa publish uğurla bitir, projection isə yaranmır.

“Event istifadə etdim, artıq bütün modullar tam müstəqildir” dəqiq deyil. Consumer event-in payload müqaviləsindən asılıdır; registration, timing və failure semantics hələ düşünülməlidir.

## Kodun vacib hissələrini açaq

- `return $this->storage->download(...)`: download üçün Media-nın hazırladığı response qaytarılır; result caller-ə lazımdır.
- `visibleMedia(...)`: stream-dən əvvəl icazəli association-dan Media alınır; event access yoxlamasını əvəz etmir.
- `new LearningEntryPublished(...)`: artıq yaranmış qeydin xəbəri qurulur; “qeyd yarat” command-ı deyil.
- `entryId`: xəbərin hansı əsas qeydə aid olduğunu bildirir.
- `eventId`: konkret xəbər identity-sidir; əsas entry identity-si ilə qarışdırılmamalıdır.
- `event(...)`: Laravel dispatcher-ə müraciətdir, birbaşa Insights modelinə write deyil.

## Niyə bir variantı hər yerə tətbiq etmirik?

Download response-u indi tələb olunursa onu qeyri-müəyyən “faylı hazırladım” xəbəri ilə əvəz etmək istifadəçi flow-unu çətinləşdirər. Əksinə, Catalog-un içinə Insights-in bütün persistence kodunu yazmaq modul sahibliyini pozar.

Event consumer-in daxili implementasiyasını producer-dən ayırır, amma payload müqaviləsinə dependency qalır. Direct call da public contract üzərindən edilə bilər: R1 rebuild bunun nümunəsidir. “Direct həmişə internal import deməkdir” doğru deyil.

## Konkret xəta nümunəsi

Catalog commit etdi, listener DB write zamanı exception atdı. Caller xəta alır, amma Catalog row-u qalır. Junior bunu “bütün publish rollback oldu” kimi başa düşüb publish-i təkrar çağırsa ikinci əsas qeyd yaradar. Recovery üçün eyni source entry public feed-dən oxunmalıdır.

Listener heç qeydiyyatda deyilsə exception yaranmır; sadəcə projection yoxdur. Yəni “consumer yoxdur” və “consumer fail oldu” iki fərqli nəticədir.

## Özünü yoxla

1. Event göndərən modul consumer-in repository-sini import etməlidirmi? **R1-də xeyr.**
2. Public feed direct call-dırmı? **Bəli, lokal sinxron PHP çağırışıdır.**
3. Event use case-in qalan qaydalarını avtomatik həll edirmi? **Xeyr; timing, failure və recovery ayrıca düşünülür.**

Əvvəl [publish diagramının izahını](../diagrams/labs/publish.md), sonra [public feed addımlarını](../diagrams/labs/public-feed.md) müqayisə et.

## Kod və yoxlama

- [Task media direct call](../../Modules/Tasks/app/Services/TaskAttachmentService.php)
- [Publish](../../Modules/LearningCatalog/app/Services/LearningEntryService.php)
- [Listener registration](../../Modules/LearningInsights/app/Providers/LearningInsightsServiceProvider.php)
- [Real failure/recovery testləri](../../Modules/LearningInsights/tests/Integration/R1LearningFlowTest.php)
- [Cari dependency qaydaları](../technical/ARCHITECTURE.md)
- Davamı: [event və queue](event-vs-queue.md), [after-commit](after-commit.md).
