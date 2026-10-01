# R1 — Modular Monolith sərhədlərini anlama və praktika

## Giriş

TaskFlow hazırda modular monolith arxitekturasındadır. Yəni sistem bir Laravel tətbiqi, bir deployment və bir database kimi işləyir, amma kod biznes sahələrinə görə modullara bölünüb:

```text
TaskFlow
├── Projects
├── Tasks
├── Media
├── Activity
└── Dashboard
```

Bu quruluşun məqsədi sadəcə kodu fərqli folder-lərə bölmək deyil. Əsas məqsəd hər modulun:

- hansı işə cavabdeh olduğunu;
- hansı məlumatın sahibi olduğunu;
- başqa modullara nə təqdim etdiyini;
- başqa modullardan nə tələb etdiyini

aydın saxlamaqdır.

Roadmap-dəki R1-in məqsədi bütün modulları bir-birindən tam ayırmaq, hər əməliyyatı event-ə çevirmək və ya sistemi microservice etmək deyil. Məqsəd hazırkı əlaqələri ölçmək, problemli əlaqələri görmək və yalnız real faydası olan yerlərdə sərhədləri yaxşılaşdırmaqdır.

R1 zamanı əsasən conceptləri öyrənəcək və kiçik praktikalar edəcəyik. Praktikaların bir hissəsi ayrıca yeni, sadə modul üzərində göstərilə bilər. Mövcud işləyən biznes axınları isə səbəbsiz yerə yenidən qurulmayacaq.

---

## 1. Modular monolith nədir?

Monolith o deməkdir ki, tətbiq birlikdə işləyir:

- eyni Laravel application;
- eyni process;
- eyni database;
- eyni deployment;
- eyni test və release prosesi.

Modular isə o deməkdir ki, tətbiqin daxilində məsuliyyətlər sərhədlərə bölünüb.

Məsələn:

- `Tasks` task, status, rank, watcher, comment və attachment əlaqəsini idarə edir;
- `Media` faylın saxlanması, MIME yoxlaması, private stream və fiziki silinməni idarə edir;
- `Activity` audit tarixçəsini idarə edir;
- `Projects` project və membership qaydalarını idarə edir.

Sadə nümunə:

```text
İstifadəçi task-a fayl əlavə edir
        ↓
Tasks task-ı və icazəni yoxlayır
        ↓
Media faylı yoxlayır və private storage-da saxlayır
        ↓
Tasks task ilə media arasındakı attachment əlaqəsini yaradır
        ↓
Activity əməliyyatı qeydə alır
```

Burada bütün sistem birlikdə işləyir, amma hər hissənin sahibi ayrıdır.

### Yalnız folder yaratmaq modular architecture deyil

Aşağıdakı struktur təkbaşına kifayət etmir:

```text
Modules/
├── Tasks/
└── Media/
```

Əgər `Tasks` Media modulunun istənilən modelinə, repository-sinə və daxili helper-inə sərbəst şəkildə daxil olursa, folder-lər ayrı olsa da sərhəd zəifdir.

Modul sərhədi bu sualla ölçülür:

> Başqa modul mənim daxili kodumu istədiyi kimi istifadə edə bilər, yoxsa yalnız əvvəlcədən müəyyən etdiyim public imkanlardan istifadə edir?

---

## 2. TaskFlow-da modullar hazırda necə aktivləşir?

Hər modulun öz `module.json` faylı var. Məsələn, Tasks modulu öz service provider-ini göstərir:

```json
{
  "name": "Tasks",
  "providers": [
    "Modules\\Tasks\\Providers\\TasksServiceProvider"
  ]
}
```

Kökdəki `modules_statuses.json` isə modulların deployment zamanı enabled olub-olmadığını göstərir:

```json
{
  "Projects": true,
  "Tasks": true,
  "Activity": true,
  "Dashboard": true,
  "Media": true
}
```

Hazırkı sistemdə ayrıca belə DB flag-ləri yoxdur:

```text
task_enabled
media_enabled
task_in_project
media_in_project
```

Bu, çatışmazlıq deyil. TaskFlow-un cari məhsul müqaviləsində modullar runtime plugin kimi admin panelindən açılıb-bağlanmır. Bütün beş modul tətbiqin qəbul edilmiş hissəsidir.

### Üç fərqli anlayışı qarışdırmamalıyıq

```text
1. Modulun kodu repository-də varmı?
2. Modul deployment konfiqurasiyasında enabled-dirmi?
3. Konkret project üçün feature aktivdirmi?
```

Bunlar eyni şey deyil.

- Folder və Composer autoload kodun fiziki mövcudluğudur.
- `modules_statuses.json` tətbiq səviyyəli module registration-dır.
- Project-ə görə feature enable/disable ayrıca məhsul xüsusiyyətidir.

R1 zamanı üçüncü variantı — project-ə görə modulların açılıb-bağlanmasını — implementasiya etməyi planlaşdırmırıq.

### Modul folder-dən silinsə nə baş verər?

Əgər başqa modul həmin modulun class-ını birbaşa istifadə edirsə, folder-i silmək təhlükəsiz deyil.

Məsələn, hazırkı `TaskAttachmentService` aşağıdakı Media class-larından istifadə edir:

