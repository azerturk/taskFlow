# Tasks modulu

## Sadə dillə: işin bütün ömrü burada idarə olunur

Bir bug yaradılır, bir nəfərə tapşırılır, mərhələlərdən keçir, şərh və fayl alır, sonra bağlanır. Bu axının mərkəzi Tasks-dır. Amma Tasks bütün işi özü etmir: layihə üzvlüyünü Projects-dən, binary saxlamanı Media-dan, audit yazısını Activity-dən istifadə edərək təşkil edir.

**Use case** istifadəçinin bir məqsədidir, məsələn «statusu dəyiş». Service həmin məqsədin bütöv nəticəsinə sahibdir: təkcə `status` field-ini yazmır, lazım olan version/timestamp/rank/Activity/bildiriş addımlarını da idarə edir.

Bu səbəbdən controller-dən birbaşa `$task->update(...)` yazmaq düzgün qısa yol deyil. Belə yol başqa girişlərdə qorunan qaydaları və yan təsirləri ötürə bilər.

Bu modulu mövzulara bölərək oxu: [yeni iş](../diagrams/flows/task-create.md) → [assignment](../diagrams/flows/assignment.md) → [status](../diagrams/flows/status.md) → [rank](../diagrams/flows/reorder.md). Sonra [subtask](../diagrams/flows/subtask.md) və [əməkdaşlıq](../diagrams/flows/collaboration.md) dərslərinə keç.

## Məsuliyyət

Tasks work item lifecycle və əməkdaşlıq səthinin sahibidir: task, subtask, project-local issue identity, assignment, status, rank, backlog, board, label, watcher, comment və Task–Media association.

## Əsas invariantlar

- Bir project, bir reporter, sıfır/bir assignee.
- Fixed type: `task|bug|story|subtask`; fixed priority: `low|medium|high|urgent`.
- Yeni task server tərəfindən `backlog`, sütun sonu rank, version və local issue number ilə yaradılır.
- `subtask` eyni project-də standard parent tələb edir; yalnız bir hierarchy səviyyəsi var.
- Açıq child olan parent `done` edilmir.
- Status keçidləri yalnız `TaskStatusService` cədvəlindən gəlir.
- Status/rank mutation-u optimistic `expected_version` tələb edir.
- Açıq reorder manager-only; assignee status move yalnız target column sonuna append edir.
- Label project-scoped, watcher visibility deyil, comment plain text və maksimum 5 000 simvoldur.

## Application sərhədləri

Purpose-specific create/update/assignment/status/rank/label/watcher/comment/media service-ləri transaction və side-effect sahibliyini bölüşür. Top-level mutation transaction-a sahibdir; nested collaborator əlavə opaque transaction açmır. Query service-lər controller və Livewire üçün authorization-ready, eager-loaded result hazırlayır.

Task detail query-si watcher-ləri eager-load edir, cari actor-un watch vəziyyətini və manager üçün aktiv üzv namizədlərini presentation-ready qaytarır. Self və manager watcher mutation-ları yenə `TaskWatcherController -> TaskWatcherService` sərhədindən keçir. Completed/archived project-də siyahı görünür, mutation control-u göstərilmir.

`TaskStatusSelector` uğurlu status dəyişikliyindən sonra task detail route-una tam redirect edir. Bununla component xaricindəki header badge, version-dan asılı control-lar və Recent activity eyni canonical server state-dən yenidən render olunur; JavaScript-siz status formu da eyni service flow-dan sonra server redirect-i edir.

Repositories Eloquent scope, filter, sort, pagination, eager load, lock, issue sequence, rank append/rebalance və write əməliyyatlarına sahibdir. Contract `Builder` qaytarmır.

## Asılılıqlar

Projects membership/lifecycle qərarları üçün, Media private binary lifecycle üçün, Activity audit üçün birbaşa çağırılır. Notification host application-da qalır. Bu əlaqələr açıq və cari mərhələdə qəbul edilmişdir.

## İctimai səth

- Web: task list/detail/create/edit, backlog, board, label/watcher/comment/media əməliyyatları.
- Livewire: `TaskFilters`, `TaskStatusSelector`, `TaskCommentForm`.
- API: 9 core task, 2 backlog/board, 4 label, 3 watcher, 3 comment və 5 media association əməliyyatı.

Exact API [`API.md`](../technical/API.md), biznes qaydaları [`BUSINESS_RULES.md`](../business/BUSINESS_RULES.md) daxilindədir.

## Əsas axınların kod xəritəsi

| İstək | Qaydanın sahibi | Persistence/əməkdaşlıq |
|---|---|---|
| Yeni iş | `TaskService::create()` | `ProjectService::allocateIssueNumber`, `TaskRankService::placeAtEnd`, label/watcher, Activity və uyğun assignment bildirişi |
| Assignment | `TaskAssignmentService::assign()` | Aktiv target membership; versiya artımı; yeni assignee auto-watch; Activity/bildiriş |
| Status | `TaskStatusService::change()` | Lock, expected version, açıq subtask, `TaskTransitionRules`, timestamp və target-column append |
| Reorder | `TaskRankService::reorder()` | Manager authority; expected version; eyni sütunda qonşu intent; dəyişiklik yoxdursa əlavə reorder Activity yoxdur |
| Comment | `TaskCommentService::create/delete()` | Plain text/trim/limit, author/manager silmə, Activity; yeni şərh üçün watcher bildirişi |
| Watcher | `TaskWatcherService::watch/unwatch()` | Eyni nəticəni təkrar tələb etmək yeni subscription/Activity yaratmır |
| Media | `TaskAttachmentService::uploadMany/delete()` | Media binary sahibliyi, explicit association və compensation |

`version` yalnız status/rank zamanı artmır: detail və assignment dəyişiklikləri də onu artıra bilər. Amma client `expected_version` müqayisəsi hazırda status və rank yazılarının müqaviləsidir; bütün edit-lər üçün universal optimistic locking kimi təqdim edilmir. Stale status/rank client-i işi yenidən oxumalıdır, kor-koranə eyni yazını təkrarlamamalıdır.

Testlər modulun `tests/Feature` qovluğunda uyğun `TaskWorkflowTest`, `TaskAssignmentRulesTest`, `TaskRankTest`, `TaskTypeAndSubtaskTest`, `TaskAttachmentFailureSafetyTest` və digər məqsədli fayllardır. [Transaction/xəta qaydaları](../technical/TRANSACTIONS_AND_FAILURES.md), [diagramlar](../diagrams/README.md).
