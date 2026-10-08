# Activity qeydi və Laravel event-i

## Sual

Activity-də `TaskStatusChanged` var. Bu avtomatik Laravel listener çağırır?

## Sadə cavab

Xeyr. Activity event enum-u audit qeydinin adıdır. Laravel event-i isə dispatcher-ə verilən obyekt və ona bağlanan listener axınıdır. Adında “event” olması iki mexanizmi eyniləşdirmir.

## Cari vəziyyət

Məhsul use case-ləri Activity recorder-i birbaşa çağırır. R1 isə `LearningEntryPublished` obyektini Laravel dispatcher-ə göndərir. Learning event-i avtomatik production Activity log-a yazılmır.

## Terminləri sadələşdirək

**Audit** keçmiş əməliyyatın izidir: kim, hansı record-da, nə dəyişdi. **Dispatcher** event obyektini qeydiyyatlı listener-lərə ötürən mexanizmdir. **Enum** icazəli ad/dəyərlərin kodda məhdud siyahısıdır; enum adı özü dispatcher çağırışı deyil.

Toplantının protokolunu yazmaqla “toplantı bitdi” xəbəri vermək ayrı işdir. Protokol tarixçə üçündür, xəbər isə başqa işi başlada bilər. Birini etmək o birini avtomatik etməz.

## Həyat ssenarisi: status history və learning projection

1. Əvvəl Fidan task statusunu dəyişir.
2. Status use case yeni state ilə birlikdə Activity recorder-i çağırır.
3. Recorder sanitized audit row-u saxlayır; task history-də görünə bilər.
4. Ayrı R1 publish-də Catalog event obyektini dispatcher-ə verir.
5. Insights listener-i projection yaradır.
6. R1 event-i production task history-si yaratmır; iki flow bir-birinə qoşulmayıb.

```text
Task mutation → audit enum + actor/subject → activity_log
Learning publish → event object → dispatcher/listener → lab projection
```

## Real kod

Məhsul status service-i:

```php
$this->activity->record(ActivityEvent::TaskStatusChanged, $actor, $task, [
    'project_id' => $task->project_id,
    'task_id' => $task->id,
    'old' => $old,
    'new' => ['status' => $task->status->value, 'rank' => $task->rank, 'version' => $task->version],
]);
```

Activity recorder saxlamadan əvvəl payload-ı sanitize edir:

```php
$properties = $this->sanitize($properties);
$properties['schema_version'] = 1;
activity($event->value)->causedBy($actor)->performedOn($subject)->withProperties($properties)->event($event->value)->log($event->value);
```

Bu metodda `event(new LearningEntryPublished(...))` dispatch-i yoxdur.

## Axın

```text
Task status use case → ActivityRecorder → sanitized activity_log row
R1 publish → Laravel dispatcher → registered Insights listener → projection row
```

Audit “kim nəyi dəyişdi?” sualına cavab verir. Listener isə baş vermiş fakta uyğun başqa əməliyyatı edir. Audit row-un olması bütün consumer-lərin işlədiyini sübut etmir; event dispatch də avtomatik audit, retry və monitorinq yaratmır.

## Yanlış yanaşma

Activity cədvəlini event bus və ya transactional outbox kimi qəbul etmə. Orada safe/sanitized audit payload var, bütün biznes obyektini bərpa edən event-sourcing müqaviləsi yoxdur. Activity read də actor-visible scope-dan keçir; hər row hamıya görünmür.

## Kod parçasını açaq

- `ActivityEvent::TaskStatusChanged`: audit üçün canonical ad seçilir.
- `$actor`: dəyişikliyi kimin etdiyini bildirir.
- `$task`: audit-in hansı subject-ə aid olduğunu bildirir.
- `'old'`, `'new'`: təsdiqlənmiş əvvəl/sonra sahələri; request dump deyil.
- `sanitize($properties)`: təhlükəli məlumat və ölçü sərhədləri tətbiq olunur.
- `schema_version=1`: audit payload formasının versiyasıdır; task concurrency version-u deyil.
- `activity(...)->...->log(...)`: Spatie audit write-ıdır; `event(...)` dispatch-i ilə eyni funksiya deyil.

## Niyə bunları ayırırıq?

Audit oxucusu keçmişi araşdırır; listener cari biznes nəticəsi yaradır. Audit payload-u təhlükəsizlik üçün bəzi məlumatları qəsdən saxlamır. Ona görə həmin tarixçəni avtomatik tam event replay mənbəyi saymaq olmaz.

Event object-i isə consumer üçün contract daşıyır. Ona lazımsız user/request/model dump əlavə etmək həm coupling, həm secret riskini artırır. R1 payload yalnız dörd public sahədir.

## Konkret yanlış nəticə

Junior Activity row-unda `TaskStatusChanged` görüb bütün listener-lərin uğurla işlədiyini hesab edir. Bu row audit write-ını göstərir; R1 kimi ayrı listener flow-unu sübut etmir. Hər side effect uyğun testlə ayrıca yoxlanılır.

Digər yanlışlıq Activity modelinin subject ID-sini access yoxlaması saymaqdır. Actor həmin project/task-ı görə bilmirsə sırf audit metadata-sı ona görünürlük vermir.

## Özünü yoxla

1. Activity enum-u avtomatik Laravel event dispatch edirmi? **Xeyr.**
2. R1 publish avtomatik production Activity yazırmı? **Xeyr.**
3. Sanitized audit tam event-sourcing jurnalıdırmı? **Bu layihədə belə müqavilə yoxdur.**

[Activity diagramının izahı](../diagrams/flows/activity.md) ilə [R1 publish izahını](../diagrams/labs/publish.md) yan-yana müqayisə et.

## Kod və yoxlama

- [Status audit çağırışı](../../Modules/Tasks/app/Services/TaskStatusService.php)
- [Recorder](../../Modules/Activity/app/Services/ActivityRecorder.php)
- [Canonical Activity testləri](../../Modules/Activity/tests/Feature/ActivityCanonicalFlowTest.php)
- [R1 event və listener registration](../../Modules/LearningInsights/app/Providers/LearningInsightsServiceProvider.php)
- [Activity müqaviləsi](../modules/ACTIVITY.md)
- [Audit/notification/log fərqi](audit-notification-operational-log.md)