```php
use Modules\Media\Services\MediaMetadataService;
use Modules\Media\Services\MediaStorageService;

class TaskAttachmentService
{
    public function __construct(
        private readonly MediaStorageService $storage,
        private readonly MediaMetadataService $metadata,
    ) {}
}
```

Media folder-i silinsə, PHP həmin class-ları tapa bilməz. Media yalnız JSON-da disable edilsə belə provider binding-ləri və config/migration qeydiyyatı işləməyə bilər.

Bu səbəbdən aşağıdakı middleware problemi tam həll etmir:

```php
if ($moduleIsEnabled) {
    $media->upload();
}
```

Çünki dependency artıq class importunda, constructor-da, model relation-da və container resolution-da mövcuddur. Kod `if` blokuna çatmazdan əvvəl belə class/container xətası yarana bilər.

Nəticə:

> Runtime-da istənilən modulu silə bilən plugin sistemi qurmaq istəyiriksə, bu ayrıca arxitektura və məhsul işidir. Sadəcə middleware və üç fərqli flag əlavə etməklə həll olunmur.

R1-in indiki mərhələsində məqsəd modul silmə sistemi qurmaq deyil.

---

## 3. High cohesion və loose coupling

### High cohesion

High cohesion o deməkdir ki, bir-biri ilə əlaqəli işlər eyni modulda saxlanılır.

Məsələn, Media modulunda bunların birlikdə olması düzgündür:

- fayl tipinin yoxlanması;
- private storage-a yazılması;
- MIME aşkarlanması;
- checksum hesablanması;
- preview/download stream-i;
- fiziki cleanup.

Bunların yarısını Tasks, yarısını Projects daxilində saxlasaq Media məsuliyyəti sistemə səpələnər.

### Loose coupling

Loose coupling o deməkdir ki, bir modul başqa modulun daxili quruluşunu mümkün qədər az bilməlidir.

Məsələn, Tasks belə detalları bilməməlidir:

```text
Media hansı diskdən istifadə edir?
Path necə yaradılır?
MIME necə aşkarlanır?
Fiziki silinmə necə retry olunur?
Checksum hansı alqoritmlə hesablanır?
```

Tasks yalnız öz use case-i üçün lazım olan nəticəni bilməlidir:

```text
Bu faylları təhlükəsiz saxla.
Mənə public-safe nəticəni qaytar.
Uğursuzluq olarsa müəyyən edilmiş exception qaytar.
```

Loose coupling “heç bir modul digər modulu çağırmasın” demək deyil. Məqsəd çağırışın kiçik, məqsədli və stabil sərhəddən keçməsidir.

---

## 4. Hazırkı direct module call həmişə səhvdirmi?

Xeyr.

Hazırkı TaskFlow-da Tasks modulunun Media modulunu birbaşa çağırması məqsədlidir:

```text
Tasks → Media
```

Task-a attachment əlavə ediləndə request dərhal nəticə gözləyir:

- fayl düzgündürmü?
- upload uğurlu oldumu?
- bütün batch saxlanıldımı?
- istifadəçiyə hansı xəta qaytarılmalıdır?

Bu synchronous use case-dir. Ona görə birbaşa service/contract çağırışı uyğun ola bilər.

Sadələşdirilmiş hazırkı flow:

```php
class TaskAttachmentService
{
    public function uploadMany(Task $task, User $actor, array $files): array
    {
        $this->ensureVisible($task, $actor);

        $stored = $this->storage->storeFiles($files);

        return DB::transaction(function () use ($task, $actor, $stored) {
            $media = $this->metadata
                ->registerManyWithinTransaction($actor, $stored);

            // Task–Media association və Activity yaradılır.
        });
    }
}
```

Problem direct call-ın özü deyil. Problem consumer modulun provider modulun çoxlu daxili class və data strukturunu bilməsidir.

R1-də verəcəyimiz sual budur:

> Tasks həqiqətən `MediaStorageService`, `MediaMetadataService` və `Media` modelinin hamısını bilməlidir, yoxsa məqsədli bir public Media contract kifayətdir?

---

## 5. Module Public API və Contract

Modulun public API-si onun başqa modullara rəsmi olaraq təqdim etdiyi imkanlardır.

Bu public API aşağıdakılardan biri ola bilər:

- purpose-specific interface/contract;
- application service;
- facade tipli bir giriş class-ı;
- immutable request/response DTO-ları;
- qəbul edilmiş domain/integration event-lər.

Buradakı “Facade” mütləq Laravel-in static facade mexanizmi demək deyil. Sadəcə modulun bir neçə daxili service-ni gizlədən sadə public giriş obyekti ola bilər.

### Contract olmadan geniş giriş

```php
use Modules\Media\Models\Media;
use Modules\Media\Services\MediaMetadataService;
use Modules\Media\Services\MediaStorageService;
```

Bu halda consumer Media modulunun bir neçə daxili detalını bilir.

### Məqsədli contract ilə giriş

Sadələşdirilmiş nümunə:

```php
interface TaskMediaGateway
{
    public function storeAttachments(
        User $actor,
        array $files,
    ): StoredMediaBatch;

    public function preview(string $mediaId): StreamedResponse;

    public function download(string $mediaId): StreamedResponse;

    public function delete(string $mediaId): void;
}
```

