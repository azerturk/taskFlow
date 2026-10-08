# Biznes qaydaları

## Bu qaydaları necə oxuyaq?

Biznes qaydası istifadəçi interfeysi dəyişsə də qorunmalı olan şərtdir. Məsələn, «bir işin ən çox bir assignee-si var» Web formundan da, API-dən də, başqa service çağırışından da pozulmamalıdır. UI-da dropdown-un tək seçimli olması təkbaşına bunu təmin etmir.

Hər maddəni belə yoxla: «Kim etmək istəyir? Layihə hansı vəziyyətdədir? Gələn məlumat düzgündürmü? Database-də nəticə nə olacaq?» İcazə və vəziyyət ayrı suallardır: manager olmaq completed layihədə adi task edit-ini avtomatik açmır.

Qaydaların işlədiyi kiçik ssenarilər [istifadəçi axınlarında](USER_FLOWS.md), hər addımın kod yolu isə [ayrıca diagram dərslərində](../diagrams/README.md) var. Əvvəl [iş yaratmağı](../diagrams/flows/task-create.md), sonra [status dəyişməyi](../diagrams/flows/status.md) oxumaq daha asandır.

## Hesablar

- Açıq qeydiyyat yoxdur; hesabı yalnız administrator yaradır.
- Hər istifadəçinin dəqiq bir qlobal rolu və `active` və ya `suspended` statusu var.
- E-poçt normallaşdırılır və unikaldır; parol hash-lənir və sonradan göstərilmir.
- Son aktiv administrator demote və ya suspend edilə bilməz.
- Suspend açıq təyinatla bloklanmır: giriş bağlanır, session və token-lər ləğv edilir, açıq işlər unassign olunur, watcher üzvlükləri silinir, tarixi reporter/assignee/Activity məlumatı qorunur.
- Suspend təhlükəsizlik cleanup-ıdır: `AdminUserService` açıq assignment-ları project lifecycle-dan asılı olmayaraq təmizləyir. Completed/archived layihənin adi iş redaktəsinin bağlı olması hesab-suspend cleanup-ını bloklamır; bağlanmış işlərin tarixi assignee-si saxlanır.
- Admin password reset bütün target session və token-ləri ləğv edir. Self-service dəyişiklik cari parolu tələb edir, digər session-ları və bütün token-ləri ləğv edir, cari session-u yeniləyir.

## Layihələr

- Layihənin adı, təsviri, unikal slug-u, 2–10 simvolluq unikal böyük hərfli canonical key-i, owner-i, üzvləri, tarixləri və lokal issue sequence-i var. Xam key inputu trim edilir və böyük hərfə çevrilir.
- Yeni layihə `draft` yaranır. Keçidlər:

```text
draft     -> active, archived
active    -> completed, archived
completed -> active, archived
archived  -> keçid yoxdur
```

- Layihə detalları və üzvlük `draft` və `active` vəziyyətlərində dəyişdirilə bilər: komanda aktivləşdirmədən əvvəl hazırlanır. İş, label, watcher, şərh və media dəyişiklikləri isə yalnız `active` layihədə mümkündür.
- `completed` və `archived` layihələrdə detallar, üzvlər və işlər dəyişdirilmir. Lifecycle ayrıca əməliyyatdır: səlahiyyətli manager `completed` layihəni `active` və ya `archived` edə bilər; `archived` terminaldır. Bu sərhədlər `ProjectService::update/changeStatus`, `ProjectMemberService` və `ProjectPolicy` ilə müəyyən olunur.
- Project key dəyişməsi hazırda layihədə ən azı bir **soft-delete olunmamış** iş varsa bloklanır. `ProjectService::update()` `existsForProject()` yoxlamasını istifadə edir; repository `withTrashed()` tətbiq etmir. Buna görə bütün işlər soft-delete olunarsa key dəyişməsi yenidən mümkün olur. Köhnə soft-deleted işlərin persisted display key-i yenilənmir və `next_issue_number` sıfırlanmır. «İlk issue-dan sonra tarix boyu immutable» bu kodun verdiyi tam zəmanət deyil.
- Owner layihənin manager-i olaraq qalır və silinə/demote edilə bilməz.
- Başqa üzvün açıq təyinatları varsa layihədən çıxarılması 409 conflict ilə bloklanır. Əvvəl reassign və ya unassign edilməlidir.
- Üzvlük silinəndə həmin layihənin watcher subscription-ları da silinir; tarixçə qorunur.

