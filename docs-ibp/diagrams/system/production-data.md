# Məlumat modeli: hansı qeyd harada və niyə saxlanır?

## Məqsəd

Bir task-ın layihəsi, reporter-i, assignee-si, comment-i və faylı var. Bunların hamısını bir cədvəldə saxlamaq əvəzinə əlaqəli cədvəllər istifadə edirik.

Bu yazı “cədvəl adlarını əzbərlə” dərsi deyil. Bir məlumatın sahibini və əlaqələrin nəyi qoruduğunu anlamaq üçündür.

## Əvvəl terminlər

- **Cədvəl / table:** eyni tip qeydlərin saxlandığı struktur.
- **Sətir / row:** bir istifadəçi, task və ya comment qeydi.
- **Primary key:** həmin sətirin daxili identity-si, çox vaxt `id`.
- **Foreign key / FK:** başqa cədvəldəki qeydə DB səviyyəli əlaqə.
- **UNIQUE:** göstərilən dəyərin və ya dəyər cütünün təkrarını məhdudlaşdıran DB qaydası.
- **Nullable:** həmin sahənin boş, yəni `null` ola bilməsi.
- **Pivot:** iki tərəf arasında çoxdan-çoxa əlaqəni saxlayan cədvəl.
- **Soft delete:** qeydi tam silmək əvəzinə silinmə vaxtı ilə işarələmək.
- **Table ownership:** hansı modulun həmin məlumatı yazmaq və idarə etmək məsuliyyətini daşıması.
- **Parent / child:** əlaqədə istinad edilən əsas qeyd və ona bağlı qeyd. Task üçün project parent, task isə child ola bilər.
- **Polymorphic əlaqə:** tək bir model növü yerinə type + ID ilə müxtəlif model növlərinə bağlanmaqdır; məsələn Activity subject-i task da, project də ola bilər.
- **Metadata / binary:** fayl haqqında saxlanan təsvir və faylın öz məzmunudur.

FK-nin olması access icazəsi vermir. O yalnız əlaqənin database tərəfində bütövlüyünü qoruyur.

## Sadə ssenari

Leyla, Murad və PAY-42 yalnız öyrənmə nümunəsidir; real data deyil.

Leyla PAY layihəsində iş yaradır, Murada təyin edir, “backend” label-ı seçir, sonra bir comment və PDF əlavə edir.

Bu əməliyyatın məlumatları bir neçə cədvələ yayılır. Bunun səbəbi hər məlumatın məqsədinin fərqli olmasıdır, məlumatın “itib qarışması” deyil.

## Şəkil

![Məhsul cədvəllərinin əlaqə və ownership xəritəsi](production-data.svg)

## Şəkildəki qrupları oxuyaq

1. **Host:** users, qlobal role/permission, PAT, session və notification məlumatı.
2. **Projects:** project identity və membership.
3. **Tasks:** task, comment, watcher, label və attachment association.
4. **Media:** faylın metadata-sı.
5. **Activity:** actor/subject məlumatı ilə audit qeydi.
6. **Dashboard:** ayrıca domain cədvəli yaratmadığı üçün data owner qutusu kimi göstərilmir.

Şəkil əsas əlaqə xəritəsidir; bütün field və bütün framework cədvəllərini göstərmir. Tam siyahı DATA_MODEL sənədindədir.

## Bir task üç fərqli user əlaqəsi daşıya bilər

`tasks.creator_id` reporter-i göstərir: işi kim yaradıb?

`tasks.assignee_id` məsul şəxsi göstərir: işi kim icra edir? Bu field nullable-dır; iş unassigned qala bilər.

`task_watchers` isə kimlərin bu işlə maraqlanıb subscription saxladığını göstərir. Watcher ikinci assignee deyil.

Buna görə Leyla reporter, Murad assignee, başqa bir layihə üzvü isə watcher ola bilər. Layihə üzvləri yalnız öz assignment-larını deyil, layihənin bütün görünən işlərini görə bilir.

## Real migration nümunəsi

İlkin tasks migration-ında bu field-lər var:

```php
$table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
$table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
$table->foreignId('assignee_id')->nullable()->constrained('users')->restrictOnDelete();
```

Sətirlərin mənası:

- `project_id`: task konkret projects qeydinə bağlıdır.
- `creator_id`: reporter mövcud users qeydinə bağlıdır.
- `assignee_id`: assignee varsa mövcud user olmalıdır; `nullable()` unassigned vəziyyətinə imkan verir.
- `restrictOnDelete()`: əlaqəli child qaldıqca parent-in fiziki silinməsini məhdudlaşdırır.