Tasks yalnız bu contract-ı görür:

```php
class TaskAttachmentService
{
    public function __construct(
        private readonly TaskMediaGateway $media,
    ) {}
}
```

Media isə həmin contract-ı öz daxili service-ləri ilə implementasiya edir:

```php
final class MediaTaskGateway implements TaskMediaGateway
{
    public function __construct(
        private MediaStorageService $storage,
        private MediaMetadataService $metadata,
    ) {}
}
```

Beləliklə:

```text
Tasks
  ↓ yalnız public contract
MediaTaskGateway
  ↓
Media-nın daxili storage və metadata service-ləri
```

### Contract bizə nə verir?

- Consumer modul yalnız lazım olan əməliyyatları görür.
- Media-nın daxili quruluşu dəyişəndə Tasks daha az təsirlənir.
- Testdə contract fake/mock ilə əvəz edilə bilər.
- İcazəsiz daxili class istifadəsi architecture test ilə bloklana bilər.

### Contract nə vaxt lazımsız abstraction olur?

Əgər interface yalnız bir metodu olduğu kimi başqa service-ə ötürür və heç bir sərhəd yaratmırsa, faydası azdır:

```php
interface GenericModuleBus
{
    public function execute(string $module, string $action, array $data): mixed;
}
```

Bu contract deyil, type safety-ni və biznes mənasını gizlədən generic service locator-dur.

R1-in qaydası:

> Contract konkret cycle, testability, encapsulation və ya dəyişiklik problemini həll etməlidir. Sadəcə “best practice” görünsün deyə yaradılmamalıdır.

---

## 6. Encapsulation və Dependency Inversion fərqi

Public contract iki fərqli məqsəd üçün istifadə oluna bilər.

### 6.1. Encapsulation

Provider modul öz public contract-ını elan edir:

```text
Tasks → Media public contract → Media internals
```

Tasks hələ Media-dan asılıdır, amma yalnız onun public hissəsini görür. Dependency graph dəyişməyə bilər, lakin sərhəd daha təmiz olur.

### 6.2. Dependency inversion

Bəzən consumer ehtiyac duyduğu port-u özü elan edir:

```text
Projects özünə lazım olan WorkLookup port-unu elan edir
Tasks həmin port-u implementasiya edir
Host application binding edir
```

Nümunə:

```php
namespace Modules\Projects\Contracts;

interface ProjectWorkLookup
{
    public function hasAnyWork(int $projectId): bool;

    public function openAssignmentCount(
        int $projectId,
        int $userId,
    ): int;
}
```

Projects artıq Tasks repository-sinin bütün imkanlarını görmür. Özünə lazım olan iki sualı contract kimi müəyyən edir.

Bu yanaşma dependency direction-u yaxşılaşdıra bilər, amma binding və ownership daha diqqətli dizayn tələb edir. Hər cross-module call üçün avtomatik tətbiq edilmir.

---

## 7. Core capability və business module istiqaməti

Sadə düşüncə modeli kimi modulları belə təsnif edə bilərik:

### Business modullar

- Projects
- Tasks

Bunlar məhsulun əsas biznes anlayışlarına sahibdir.

### Supporting/foundation capability modulları

- Media
- Activity

Bunlar bir neçə biznes axınına texniki və ya supporting imkan verir.

### Read/composition modulu

- Dashboard

Dashboard ayrıca business state sahibi deyil; digər modulların read nəticələrini birləşdirir.

Bu yalnız düşüncə modelidir. TaskFlow-da ayrıca `Core` adlı modul yaradılmır.

Dependency direction üçün əsas qayda:

```text
Business module → supporting capability
```

Məsələn:

```text
Tasks → Media
```

Media-nın Tasks daxilinə müraciət etməsi isə risklidir:

```text
Media → Tasks  ✗
```

Media task-ın statusunu dəyişməyə, Task modelini update etməyə və ya Task repository-sini çağırmağa başlasa, foundation capability business moduldan asılı olar. Tasks söndükdə və ya dəyişdikdə Media da dağılmağa başlayar.

Hazırkı architecture test bunu qoruyur:

```php
$allowed = [
    'Tasks' => ['Projects', 'Media', 'Activity'],
    'Media' => [],
];
```

Yəni Media başqa məhsul modulunu import etməməlidir.

### Media Task haqqında məlumatı necə ala bilər?

Əgər Media-ya həqiqətən task-la bağlı məlumat lazımdırsa, üç variant düşünülür:

1. Tasks Media contract-ına lazım olan sadə məlumatı özü ötürür.
2. Media business modula geri çağırış etmir; nəticəni Tasks-a qaytarır və son qərarı Tasks verir.
3. Müstəqil side-effect üçün Media stabil event-i dinləyir.

Hansı variantın seçilməsi use case-in synchronous cavab, transaction və failure tələbinə bağlıdır.

---

## 8. Direct call, event və queue nə vaxt istifadə olunur?

Hər module communication event olmamalıdır.

### Direct synchronous call seç

Əməliyyatın nəticəsi əsas request-in uğuru üçün vacibdirsə:

```text
Task attachment upload
        ↓
Media faylı qəbul etməsə request uğurlu sayıla bilməz
```

