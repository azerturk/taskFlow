# İstifadəçi axınları

## Axın / flow nə deməkdir?

Axın bir məqsədə çatmaq üçün addımların ardıcıllığıdır. Məsələn, «bug-u icraya götürmək» sadəcə bir button klikləmək deyil: giriş yoxlanır, iş tapılır, icazə və version yoxlanır, status/rank/tarixçə yazılır, nəticə göstərilir.

Bu sənəd istifadəçinin gördüyü ardıcıllığı qısa saxlayır. Hər flow-un geniş dərsi [diagram indeksində](../diagrams/README.md) ayrıca fayldadır. Dərsdə əvvəl məqsəd və nümunə, sonra qutuların izahı, real kod və xəta halı verilir.

İlk dəfə oxuyursansa [junior hekayəsindən](../JUNIOR_START.md) başla. Sonra bir axını seçib əvvəldən axıra izlə; bütün sistemin kodunu eyni anda açmaq lazım deyil.

### Axın seçmək üçün

- Hesaba girə bilmirsən: [session](../diagrams/flows/session.md) və [suspend](../diagrams/flows/account-admin.md).
- Layihə hazırlayırsan: [project lifecycle və üzvlər](../diagrams/flows/project.md).
- Yeni iş və ya subtask yaradırsan: [task create](../diagrams/flows/task-create.md), [subtask](../diagrams/flows/subtask.md).
- İcra/sıralama fərqini öyrənirsən: [assignment](../diagrams/flows/assignment.md), [status](../diagrams/flows/status.md), [reorder](../diagrams/flows/reorder.md).
- Şərh, watcher, notification və tarixçəni ayırırsan: [əməkdaşlıq](../diagrams/flows/collaboration.md), [Activity](../diagrams/flows/activity.md).
- Faylla işləyirsən: [upload](../diagrams/flows/media-upload.md), [stream](../diagrams/flows/media-stream.md), [delete](../diagrams/flows/media-delete.md).
- API ilə daxil olursan: [PAT](../diagrams/flows/pat.md), [authorization](../diagrams/flows/authorization.md).

## Giriş və hesab lifecycle

1. Administrator daxili istifadəçini ad, normallaşdırılmış e-poçt, parol və bir qlobal rol ilə yaradır.
2. Aktiv istifadəçi session login edir; uğurlu girişdə session ID yenilənir.
3. İstifadəçi öz parolunu cari parol təsdiqi ilə dəyişə bilər.
4. Administrator istifadəçini suspend etdikdə sistem eyni use case daxilində access-i bağlayır, session/token-ləri ləğv edir, açıq assignment-ları təmizləyir və watcher-ləri silir.
5. Reactivate yeni girişə icazə verir, lakin əvvəlki token/session və assignment/watcher vəziyyətini avtomatik bərpa etmir.

## Layihə lifecycle

1. Admin və ya `project_manager` layihəni yaradır; layihə `draft` olur və creator owner/manager kimi əlavə edilir.
2. Manager hələ `draft` ikən active istifadəçiləri layihəyə `manager` və ya `member` kimi əlavə edir, layihə detallarını hazırlayır. Bu əməliyyatlar `active` vəziyyətində də mümkündür.
3. Manager layihəni `active` edir.
4. Komanda work item, label, comment, watcher və media axınlarından istifadə edir.
5. Manager layihəni `completed` edir; detail, üzv və iş/əməkdaşlıq dəyişiklikləri dayanır.
6. Lazım olarsa manager completed layihəni yenidən `active` edir və ya `archived` vəziyyətinə keçirir. Lifecycle keçidi read-only qaydasının ayrıca, icazəli istisnasıdır.
7. Archive terminal read-only vəziyyətdir.

## İşin report edilməsi və icrası

1. Active layihə üzvü Task/Bug/Story/Subtask yaradır.
2. Sistem reporter-i aktordan götürür, project-local nömrə ayırır, statusu `backlog`, rank-ı sütunun sonu edir və reporter-i watcher əlavə edir.
3. Reporter işi unassigned saxlaya və ya özünə assign edə bilər; manager başqa aktiv üzvə assign edə bilər.
4. Assignment yeni assignee-ni watcher edir və actor xaric uyğun recipient-ə bildiriş yaradır.
5. Assignee workflow-un icazəli keçidləri ilə işi progress edir; manager icazəli reopen daxil bütün keçidləri edə bilər.
6. Hər status yazısında client-in `expected_version` dəyəri cari version ilə müqayisə edilir.

