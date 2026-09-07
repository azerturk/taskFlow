# Biznes qaydaları

## Hesablar

- Açıq qeydiyyat yoxdur; hesabı yalnız administrator yaradır.
- Hər istifadəçinin dəqiq bir qlobal rolu və `active` və ya `suspended` statusu var.
- E-poçt normallaşdırılır və unikaldır; parol hash-lənir və sonradan göstərilmir.
- Son aktiv administrator demote və ya suspend edilə bilməz.
- Suspend açıq təyinatla bloklanmır: giriş bağlanır, session və token-lər ləğv edilir, açıq işlər unassign olunur, watcher üzvlükləri silinir, tarixi reporter/assignee/Activity məlumatı qorunur.
- Admin password reset bütün target session və token-ləri ləğv edir. Self-service dəyişiklik cari parolu tələb edir, digər session-ları və bütün token-ləri ləğv edir, cari session-u yeniləyir.

## Layihələr

- Layihənin adı, təsviri, unikal slug-u, 2–10 simvolluq unikal böyük hərfli key-i, owner-i, üzvləri, tarixləri və lokal issue sequence-i var.
- Yeni layihə `draft` yaranır. Keçidlər:

```text
draft     -> active, archived
active    -> completed, archived
completed -> active, archived
archived  -> keçid yoxdur
```

- Yalnız `active` layihədə iş, üzv, label, watcher, şərh və media mutasiyası mümkündür.
- `completed` və `archived` layihələr görünə bilər, amma read-only-dir. Səlahiyyətli manager completed layihəni active vəziyyətinə qaytara bilər; archived terminaldır.
- İlk issue ayrıldıqdan sonra project key dəyişmir.
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
- Reporter yalnız `backlog` və `todo` statusunda title, description, type, priority, due date və label-ları dəyişə bilər.

## Workflow və tarixlər

```text
backlog     -> todo, cancelled
todo        -> backlog, in_progress, cancelled
in_progress -> todo, review, cancelled
review      -> in_progress, done, cancelled
done        -> in_progress
cancelled   -> backlog
```

- Assignee adi irəli/geri keçidləri edə bilər; manager bütün icazəli keçidləri, o cümlədən reopen keçidlərini edə bilər.
- Assignee olmayan adi üzv status dəyişə bilməz.
- Hər status və rank yazısı `expected_version` ilə optimistic concurrency yoxlamasından keçir.
- Status dəyişən iş target sütunun sonuna yerləşir.
- `started_at` ilk `in_progress` keçidində yazılır və geriyə keçiddə tarix kimi saxlanır.
- `completed_at` `done` zamanı yazılır, reopen zamanı təmizlənir. `cancelled` tamamlanmış sayılmır.
- Overdue: due date tətbiqin lokal vaxtından əvvəldir və status `done`/`cancelled` deyil.

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
- Multi-file request tam atomikdir: hər şey əvvəl validasiya edilir, hər hansı addım uğursuz olsa həmin request-in bütün file/record/association/Activity nəticələri kompensasiya olunur.
- Inline preview yalnız image/PDF üçündür; download hamısı üçün authorized stream-dir. Public URL və HTTP Range/206 yoxdur.
- Uploader öz faylını, manager bütün task media-sını yalnız active layihədə silə bilər.

## Activity və Dashboard

- Activity layihə, üzvlük, work item, assignment, status/rank, label, watcher, şərh, media və hesab əməliyyatlarını canonical event-lərlə qeyd edir.
- Payload yalnız təsdiqlənmiş köhnə/yeni dəyərlər və safe summary saxlayır; credential, token, header, cookie, path, checksum və binary saxlamır.
- Dashboard və Activity list/API ilə eyni actor-visible scope-u istifadə edir.
- Dashboard project status sayları, ümumi iş, workflow/type paylanması, overdue, completed-today, assigned/reported/watched queue-ları, son Activity və QuickTaskCreate göstərir.