Bu halda Tasks Media-nı synchronous contract vasitəsilə çağırmalıdır.

### Event seç

Bir state dəyişikliyi baş verib və başqa hissələr bundan xəbərdar olmaq istəyirsə:

```text
Task status dəyişdi
        ↓
Activity audit yazır
Notification recipient-lərə mesaj yaradır
Insights projection sayğacı yeniləyir
```

Burada producer belə deyir:

> Task statusu dəyişdi.

Producer hər listener-in nə edəcəyini bilməyə bilər.

### Queue/asynchronous processing seç

Side-effect request cavabını gözlətməməlidirsə və gecikmə qəbul edilirsə:

```text
Task status dəyişdi
        ↓
Əsas request tamamlandı
        ↓
Queue worker daha sonra statistik projection-u yenilədi
```

Amma async iş əlavə problemlər gətirir:

- retry;
- duplicate delivery;
- event ordering;
- failed job;
- monitoring;
- eventual consistency.

Bu problemlərə ehtiyac yoxdursa queue və broker əlavə etmək fayda deyil, əlavə əməliyyat yüküdür.

---

## 9. Domain Event və Integration Event

### Domain Event

Domain daxilində baş verən biznes faktını bildirir:

```php
final readonly class TaskStatusChanged
{
    public function __construct(
        public string $eventId,
        public int $taskId,
        public int $projectId,
        public string $oldStatus,
        public string $newStatus,
        public int $version,
        public int $actorId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
```

Event əmr deyil. `TaskStatusChanged` “statusu dəyiş” demir; “status artıq dəyişib” deyir.

### Integration Event

Başqa modul və ya xarici consumer üçün sabit contract kimi yayımlanan event-dir. Domain event ilə eyni məlumatdan yarana bilər, amma daha stabil və versioned contract tələb edir.

### Event payload necə olmalıdır?

Event-də yalnız consumer üçün lazım olan stabil məlumat olmalıdır:

```text
event_id
task_id
project_id
old_status
new_status
task_version
actor_id
occurred_at
```

Aşağıdakılar event-ə qoyulmamalıdır:

- bütün Eloquent model serialization-u;
- password/token/header;
- private storage path;
- binary fayl;
- lazımsız request dump;
- dəyişkən relation ağacı.

İlkin qaralamadakı bu event buna görə uyğun deyil:

```json
{
  "module": "task",
  "operation": "insert",
  "files": ["1.png"]
}
```

Çünki bu, generic və zəif typed-dir, həm də task yaradılması ilə attachment upload-u qarışdırır. Task faylsız yaradıla bilər; attachment isə ayrıca authorization, validation və failure qaydası olan use case-dir.

Daha məqsədli event belə görünür:

```text
TaskCreated
TaskStatusChanged
AttachmentUploaded
ProjectMemberRemoved
```

---

## 10. Event handler və publish/subscribe

Producer event-i publish edir, listener-lər isə onu dinləyir:

```text
TaskStatusService
        ↓ publish
TaskStatusChanged
        ├── Activity listener
        ├── Notification listener
        └── Insights listener
```

Sadə Laravel nümunəsi:

```php
TaskStatusChanged::dispatch(
    eventId: (string) Str::uuid(),
    taskId: $task->id,
    projectId: $task->project_id,
    oldStatus: $oldStatus,
    newStatus: $task->status->value,
    version: $task->version,
    actorId: $actor->id,
    occurredAt: new DateTimeImmutable(),
);
```

Listener:

```php
final class UpdateTaskStatusProjection
{
    public function handle(TaskStatusChanged $event): void
    {
        // Yalnız öz modulunun state-ni yeniləyir.
    }
}
```

### “Fire and forget” hər şey üçün uyğun deyil

Əgər əməliyyatın itməsi qəbul edilə bilmirsə, sadəcə event-i atıb unutmaq olmaz.

Məsələn:

- optional telemetry itə bilər;
- audit event-in itməsi təhlükəsizlik problemi ola bilər;
- notification retry oluna bilər;
- attachment storage failure əsas request-i fail etməlidir.

Hər listener üçün əvvəlcədən bu sual cavablandırılmalıdır:

> Bu listener işləməsə əsas əməliyyat uğurlu sayılırmı?

---

## 11. Transaction boundary və event zamanı

Transaction boundary bir use case-in hansı dəyişikliklərinin birlikdə uğurlu və ya uğursuz olacağını müəyyən edir.

Task status nümunəsi:

```php
DB::transaction(function () use ($task, $data, $actor) {
    // Task lock edilir.
    // expected_version yoxlanılır.
    // Status və rank dəyişir.
    // Activity yazılır.
});
```

Əgər event transaction bitməmiş queue-ya göndərilsə, consumer task commit olunmamışdan əvvəl işləyə bilər. Sonra transaction rollback olsa consumer mövcud olmayan dəyişikliyə reaksiya vermiş olar.

Ona görə async event üçün adətən bunlardan biri tələb olunur:

- dispatch after commit;
- transactional outbox.

### Fayl sistemi DB transaction-a daxil deyil

Media upload zamanı aşağıdakı fikir kifayət etmir:

```php
DB::transaction(function () {
    $task->save();
    Storage::put(...);
});
```