## Backlog və board

1. Backlog yalnız backlog işlərini project-local rank sırası ilə göstərir.
2. Manager qonşu work item-ləri göstərərək işi yenidən sıralayır.
3. Board drag/drop eyni backend status və rank use case-lərini çağırır.
4. JavaScript olmasa status form-u və manager reorder fallback-ı əsas əməliyyatı təhlükəsiz saxlayır.
5. Conflict zamanı UI cari state-i yeniləməyi tələb edir; raw rank qəbul edilmir.

## Subtask

1. Creator `subtask` seçəndə eyni layihədən standard parent seçir.
2. Sistem parent-in subtask olmadığını və eyni layihədə olduğunu yoxlayır.
3. Parent-in açıq child-ları qaldıqda `done` keçidi rədd edilir.

## Əməkdaşlıq

- Label: manager layihə label-ını yaradır; icazəli editor onu işə bağlayır.
- Watcher: üzv özünü, manager başqa aktiv üzvü əlavə/silə bilər.
- Comment: hər görünən active-project işi üzrə plain-text şərh yazılır; author və manager silə bilir.
- Notification: assignment, watched comment və watched status dəyişikliyi inbox-a düşür; read/read-all Web əməliyyatıdır.

## Media

1. İstifadəçi bir request-də ən çox 5 fayl seçir.
2. Sistem hamısını saxlamadan əvvəl count, size, content, MIME/extension və image ölçülərinə görə yoxlayır.
3. Media random private path-də saxlanır, metadata Media modulunda, Task–Media əlaqəsi Tasks modulunda yaranır.
4. Yazı uğursuz olsa DB batch rollback edilir və saxlanmış faylların cleanup-ı cəhd edilir. Cleanup da alınmasa retry üçün əlaqəsiz metadata saxlamaq cəhd edilir, pending xəta/log yaranır; bu, bütün fiziki faylların mütləq artıq silindiyi demək deyil.
5. İcazəli istifadəçi image/PDF üçün inline preview, bütün icazəli tiplər üçün download edir. Başqa tipin preview endpoint-i download fallback-ı verir.
6. Uploader və ya manager active layihədə attachment-i silir; fiziki silinmə alınmasa retry üçün safe UUID-li aktiv Media metadata qalır.

## Dashboard və Activity

- Dashboard yalnız actor-un görə bildiyi layihə/işlərdən metrik və limitli queue-lar yaradır.
- My Assigned, Reported by Me, My Watched və Overdue bir-birindən ayrı queue-dur.
- Activity global görünən, project və task səviyyəsində eyni scope ilə filtr olunur.
- QuickTaskCreate full create use case-i istifadə edir və client-owned status/rank qəbul etmir.

## API token axını

1. Client `POST /api/v1/auth/token` ilə credential, device name və allowlist ability-ləri göndərir.
2. Plaintext token yalnız 201 cavabında bir dəfə qaytarılır.
3. Protected request Bearer token, active account, ability, permission və policy yoxlamalarından keçir.
4. `GET /api/v1/me` cari actor-u qaytarır.
5. `DELETE /api/v1/auth/token` yalnız cari token-i ləğv edir və 204 qaytarır.

## R1 laboratoriya axını

Bu axın UI/HTTP endpoint deyil. `LearningEntryService::publish()` title-ı yoxlanmış DTO ilə Catalog-a qeyd yazır. `LearningEntryPublished` yalnız faktiki outer commit-dən sonra sinxron listener-ə çatır. Insights çatışmayan projection-u əlavə edir. Outer rollback olarsa listener işləmir. Listener commit-dən sonra xəta atarsa Catalog qeydi qalır; `RebuildLearningInsightsService::rebuild()` public feed üzərindən çatışmayan projection-ları bərpa edir. Ətraflı: [R1 laboratoriyası](../labs/r1/README.md).

Uğur və xəta budaqları: [diagram xəritəsi](../diagrams/README.md). Transaction-un nəyi geri qaytardığı: [transaction və xətalar](../technical/TRANSACTIONS_AND_FAILURES.md).