## Work item-lər

- Hər work item bir layihəyə və bir reporter-ə bağlıdır; assignee `null` və ya bir istifadəçidir.
- Açar project key və lokal nömrədən yaranır: məsələn, `PAY-42`.
- Yeni work item həmişə `backlog` statusunda və serverin hesabladığı rank ilə yaranır. Client ilkin status, rank, reporter, version və issue nömrəsi seçmir.
- Növ yalnız `task`, `bug`, `story`, `subtask`; prioritet yalnız `low`, `medium`, `high`, `urgent` ola bilər.
- Bütün aktiv layihə üzvləri layihənin bütün work item-lərini görə bilir. Assignee görünürlüyü deyil, məsuliyyəti və status səlahiyyətini göstərir.
- Üzv işi boş assignee ilə yarada və ya özünə assign edə bilər. Başqasına assign/unassign manager səlahiyyətidir.
- Manager bütün mutable detalları redaktə edə və işi soft-delete edə bilər.
- Reporter yalnız `backlog` və `todo` statusunda title, description, type, uyğun parent, priority, due date və label-ları dəyişə bilər. Parent dəyişməsi də eyni layihə və bir səviyyəli subtask invariantlarına tabedir.

## Workflow və tarixlər

```text
backlog     -> todo, cancelled
todo        -> backlog, in_progress, cancelled
in_progress -> todo, review, cancelled
review      -> in_progress, done, cancelled
done        -> in_progress
cancelled   -> backlog
```

- Assignee açıq statuslarda cədvəldəki keçidləri, o cümlədən `cancelled` keçidini edə bilər. `done` və `cancelled` vəziyyətindən reopen yalnız manager üçündür; manager bütün cədvəl üzrə icazəli keçidləri edə bilər.
- Assignee olmayan adi üzv status dəyişə bilməz.
- Hər status və rank yazısı `expected_version` ilə optimistic concurrency yoxlamasından keçir.
- Status dəyişən iş target sütunun sonuna yerləşir.
- `started_at` ilk `in_progress` keçidində yazılır və geriyə keçiddə tarix kimi saxlanır.
- `completed_at` `done` zamanı yazılır, reopen zamanı təmizlənir. `cancelled` tamamlanmış sayılmır.
- Overdue üçün cari kodda bir fərq var: ümumi task filter-i və Dashboard summary-si date-only `due_at`-ı bugünkü təqvim gününün başlanğıcı ilə müqayisə edir; bugünkü deadline həmin nəticələrdə overdue deyil. Dashboard overdue queue-su/API siyahısı isə `due_at < now()` istifadə edir və bugünkü date-only deadline-ı da gün ərzində daxil edə bilər. Hər ikisi `done`/`cancelled` işləri çıxarır. Bu, vahid biznes qaydası kimi təqdim edilməməli olan mövcud uyğunsuzluqdur; [real kod və sadə nümunə](../diagrams/flows/dashboard.md). Bu sənəd işi onu kodda düzəltmir.

## Rank, backlog və board

- Rank project və status sütunu daxilində unikaldır və prioritetdən ayrıdır.
- Açıq reorder yalnız project manager üçündür və qonşu intent (`before_task_id`, `after_task_id`) qəbul edir; client raw rank göndərmir.
- Reorder, create və status append project/sütun lock-ları və collision-safe rebalance ilə qorunur.
- Assignee status keçidi ilə yalnız öz işini target sütunun sonuna daşıyır; ümumi reorder səlahiyyəti qazanmır.
- Backlog yalnız `backlog` statusunu göstərir. Board workflow sütunlarına bölünür.

## Subtask

- `subtask` eyni layihədəki `task`, `bug` və ya `story` tipli parent tələb edir.
- Subtask parent ola bilməz; hierarchy bir səviyyədir, cycle və cross-project parent mümkün deyil.
- Açıq subtask-ı olan parent `done` edilə bilməz.
- Parent və child fərqli assignee-yə malik ola bilər.

## Label