DB rollback olsa belə diskə yazılmış fayl avtomatik silinmir.

TaskFlow buna görə compensation istifadə edir:

```text
Fayllar əvvəl yoxlanır və storage-a yazılır
        ↓
DB transaction-da metadata + association + Activity yazılır
        ↓
DB mərhələsi fail olarsa yazılmış fayllar kompensasiya ilə silinir
```

Bu, transaction və compensating action fərqinin real nümunəsidir.

---

## 12. Transactional Outbox və Inbox

Outbox/inbox pattern-i R1-də dərhal bütün sistemə tətbiq edilməyəcək. Əvvəl nə üçün lazım olduğunu başa düşəcəyik.

### Problem

Belə bir flow düşünək:

```text
1. Task DB-də dəyişdi
2. Event broker-ə göndəriləcək
```

İki ayrı sistem var:

- database;
- broker/stream.

Task commit olub event göndərilməsə consumer xəbər tutmayacaq. Event göndərilib DB rollback olsa consumer yanlış məlumat alacaq.

### Outbox həlli

Task dəyişiklikləri ilə event eyni DB transaction-da outbox cədvəlinə yazılır:

```text
DB transaction
├── tasks update
└── outbox_events insert
```

Sonra worker outbox record-u oxuyur, event-i publish edir və göndərildiyini qeyd edir.

Sadə outbox record-u:

```text
id
event_id
event_type
aggregate_id
aggregate_version
payload
occurred_at
published_at
attempts
```

### Niyə duplicate event yarana bilər?

Worker event-i publish edib, amma `published_at` yazmazdan əvvəl dayana bilər. Növbəti run eyni event-i yenidən göndərər.

Buna görə delivery çox vaxt belə olur:

```text
at-least-once delivery
= event ən az bir dəfə çatacaq
= bəzən bir dəfədən çox çata bilər
```

### Inbox və idempotency

Consumer hər `event_id` üçün emal qeydi saxlayır:

```text
inbox_messages
├── event_id UNIQUE
├── consumer
└── processed_at
```

Listener eyni event-i ikinci dəfə alsa:

```php
if ($inbox->alreadyProcessed($event->eventId)) {
    return;
}
```

Bu idempotency-dir: eyni əməliyyat təkrar gəlsə nəticə ikinci dəfə pozulmur.

R1 praktikası üçün outbox/inbox kiçik nümunədə öyrənilə bilər. Real notification, audit və media flow-ları acceptance parity olmadan birdən-birə outbox-a daşınmayacaq.

---

## 13. Redis Streams, RabbitMQ və Kafka lazımdırmı?

Hazırkı TaskFlow bir tətbiq və bir server kimi qəbul edilir. Buna görə external broker avtomatik “best practice” deyil.

Seçim ehtiyacdan başlamalıdır:

### Laravel synchronous event

Uyğundur:

- eyni process;
- dərhal işləməlidir;
- listener failure əsas transaction-a təsir edə bilər;
- tədris və sadə decoupling.

### Laravel queue + database/Redis driver

Uyğundur:

- iş request-dən sonra görülə bilər;
- retry lazımdır;
- queue worker idarə olunur;
- əlavə broker infrastrukturu istənilmir.

### Redis Streams

Uyğun ola bilər:

- consumer group lazımdır;
- stream replay və offset idarəsi lazımdır;
- komanda Redis əməliyyatlarını idarə edə bilir.

Amma retry, pending entries, trimming, poison message və monitoring ayrıca həll edilməlidir.

### RabbitMQ/Kafka

Yalnız real throughput, çox consumer, integration və operational tələb olduqda düşünülür. Tək serverli modular monolith üçün sırf “daha professionaldır” deyə əlavə edilmir.

R1-in hazırkı mərhələsində məqsəd broker seçmək deyil. Əvvəl Laravel-in lokal event/queue mexanizmi ilə communication və reliability anlayışları öyrənilməlidir.

---

## 14. Database isolation nə deməkdir?

TaskFlow bir database istifadə edir. R1 zamanı database-per-module və ya distributed transaction qurulmayacaq.

Amma vahid database daxilində table ownership müəyyən etmək olar:

```text
Projects owns:
  projects
  project_members

Tasks owns:
  tasks
  task_comments
  task_watchers
  task_labels
  task_attachments

Media owns:
  media

Activity owns:
  activity_log contract and queries
```

Əsas qayda:

> Bir modul başqa modulun cədvəlini istənilən formada birbaşa dəyişməməlidir.

Məsələn, Tasks `media` cədvəlinə birbaşa insert etməməlidir. Bunun əvəzinə Media-nın public application boundary-sini çağırmalıdır.

### Cross-module foreign key qadağandırmı?

Xeyr. Cari modular monolith-də cross-module foreign key qəbul edilir. Məsələn, `task_attachments.media_id` Media record-una bağlanır.

R1-də məqsəd bütün foreign key-ləri silmək deyil. Məqsəd ownership-i aydın saxlamaq və başqa modulun cədvəlinə gizli write etməməkdir.

### Read model və projection

Dashboard kimi modul bir neçə modulun məlumatını oxumalıdır. Hər dəfə daxili table-lara sərbəst query etmək əvəzinə iki yol var:

