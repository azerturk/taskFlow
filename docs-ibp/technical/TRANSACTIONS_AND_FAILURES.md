# Transaction və xəta zamanı real davranış

Transaction eyni DB connection-dakı yazıları birlikdə commit/rollback edir. Diskə yazılmış fayl, HTTP cavabı və commit-dən sonra işləyən listener avtomatik onunla geri qayıtmır. Bu sənəd cari kodun hansı sərhəddə nəyi qoruduğunu izah edir; gələcək outbox/queue dizaynı deyil.

## Əvvəl üç sadə nümunə

**Project create:** layihə yazılıb owner üzvlüyü yazılmasa yarımçıq nəticə istəmirik. Eyni DB transaction daxilində olduğuna görə üzvlük/Activity mərhələsi fail edərsə layihə yazısı da rollback olur.

**Fayl upload:** fayl diskə yazılıb, sonra DB association mərhələsi fail edə bilər. DB rollback association-u geri qaytarır, amma disk faylını yox. Service ayrıca compensation, yəni yazılmış faylı təmizləmə cəhdi edir. Bu cəhdin özü də fail edə bilər.

**R1 publish:** source entry commit olunur, sonra listener işləyir. Listener fail edərsə source artıq qalmış ola bilər. Bu, əvvəlki iki nümunədən fərqli sərhəddir: sonrakı exception keçmiş commit-i silmir.

Deməli, «xəta oldu, hər şey geri qayıtdı» ümumi cümləsi təhlükəlidir. Həmişə soruş: hansı yazı, hansı connection, hansı transaction və commit-dən əvvəlmi, sonramı?

Hər hissə üçün ayrıca dərs var: [transaction və compensation](../extended/transaction-vs-compensation.md), [media upload](../diagrams/flows/media-upload.md), [media delete](../diagrams/flows/media-delete.md), [R1 publish](../diagrams/labs/publish.md), [R1 rebuild](../diagrams/labs/rebuild.md). Aşağıdakı cədvəllər onların dəqiq texniki sərhədidir.

## Kim transaction açır?

Üst səviyyə mutation service tam use case-in transaction sahibidir. Eyni transaction-da işləyən daxili collaborator əlavə gizli transaction açmır. Query service transaction sahibinin yerinə keçmir; repository query/persistence və lock-u idarə edir.

| Use case | Üst sərhəd | Eyni DB transaction-a daxil olanlar |
|---|---|---|
| Layihə yaratmaq | `ProjectService::create()` | Project + owner-manager üzvlüyü + Activity |
| Üzv silmək | `ProjectMemberService::removeMember()` | Açıq assignment yoxlaması + project watcher cleanup + membership delete + Activity |
| İş yaratmaq | `TaskService::create()` | Project sequence lock/write + rank/task + label/watcher + Activity + uyğun database notification |
| Assignment | `TaskAssignmentService::assign()` | Assignee + version + auto-watch + Activity + notification |
| Status | `TaskStatusService::change()` | Lock/version/transition + timestamp + rank + Activity + notification |
| Reorder | `TaskRankService::reorder()` | Lock/version + qonşu mövqe/rebalance + Activity |
| Suspend | `AdminUserService::suspend()` | Açıq assignment cleanup + watcher/PAT/session delete + status + audit/notification |
| Attachment upload | `TaskAttachmentService::uploadMany()` | Fiziki yazıdan sonra Media metadata + attachment + Activity |

Məsələn, `ProjectService::create()` daxilində `addMemberWithinTransaction()` və `TaskService` daxilində `TaskRankService::placeAtEnd()` transaction-neutral collaborator-lardır. Ayrı entry point olan `ProjectMemberService::addMember()` isə öz transaction-ını açır.

Suspend cleanup-ı project lifecycle-ından asılı deyil: `AdminUserService` açıq assignment-ları completed/archived layihələrdə də təmizləyə bilər. Bu xüsusi account-təhlükəsizlik əməliyyatıdır; adi task mutation-larının active-only qaydasını ümumi şəkildə açmır. Bağlanmış işlərin assignee tarixçəsi saxlanır.

## Version konflikti rollback deməkdirmi?

`TaskStatusService::change()` və `TaskRankService::reorder()` əvvəl repository vasitəsilə lock alır, sonra `expectedVersion` ilə real `version`-ı müqayisə edir. Köhnə versiyada `TaskVersionConflict` atılır: həmin əməliyyatın yazıları commit edilmir. API 409 verir, Web istifadəçiyə refresh tələb edən təhlükəsiz mesaj göstərir.

Client yeni versiyanı bilmədən request-i təkrarlamamalıdır. Assignment/detail yazıları version-u artıra bilər, amma onlar hazırda universal `expected_version` müqayisəsinə malik deyil. Ətraflı: [optimistic concurrency](../extended/optimistic-concurrency.md).

## Multi-file upload mərhələləri

`TaskAttachmentService::uploadMany()` axını:

1. Task görünürlüyü, aktiv layihə və upload permission-u yoxlanır.
2. `MediaStorageService::storeFiles()` **bütün** faylları content/ölçü/MIME/image qaydaları ilə əvvəl hazırlayır.
3. Fayllar private diskə yazılır; hər uğurlu yazı üçün metadata ledger-i yaranır.
4. DB transaction açılır: Media metadata, Task association və Activity yazılır.
5. DB mərhələsi uğurludursa commit edilir; uğursuzdursa DB rollback və ledger-dəki faylların kompensasiyası edilir.