Bu qayda “assignee həmin layihənin üzvüdür” demir. Mövcud user olmaqla layihə üzvü olmaq fərqlidir. Membership invariantını service yoxlayır.

## Düz və kəsik ox nə deməkdir?

Bu data şəklində düz ox əsas DB FK əlaqəsini göstərir. Ox parent-dən child-a oxunur; FK field-i child cədvəldə saxlanır. Məsələn, projects → tasks xəttinin FK-si tasks.project_id-dir.

Kəsik ox logical/polymorphic əlaqədir. Laravel hansı type və ID-yə baxacağını bilir, amma DB həmin əlaqəni adi users FK-si kimi məcbur etmir.

Activity-də subject/causer, PAT-da tokenable, notification-da notifiable buna nümunədir. sessions.user_id index-i də təkbaşına FK demək deyil.

Dependency diagramındakı oxun mənası fərqlidir: orada caller-dən istifadə edilən modula gedir. İki şəkli eyni legend ilə oxuma.

## Label və watcher üçün pivot niyə var?

Bir task-a çox label bağlana bilər, bir label da çox task-da istifadə oluna bilər. `task_label` cədvəli bu cütlükləri saxlayır.

Eyni şəkildə, bir task-ın çox watcher-i, bir user-in çox watched task-ı ola bilər. `task_watchers` həmin əlaqəni saxlayır.

Cütlük üzərində UNIQUE/composite key eyni əlaqənin təkrar yazılmasını bloklayır. Amma cross-project label seçilməsini təkcə bu cütlük qaydası yox, application service də yoxlayır.

## Task attachment ilə Media niyə ayrıdır?

`task_attachments` “bu fayl bu task-a bağlıdır” əlaqəsini saxlayır. `media` isə filename, detected MIME, size, private path və digər metadata sahibidir.

Faylın binary-si private storage-dadır. Metadata və binary eyni şey deyil.

Cari final schema-da attachment-in özündə köhnə binary metadata field-ləri saxlanmır. Final ownership migration-ı bu sahələri ayırıb. `media_id` UNIQUE olduğu üçün bir Media ən çox bir Task attachment-a bağlıdır.

## Nəticə və failure halları

- Task yaratma qaydası uğurlu olsa bir task və lazım olan əlaqələr yaranır.
- İki eyni project-local issue number yazısı UNIQUE ilə məhdudlaşdırılır.
- Mövcud olmayan user ID-si FK ilə rədd edilə bilər; mövcud, amma qanunsuz assignee-ni service rədd edir.
- Soft delete fiziki delete deyil; buna görə FK cascade-in hər soft-delete zamanı işlədiyini düşünmə.
- Fayl silinməsi DB ilə eyni transaction resursu deyil. Media failure qaydaları ayrıca öyrənilməlidir.

R1 lab-da Catalog–Insights identity əlaqəsi FK deyil. Bu seçim production-dakı bütün cross-module FK-lərin qadağan olduğu mənasına gəlmir.

## Tez-tez qarışan suallar

**FK varsa Policy lazım deyil?**  
Lazımdır. FK məlumatın əlaqəsini, Policy actor-un icazəsini yoxlayır.

**UNIQUE bir field üçünmü olur?**  
Xeyr. Məsələn, project_id + issue_number cütü də unikal ola bilər.

**Soft-deleted row həqiqətən DB-dən yox olur?**  
Xeyr. Modelin default query-si onu gizlədə bilər, amma sətir saxlanır.

**Activity subject_id-ni bilmək record-a baxmağa icazə verir?**  
Xeyr. Oxu actor-visible project/task scope-u ilə qorunur.

## Özünü yoxla

1. Assignee boş qala bilər? **Bəli, nullable-dır.**
2. Bir task iki project-ə bağlıdır? **Xeyr.**
3. Faylın binary-si task_attachments field-idirmi? **Xeyr, Media private storage-da idarə edir.**
4. Project FK-si membership qaydasını da yoxlayırmı? **Xeyr.**

## Mənbələr

- [Tasks migration](../../../Modules/Tasks/database/migrations/2026_08_14_110100_create_tasks_table.php)
- [Final attachment ownership](../../../Modules/Tasks/database/migrations/2026_09_07_110000_finalize_task_attachment_media_ownership.php)
- [Migration rollback testi](../../../tests/Feature/MigrationRollbackTest.php)
- [Attachment data migration testi](../../../Modules/Tasks/tests/Feature/TaskAttachmentMediaMigrationTest.php)
- [Əsas data müqaviləsi](../../technical/DATA_MODEL.md)
- [Biznes qaydaları](../../business/BUSINESS_RULES.md)
- [Media delete dərsi](../flows/media-delete.md), [diagram indeksi](../README.md)