1. Projects/Tasks/Activity read contract-larından hazır nəticə almaq;
2. ölçülmüş performance ehtiyacı varsa ayrıca projection saxlamaq.

Projection source-of-truth deyil. Məsələn, task status sayları üçün optimallaşdırılmış read cədvəli ola bilər, amma task-ın həqiqi statusu yenə Tasks modulundadır.

---

## 15. Architecture enforcement

Arxitektura yalnız sənəddə yazılsa zamanla pozula bilər. Ona görə qaydalar testlə qorunmalıdır.

TaskFlow-da artıq architecture test-ləri var. Məsələn:

- controller repository/Eloquent çağırmamalıdır;
- service Eloquent query qurmamalıdır;
- repository contract `Builder` qaytarmamalıdır;
- yalnız icazəli module dependency-lər olmalıdır;
- Media başqa məhsul modulunu import etməməlidir;
- yeni gizli cross-module import testdə görünməlidir.

Sadələşdirilmiş dependency testi:

```php
$allowed = [
    'Projects' => ['Activity', 'Tasks'],
    'Tasks' => ['Projects', 'Media', 'Activity'],
    'Activity' => ['Projects', 'Tasks'],
    'Dashboard' => ['Projects', 'Tasks', 'Activity'],
    'Media' => [],
];
```

R1 zamanı bu testlər aşağıdakı suallara cavab verəcək şəkildə inkişaf etdirilə bilər:

- hansı modul hansı modula neçə yerdən bağlıdır?
- dependency cycle varmı?
- public contract əvəzinə internal model/repository import olunubmu?
- yeni modul icazəsiz dependency yaradıbmu?
- contract breaking change veribmi?

### Cycle nümunəsi

Hazırda həm `Projects → Tasks`, həm də `Tasks → Projects` əlaqələri var. Bu avtomatik bug deyil; mövcud modular monolith üçün sənədləşdirilib və testlə allowlist olunub.

R1-də əvvəl bu əlaqələrin səbəbi ölçüləcək:

- Project key immutability üçün Tasks lookup;
- member removal üçün open assignment/watcher cleanup;
- Tasks üçün project lifecycle və membership qaydaları.

Sonra yalnız konkret fayda varsa purpose-specific contract və ya dependency inversion tətbiq ediləcək.

---

## 16. Yeni modul üzərində kiçik R1 praktikası

R1-də bütün mövcud modulları birdən refaktor etmək əvəzinə conceptləri kiçik tədris modulunda sınamaq daha təhlükəsizdir.

Məsələn, ayrıca branch-də sadə `Insights` tədris modulu düşünə bilərik. Bu ad nümunədir; məhsula əlavə edilməzdən əvvəl ayrıca qəbul olunmalıdır.

Insights aşağıdakı sadə işi görə bilər:

```text
Task status dəyişir
        ↓
TaskStatusChanged event-i yaranır
        ↓
Insights event-i qəbul edir
        ↓
Project/status üzrə sayğac projection-u yenilənir
        ↓
Sadə read endpoint nəticəni göstərir
```

Bu praktikanın məqsədi yeni böyük feature yaratmaq deyil. Aşağıdakı conceptləri görməkdir:

- module registration;
- public contract;
- event DTO;
- listener;
- after-commit davranışı;
- idempotency;
- read projection;
- contract və architecture testləri;
- module dependency graph.

### Praktika 1 — Direct contract

Insights əvvəl Tasks-ın purpose-specific read contract-ını istifadə edə bilər:

```php
interface TaskStatusSummary
{
    public function forProject(int $projectId): array;
}
```

Burada synchronous contract və read model öyrənilir.

### Praktika 2 — Local event

Sonra `TaskStatusChanged` local event-i yaradılıb Insights listener ilə projection yenilənə bilər.

Burada bunlar yoxlanılır:

- event payload minimumdurmu?
- event transaction-dan sonra işləyirmi?
- listener ikinci dəfə işləsə nəticə pozulurmu?
- Tasks Insights modulunu import edirmi?

Doğru istiqamət:

```text
Tasks event contract publish edir
Insights onu dinləyir
Tasks Insights-ın mövcudluğunu bilmir
```

### Praktika 3 — Module disable testi

Insights optional tədris consumer-i kimi disable ediləndə əsas Task status flow-u işləməyə davam etməlidir.

Bu, Media üçün avtomatik qayda deyil. Media attachment use case-in məcburi hissəsidir. Optional listener ilə mandatory synchronous dependency eyni şey deyil.

### Praktika 4 — Kiçik outbox laboratoriyası

Əgər local event praktikası tamamlanarsa, ayrıca test/lab səviyyəsində bir event outbox-dan publish edilə bilər:

- eyni event iki dəfə delivery edilir;
- consumer event ID-yə görə ikinci delivery-ni ignore edir;
- worker failure-dən sonra retry edir;
- əsas business transaction itmir.

Bu laboratoriya uğurlu olsa belə dərhal bütün Activity/Notification flow-larını outbox-a keçirmək qərarı verilmir.

---

## 17. Observability niyə vacibdir?

Direct synchronous call zamanı xəta istifadəçiyə eyni request-də görünür. Async event zamanı isə əsas request artıq uğurlu bitmiş ola bilər.