İkinci faylın disk yazısı fail etsə də əvvəl yazılmış fayllar `MediaBatchStorageException` ledger-i ilə təmizlənir. «All-or-nothing» tətbiqin məqsədli nəticəsidir, storage ilə DB arasında paylanmış atomik transaction deyil.

### Cleanup özü də uğursuz ola bilər

`compensateStored()` hər faylı silməyə çalışır. Silinməyən fayl üçün `MediaMetadataService::retainForCleanup()` aktiv, əlaqəsiz record saxlamağa çalışır, UUID-lər `MediaCleanupPendingException` daxilində qaytarılır və safe warning log yazılır. Record saxlamaq da fail etsə critical log yaranır. Disk yolu, checksum və credential log-a çıxarılmır.

Cari kodda avtomatik cleanup worker-i yoxdur. Retry oluna bilən məlumatın saxlanması avtomatik retry edildi demək deyil.

## Attachment delete atomik DB+disk əməliyyatı deyil

Ardıcıllıq mühümdür:

```text
TaskAttachmentService::delete()
  -> DB transaction: attachment delete + Activity
  -> commit
  -> MediaStorageService::delete()
       -> fiziki faylı sil
       -> metadata-nı soft-delete et
```

| Failure nöqtəsi | Real nəticə |
|---|---|
| Association/Activity DB mərhələsi | DB rollback; fiziki cleanup-a çatmır |
| Fiziki delete | Association artıq silinib; binary və aktiv metadata qalır; cleanup-pending xəta/log |
| Fiziki delete uğurlu, metadata delete fail | Binary yoxdur, metadata qala bilər; storage xəta/log |
| Sonradan internal cleanup retry uğurlu | Fayl yoxdursa fiziki addım no-op olur, metadata soft-delete edilir |

Eyni attachment endpoint-i ilə retry yetərli deyil: association artıq silinib və normal binding 404 verə bilər. Test `MediaStorageService::delete($media)` internal retry-sini sübut edir; ayrıca admin cleanup endpoint/UI implementasiya edilməyib. Sübut: `Modules/Tasks/tests/Feature/TaskAttachmentFailureSafetyTest.php`.

## R1: after-commit event nəyi dəyişir?

`LearningEntryService::publish()` əvvəl `DB::transaction()` ilə Catalog qeydini yazır, sonra `LearningEntryPublished` dispatch edir. Event `ShouldDispatchAfterCommit` tətbiq edir.

- Outer transaction yoxdursa insert-in commit-indən sonra listener eyni prosesdə dərhal işləyir.
- Caller outer transaction açıbsa service-in daxili transaction-u onun içindədir; listener ən xarici commit-i gözləyir.
- Outer rollback event callback-ını ləğv edir; Catalog və projection yazılmır.
- Listener olmadığı halda Catalog publish edilir, projection yaranmır.
- Listener commit-dən sonra xəta atsa xəta caller-a çıxır, **Catalog qeydi artıq commit edilmişdir**. Bu, publish-i kor-koranə təkrar çağırmaq üçün səbəb deyil: yeni source qeyd yarana bilər.

Queue, outbox, inbox, avtomatik retry və durable delivery yoxdur. After-commit rollback edilmiş qeydə projection yazılmasının qarşısını alır, amma commit-dən sonrakı proses dayanmasında mesajın saxlanmasına zəmanət vermir.

## R1 rebuild necə bərpa edir?

`RebuildLearningInsightsService::rebuild()` əvvəl Catalog-un public feed-ini materializə edir. Sonra Insights yazılarını bir transaction-da edir:

1. `PublishedLearningEntryFeed::all()` siyahını qaytarır; bu mərhələ fail etsə heç bir projection yazısı başlamır.
2. Hər source entry üçün `recordPublishedIfMissing()` çağırılır.
3. `entry_id` artıq varsa title/tarix/event ID yenilənmir.
4. Yoxdursa `source_event_id=null` ilə əlavə edilir.
5. Aralıq yazı fail etsə həmin rebuild-in bütün yeni yazıları rollback edilir; əvvəldən olan projection-lar dəyişmir.

Bu «bütün məlumatı sıfırdan yenidən hesabla» rebuild-i deyil; məqsəd itmiş projection-ları tamamlamaqdır. Sonradan event gəlsə əvvəlki null event ID də dəyişdirilmir. `entry_id` və non-null `source_event_id` UNIQUE-ləri duplicate/collision sərhədini verir; collision xətası udulmur.

Real provider/listener, commit/rollback və recovery sübutu `Modules/LearningInsights/tests/Integration/R1LearningFlowTest.php` daxilindədir. Burada `DatabaseMigrations` istifadə edilir ki, Feature testinin xarici test transaction-u real commit-i gizlətməsin.

## Əlaqəli sənədlər

- [Diagram xəritəsi](../diagrams/README.md)
- [Media modulu](../modules/MEDIA.md)
- [R1 laboratoriyası](../labs/r1/README.md)
- [Transaction və compensation müqayisəsi](../extended/transaction-vs-compensation.md)
- [After-commit sadə izahı](../extended/after-commit.md)
- [Test strategiyası](TESTING.md)