- Label bir layihəyə məxsusdur; name/slug həmin layihədə unikaldır və rəng sabit allowlist-dəndir.
- Label CRUD manager üçündür.
- İcazəli editor mövcud eyni-layihə label-larını work item-ə bağlaya və ayıra bilər.
- Cross-project label qəbul edilmir; label silmək work item-i silmir.

## Watcher və bildiriş

- Aktiv layihə üzvü görünən işi watch/unwatch edə bilər; manager başqa aktiv layihə üzvünün watcher statusunu idarə edə bilər.
- Reporter və assignee avtomatik watcher olur; açıq şəkildə unwatch mümkündür, sonrakı yeni assignment assignee-ni yenidən əlavə edə bilər.
- Assignment, watched-item comment və status dəyişikliyi database/in-app bildirişi yaradır.
- Actor öz əməliyyatına görə bildiriş almır; recipient-lər aktiv, uyğun layihə üzvü olmalıdır.
- Watcher edit səlahiyyəti vermir. Ümumi task filter-də arbitrary watcher filter yoxdur; şəxsi queue Dashboard-dadır.

## Şərh

- Görə bilən layihə üzvü yalnız active layihədə şərh yaza bilər.
- Body plain text, trim edilmiş, boş olmayan və maksimum 5 000 simvoldur.
- Müəllif öz şərhini, manager istənilən şərhi soft-delete edə bilər.
- Yeni Activity payload silinmiş body-ni saxlamır.

## Media

- Media şəxsidir; Tasks yalnız Task–Media association və parent authorization sahibidir, binary/metadata lifecycle Media modulundadır.
- Bir fayl maksimum 10 MB, bir request maksimum 5 fayldır.
- İcazəli formatlar: PDF, PNG, JPEG, WebP, TXT/LOG/MD, DOC/DOCX, XLS/XLSX. SVG, HTML, script, archive, executable və naməlum binary qadağandır.
- Client filename, extension və MIME etibarlı sayılmır; content server tərəfdə aşkarlanır və extension/MIME cütü yoxlanır.
- Multi-file request əvvəl tam validasiya edilir; metadata/association/Activity yazıları bir DB transaction-dadır. Storage və ya DB failure-da bütün saxlanmış faylların kompensasiyası cəhd edilir. Cleanup özü fail edə bilər: əlaqəsiz cleanup record-u saxlamaq cəhd edilir və pending xəta/log yaranır; disk+DB üzrə qüsursuz atomiklik zəmanəti yoxdur.
- Inline preview yalnız image/PDF üçündür; başqa icazəli format üçün preview çağırışı download cavabına keçir. Download bütün qəbul edilən formatlar üçün icazəli stream-dir. Public URL və HTTP Range/206 yoxdur.
- Attachment silinməsində əvvəl Task–Media əlaqəsi və Activity bir DB transaction-da tamamlanır, sonra fiziki fayl silinir və Media metadata-sı soft-delete edilir. Fiziki cleanup alınmasa əlaqə geri qaytarılmır; aktiv, əlaqəsiz metadata retry üçün saxlanır. Avtomatik cleanup worker-i yoxdur.
- Uploader öz faylını, manager bütün task media-sını yalnız active layihədə silə bilər.

## Activity və Dashboard

- Activity layihə, üzvlük, work item, assignment, status/rank, label, watcher, şərh, media və hesab əməliyyatlarını canonical event-lərlə qeyd edir.
- Payload yalnız təsdiqlənmiş köhnə/yeni dəyərlər və safe summary saxlayır; credential, token, header, cookie, path, checksum və binary saxlamır.
- Dashboard və Activity list/API ilə eyni actor-visible scope-u istifadə edir.
- Dashboard project status sayları, ümumi iş, workflow/type paylanması, overdue, completed-today, assigned/reported/watched queue-ları, son Activity və QuickTaskCreate göstərir.

## Məhsul və laboratoriya sərhədi

`LearningCatalog` və `LearningInsights` iki R1 tədris moduludur; layihə/iş/istifadəçi axınlarına qoşulmur. Onların cədvəlləri, public PHP contract-ı və event-i məhsul qaydalarını dəyişmir. Cari işlək praktika [R1 sənədlərində](../labs/r1/README.md), gələcək production refaktorları isə [roadmap-də](../../ROADMAP.md) ayrılıqda göstərilir.

Axınların vizual xəritəsi: [diagramlar](../diagrams/README.md).