Ona görə event-driven flow üçün ən azı bunlar lazımdır:

- `event_id`;
- `event_type`;
- `correlation_id` — eyni istifadəçi əməliyyatına aid logları birləşdirmək üçün;
- `causation_id` — hansı event/command bu event-i yaratdı;
- attempt sayı;
- işləmə müddəti;
- success/failure statusu;
- retry və failed-job görünüşü.

Structured log nümunəsi:

```json
{
  "event": "task.status_changed",
  "event_id": "...",
  "correlation_id": "...",
  "task_id": 42,
  "project_id": 7,
  "consumer": "insights.status_projection",
  "attempt": 1,
  "result": "processed"
}
```

Activity log operational log deyil:

- Activity istifadəçiyə/auditə görünən biznes tarixçəsidir;
- structured operational log developer və operator üçün processing məlumatıdır;
- metrics ümumi say və müddətləri göstərir;
- trace bir request/event zəncirini izləyir.

R1-də tam distributed tracing sistemi qurulmayacaq. Amma event praktikası edilirsə event ID və structured log başlanğıcdan düşünülməlidir.

---

## 18. Əsas conceptlərin sadə xəritəsi

R1-də bütün terminləri implementasiya etməyəcəyik. Onların bir-biri ilə əlaqəsini başa düşəcəyik.

### Core architecture

- **Modular Monolith:** bir tətbiq daxilində sərhədli modullar.
- **Module Boundary:** modulun daxili və public hissəsinin sərhədi.
- **High Cohesion:** əlaqəli məsuliyyətlərin eyni modulda qalması.
- **Loose Coupling:** modulların bir-birinin daxili detallarını az bilməsi.
- **Encapsulation:** internal model/service/repository-lərin gizlədilməsi.
- **Dependency Direction:** hansı modulun hansından asılı ola biləcəyi.
- **Dependency Inversion:** consumer-in konkret implementation deyil, ehtiyac duyduğu port-a bağlı olması.

### Module communication

- **Direct call:** nəticə dərhal lazımdır.
- **Contract/Public API:** modulun rəsmi təqdim etdiyi typed imkan.
- **Facade:** modulun bir neçə daxili işini bir giriş nöqtəsinin arxasında gizlədir.
- **Proxy:** contract çağırışını ötürür və lazım olduqda guard/log/cache kimi davranış əlavə edir.
- **Port:** consumer-in ehtiyacını ifadə edən interface.
- **Adapter:** başqa implementation və ya xarici sistemi port-a uyğunlaşdırır.
- **Anti-Corruption Layer:** xarici modelin daxili domain modelini pozmasının qarşısını alan çevirmə qatı.

### Event-driven

- **Domain Event:** domain daxilində baş vermiş fakt.
- **Integration Event:** modul/xarici consumer üçün stabil event contract-ı.
- **Publisher/Subscriber:** event-i yayan və dinləyən tərəflər.
- **Eventual Consistency:** consumer state-i əsas transaction-dan bir qədər sonra yenilənə bilər.
- **Idempotency:** eyni event təkrar gələndə nəticə pozulmur.
- **Event Ordering:** event-lərin hansı ardıcıllıqla işlənməsi.
- **Event Versioning:** payload dəyişəndə köhnə consumer-lərin qorunması.

### Reliability

- **Outbox:** DB dəyişikliyi və publish ediləcək event eyni transaction-da yazılır.
- **Inbox:** consumer işlədilmiş event ID-lərini saxlayır.
- **At-least-once delivery:** event çatacaq, amma duplicate ola bilər.
- **Retry:** müvəqqəti failure-dən sonra yenidən cəhd.
- **Dead Letter Queue:** təkrar-təkrar fail edən mesajların ayrıca saxlanması.
- **Compensation:** rollback edə bilmədiyimiz xarici nəticəni ayrıca geri təmizləmək.

### Domain və application design

- **Entity:** identity-si olan domain obyekti; məsələn Task.
- **Value Object:** identity-si olmayan, dəyəri ilə tanınan obyekt.
- **Aggregate Root:** aggregate dəyişikliklərinin giriş nöqtəsi.
- **Domain Service:** modelə rahat yerləşməyən domain qaydası.
- **Application Service:** use case orchestration və transaction sahibi.
- **Repository:** persistence/query sərhədi.
- **Command:** state dəyişmək niyyəti.
- **Query:** state dəyişmədən məlumat oxumaq.
- **CQRS:** command və query modellərini ehtiyac olduqda ayırmaq.

### Testing

- **Unit test:** tək qaydanı izolə yoxlayır.
- **Integration test:** repository/DB/constraint əlaqəsini yoxlayır.
- **Module integration test:** iki modulun qəbul edilmiş contract üzərindən işləməsini yoxlayır.
- **Contract test:** provider və consumer-in eyni contract-a uyğunluğunu yoxlayır.
- **Architecture test:** qadağan dependency və import-ları bloklayır.
- **End-to-end test:** real istifadəçi axınını browser səviyyəsində yoxlayır.

---

## 19. Qaçmalı olduğumuz anti-pattern-lər

### Big Ball of Mud

Hər modul hər modulu çağırır və ownership bilinmir.

### Cross-module repository access

Bir modul başqa modulun repository-sini onun bütün daxili imkanları ilə istifadə edir.

### Cross-module entity mutation

Media modulunun Task modelini birbaşa update etməsi kimi davranış.

### Shared DTO everywhere

Bütün modulların bütün use case-lər üçün bir böyük ümumi DTO istifadə etməsi.

### Generic Event Bus

```php
$bus->execute('tasks', 'update', $payload);
```

Bu approach typed contract və biznes mənasını itirir.

### Circular dependency

Modullar bir-birinin concrete implementation-larını tələb edir və ayrıca dəyişə bilmir.

### Hidden coupling

Import görünmür, amma service locator, global helper və ya birbaşa table query ilə başqa moduldan gizli asılılıq yaranır.

### Temporal coupling

Bir neçə əməliyyat mütləq müəyyən ardıcıllıqla çağırılmalıdır, amma bu contract-da görünmür.

### Distributed monolith

Kod fiziki olaraq ayrılır, amma bütün hissələr yenə eyni anda deploy olunmalı və bir-birinin database/state-inə sıx bağlı qalır. Microservice adı verilsə də real müstəqillik olmur.

---

## 20. R1 zamanı konkret olaraq nə edəcəyik?

R1 geniş event-driven və ya plugin platforması qurmayacaq. Təklif edilən kiçik scope:

1. Hazırkı module dependency graph çıxarılsın və sənədləşdirilsin.
2. Cross-module import-lar məqsədinə görə qruplaşdırılsın:
   - model/relation;
   - repository;
   - application service;
   - event/audit;
   - read model.
3. Bir real sərhəd seçilsin və purpose-specific contract praktikası edilsin.
4. Kiçik yeni tədris modulunda direct contract və local event nümunəsi qurulsun.
5. Event transaction/after-commit və duplicate delivery testləri yazılsın.
6. Architecture test yeni forbidden dependency-ni bloklasın.
7. Əlavə abstraction-ın hansı konkret problemi həll etdiyi report edilsin.

### Bu mərhələdə nə etməyəcəyik?

- bütün direct module call-ları event-ə çevirməyəcəyik;
- bütün modulları runtime plugin etməyəcəyik;
- DB, JSON və project səviyyəsində üçqat enable flag sistemi qurmayacağıq;
- ayrıca `Core` və ya `Shared` modulu yaratmayacağıq;
- Kafka/RabbitMQ əlavə etməyəcəyik;
- bütün modullar üçün outbox/inbox qurmayacağıq;
- schema-per-module və ya database-per-module-a keçməyəcəyik;
- mövcud transaction və all-or-nothing media davranışını pozmayacağıq;
- generic bus, service locator və speculative interface yaratmayacağıq.

---

## 21. Praktikanın qəbul meyarları

R1 praktikası aşağıdakı hallarda uğurlu sayılır:

- yeni contract konkret və purpose-specific-dir;
- consumer provider-in internal repository/service/model detallarını görmür;
- dependency direction sənədləşdirilib və architecture test ilə qorunur;
- yeni tədris modulu disable ediləndə əsas business flow pozulmur;
- event payload typed, minimum və secretsizdir;
- transaction-dan əvvəl/sonra dispatch davranışı testlə sübut olunur;
- duplicate event consumer state-ni ikinci dəfə pozmur;
- synchronous failure və async failure semantikası aydındır;
- mövcud SQLite/MySQL, architecture və feature testləri pass edir;
- yeni abstraction-ın faydası sadəcə nəzəri yox, ölçülən nəticə ilə göstərilir.

Məsələn:

```text
Əvvəl:
Tasks → Media-nın 3 internal class-ı və 1 model-i

Sonra:
Tasks → 1 purpose-specific Media contract

Sübut:
- daha az icazəli cross-module import;
- contract test;
- dəyişməyən upload davranışı;
- eyni transaction/compensation nəticəsi.
```

---

## Yekun

Modular monolith-in məqsədi modulların bir-biri ilə heç danışmaması deyil. Məqsəd onların **aydın, ölçülən və idarə olunan sərhədlərlə** danışmasıdır.

TaskFlow üçün doğru inkişaf ardıcıllığı belədir:

```text
Hazırkı dependency-ləri gör
        ↓
Ownership və istiqaməti müəyyən et
        ↓
Direct call həqiqətən problem yaradırmı ölç
        ↓
Lazım olan yerdə purpose-specific contract yarat
        ↓
Yalnız uyğun side-effect üçün event istifadə et
        ↓
Reliability tələb olunursa outbox/inbox qiymətləndir
        ↓
Architecture test və observability ilə sərhədi qoru
```

Ən vacib nəticələr:

- Direct call avtomatik pis deyil.
- Event avtomatik daha yaxşı deyil.
- Contract real sərhəd yaratmalıdır.
- Mandatory synchronous dependency ilə optional event listener eyni deyil.
- DB transaction fayl sistemi və brokeri rollback etmir.
- Outbox duplicate delivery ehtimalını yox etmir; idempotency tələb edir.
- Runtime module removal ayrıca plugin architecture problemidir.
- R1-in məqsədi böyük refaktor deyil, conceptləri real kod üzərində təhlükəsiz şəkildə öyrənməkdir.

