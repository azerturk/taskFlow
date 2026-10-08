# REST API v1

## Junior üçün: bir HTTP istəyi necə oxunur?

Client, məsələn Postman, URL-ə metod, header və lazım gələrsə body göndərir. `GET` oxumaq, `POST` yaratmaq, `PATCH/PUT` müəyyən dəyişiklik, `DELETE` silmə/ləğv üçün istifadə olunur. Dəqiq nəticə yenə aşağıdakı endpoint müqaviləsinə bağlıdır.

`GET /api/v1/tasks` siyahı istəyir. `PATCH /api/v1/tasks/{task}/status` isə konkret işin statusunu dəyişmək istəyir. `{task}` sənəddə placeholder-dır; request-də icazəli task ID-si ilə əvəz olunur.

Qorunan endpoint üçün Bearer token user-i tanıdır. Token-in `tasks:write` ability-si olsa da user həmin iş üzrə policy-dən keçməlidir. «Token var» ilə «hər şeyi edə bilər» eyni deyil. Token plaintext-i screenshot-a, log-a və report-a çıxarma.

Uğurlu JSON-da `data` resource-u, paginated list-də `links/meta` isə səhifələməni daşıyır. `204` cavabında body axtarma. Xəta zamanı əvvəl statusa, sonra təhlükəsiz `code/errors` sahələrinə bax; eyni request-i düşünmədən təkrarlama.

Oxu: [token-in tam axını](../diagrams/flows/pat.md), [icazə qatları](../diagrams/flows/authorization.md), [status mutation nümunəsi](../diagrams/flows/status.md), [HTTP API və PHP public API fərqi](../extended/rest-api-vs-module-public-api.md). Aşağıdakı cədvəl dəqiq route istinadıdır, ayrıca öyrənmə dərsini əvəz etmir.

## Ümumi müqavilə

Base path: `/api/v1`. JSON request-lər `Accept: application/json`, qorunan request-lər `Authorization: Bearer {token}` göndərir. Bütün route adları `api.v1.*` prefiksini daşıyır.

Bu sənəd yalnız **HTTP REST API** haqqındadır. LearningCatalog-un `PublishedLearningEntryFeed` PHP interface-i modulun daxili public API-sidir; URL, Bearer token və JSON endpoint deyil. LearningCatalog/Insights bu 46 əməliyyata heç bir route əlavə etmir. Müqayisə: [REST API və modul public API-si](../extended/rest-api-vs-module-public-api.md).

Token ability-si yalnız route ailəsini daraldır; Spatie permission, aktiv hesab yoxlaması və Policy/Gate qərarını əvəz etmir. Canonical ability-lər:

- `projects:read`, `projects:write`;
- `tasks:read`, `tasks:write`;
- `comments:write`;
- `activity:read`;
- `dashboard:read`.

## Runtime route manifesti

Aşağıdakı 46 əməliyyat tam v1 inventarıdır. `—` ability middleware-i olmadığını göstərir; `/me` və revoke yenə etibarlı Sanctum token-i tələb edir. Bu cədvəl architecture testində runtime route-ları ilə metod, yol və ability üzrə dəqiq müqayisə olunur.

| Metod | Yol | Token ability-si | Nəticə |
| --- | --- | --- | --- |
| POST | `/auth/token` | — | 201 token envelope; yanlış input/credential üçün 422 |
| GET | `/me` | — | 200 authenticated user resource |
| DELETE | `/auth/token` | — | 204, cari token ləğv olunur |
| GET | `/projects` | projects:read | 200 scope edilmiş pagination |
| POST | `/projects` | projects:write | 201 project resource |
| GET | `/projects/{project}` | projects:read | 200 scope edilmiş resource |
| PUT | `/projects/{project}` | projects:write | 200 project resource |
| PATCH | `/projects/{project}/status` | projects:write | 200; state conflict üçün 409 |
| GET | `/projects/{project}/members` | projects:read | 200 pagination |
| POST | `/projects/{project}/members` | projects:write | 201 member resource |
| PATCH | `/projects/{project}/members/{user}` | projects:write | 200 member resource |
| DELETE | `/projects/{project}/members/{user}` | projects:write | 204; açıq assignment üçün 409 |
| GET | `/tasks` | tasks:read | 200 scope edilmiş pagination |
| POST | `/tasks` | tasks:write | 201 task resource |
| GET | `/tasks/{task}` | tasks:read | 200 scope edilmiş resource |
| PUT | `/tasks/{task}` | tasks:write | 200 task resource |
| DELETE | `/tasks/{task}` | tasks:write | 204 soft-delete |
| PATCH | `/tasks/{task}/assignee` | tasks:write | 200 task resource |
| PATCH | `/tasks/{task}/status` | tasks:write | 200; transition/version conflict üçün 409 |
| PATCH | `/tasks/{task}/rank` | tasks:write | 200; version conflict üçün 409 |
| PUT | `/tasks/{task}/labels` | tasks:write | 200 task resource |
| GET | `/projects/{project}/backlog` | tasks:read | 200 pagination |
| GET | `/projects/{project}/board` | tasks:read | 200 fixed-column read model |
| GET | `/projects/{project}/labels` | tasks:read | 200 collection |
| POST | `/projects/{project}/labels` | tasks:write | 201 label resource |
| PATCH | `/projects/{project}/labels/{label}` | tasks:write | 200 label resource |
| DELETE | `/projects/{project}/labels/{label}` | tasks:write | 204 |
| GET | `/tasks/{task}/watchers` | tasks:read | 200 watcher collection |
| POST | `/tasks/{task}/watchers` | tasks:write | 204 |
| DELETE | `/tasks/{task}/watchers/{user}` | tasks:write | 204 |
| GET | `/tasks/{task}/comments` | tasks:read | 200 collection |
| POST | `/tasks/{task}/comments` | comments:write | 201 comment resource |
| DELETE | `/tasks/{task}/comments/{comment}` | comments:write | 204 |
| GET | `/tasks/{task}/media` | tasks:read | 200 pagination |
| POST | `/tasks/{task}/media` | tasks:write | 201 attachment collection |
| GET | `/tasks/{task}/media/{media}/preview` | tasks:read | 200 private stream |
| GET | `/tasks/{task}/media/{media}/download` | tasks:read | 200 private stream |
| DELETE | `/tasks/{task}/media/{media}` | tasks:write | 204 |
| GET | `/activity` | activity:read | 200 pagination |
| GET | `/projects/{project}/activity` | activity:read | 200 pagination |
| GET | `/tasks/{task}/activity` | activity:read | 200 pagination |
| GET | `/dashboard/summary` | dashboard:read | 200 summary resource |
| GET | `/dashboard/my-tasks` | dashboard:read | 200 collection |
| GET | `/dashboard/reported` | dashboard:read | 200 collection |
| GET | `/dashboard/watched` | dashboard:read | 200 collection |
| GET | `/dashboard/overdue` | dashboard:read | 200 pagination |

## Authentication və identity

`POST /auth/token` normallaşdırılmış e-poçt, parol, `device_name` və optional canonical `abilities[]` qəbul edir. Uğurlu cavab plaintext token-i yalnız bir dəfə göstərir; database hash saxlayır. Yanlış və suspended credential-lar enumeration yaratmayan 422 cavabı alır. Endpoint 5/dəqiqə limitlidir.

`GET /me` aktiv istifadəçini və effektiv ability-ləri qaytarır. `DELETE /auth/token` yalnız request-i authenticate edən token-i silir və body-siz 204 qaytarır. Public registration, API user administration, notification inbox və token-list/rotation UI yoxdur.

## Projects və üzvlər

Create input-u: `name`, `key`, optional `description`, `starts_at`, `due_at`. Key trim/böyük hərf normalizasiyasından sonra 2–10 böyük hərf/rəqəmdən ibarətdir, hərflə başlayır və unikaldır. Yeni layihə `draft` yaranır. Update zamanı soft-delete olunmamış issue varsa key dəyişikliyi `project_key_immutable` 409 verir. Cari repository soft-deleted işləri bu yoxlamaya daxil etmir; bütün issue-lər soft-delete olunarsa key dəyişməsi mümkün olur. Əvvəlki persisted issue nömrələri yenilənmir.

Status endpoint-i yalnız [`BUSINESS_RULES.md`](../business/BUSINESS_RULES.md) keçidlərini qəbul edir. Member add/update target active user-i və `manager|member` rolunu tələb edir. Owner demote/silinə bilməz; açıq assignment-lı üzvün silinməsi `member_has_open_assignments` 409 verir.

Project detail və member yazıları `draft` və `active` vəziyyətində mümkündür, `completed`/`archived` üçün service `project_read_only` conflict verir. Lifecycle ayrı sərhəddir: completed layihə active və ya archived edilə bilər. Task və əməkdaşlıq yazıları yalnız active layihədə açıqdır; entry policy bəzi qadağan vəziyyətləri service-ə çatmadan 403 ilə rədd edə bilər.

## Work item-lər

API compatibility səbəbilə məhsulun work item/issue anlayışı `/tasks` yolu və `Task` modeli ilə təqdim olunur.

Create input-u:

- `project_id`, `type`, `title`, `priority`;
- optional `parent_id`, `description`, `assignee_id`, `due_at`, `label_ids`.

Display key, reporter, issue number, initial `backlog`, rank, version və auto-watcher-lər server tərəfindən idarə olunur. `subtask` eyni layihədə standard parent tələb edir. Assignment `assignee_id: null|int` qəbul edir və tək assignee qaydasını saxlayır.

Status və rank yazısı serverin verdiyi `expected_version` tələb edir. Rank raw dəyər deyil, qonşu intent qəbul edir:

```json
{
  "before_task_id": 41,
  "after_task_id": 39,
  "expected_version": 3
}
```

Stale status/rank `task_version_conflict`, icazəsiz workflow keçidi `invalid_task_status_transition` kodlu 409-dur. Input shape problemi 422-dir. Açıq reorder manager-only-dir; assignee status keçidi etdikdə task target sütunun sonuna yerləşir.

Bu nümunədə `before_task_id: 41` əvvəl gələcək qonşu, `after_task_id: 39` isə sonra gələcək qonşudur: nəticə `41 → daşınan iş → 39` olur. İki qonşu verilirsə daşınan iş siyahıdan çıxarıldıqdan sonra yanaşı olmalıdırlar. Yalnız əvvəlki qonşu verilirsə ondan sonra, yalnız sonrakı verilirsə ondan əvvəl yerləşir; hər ikisi boşdursa sütunun sonuna keçir. Qonşular eyni project/status sütunundan olmalıdır; stale və ya uyğunsuz mövqe `invalid_task_rank_position` 409 verir.

## Task filter və sort

Canonical query input-ları:

- `search`, `project_id`, `types[]`, `statuses[]`, `priorities[]`;
- `assignee_id` (ədədi ID və ya explicit `unassigned`), `reporter_id`, `label_ids[]`, `parent_id` (`0` yalnız parent-i olmayan işlər);
- `due_before`, `due_after`, `overdue`, `sort`, `page`, `per_page`.

Arbitrary watcher filter yoxdur; cari user-in watched queue-su `/dashboard/watched` yolundadır. İcazəli signed sort-lar:

```text
number, -number, created_at, -created_at, updated_at, -updated_at,
due_at, -due_at, priority, -priority, status, -status, rank, -rank
```

`per_page` 1–100-dür. Unknown filter/sort SQL-ə ötürülmür və 422 qaytarır. Web adapter-in `q` input-u eyni DTO-da `search`-ə normallaşdırılır.

## Label, watcher və comment

Label project-scoped və mutation manager-only-dir. Task-a yalnız eyni layihənin label-ı bağlanır. Nested project/label mismatch 404-dür.

Watcher POST optional `user_id` qəbul edir; yoxdursa cari actor nəzərdə tutulur. Adi üzv yalnız öz watcher vəziyyətini, manager başqa aktiv layihə üzvünü idarə edə bilər. Əməliyyat idempotentdir.

Comment plain text, trim edilmiş, boş olmayan, maksimum 5 000 simvoldur. Müəllif öz, manager istənilən comment-i soft-delete edir. Cross-task nesting eyni safe 404 verir.

## Media

Upload `multipart/form-data` və `media[]` sahəsi ilə maksimum 5 fayl qəbul edir. Allowlist, 10 MB limit, MIME/extension uyğunluğu, DB batch transaction-u və xarici faylların kompensasiya/recovery sərhədi [`MEDIA.md`](../modules/MEDIA.md) daxilindədir. Resource disk, path, checksum və temporary storage məlumatı göstərmir.

Preview image/PDF üçün inline, digər icazəli formatlar üçün download fallback-ıdır. Download bütün allowlist üçün tam private stream-dir. HTTP Range/206 v1 müqaviləsində yoxdur. Parent Task authorize edilir, Media association həmin parent daxilində resolve olunur; cross-task ID eyni 404-dür.

Yoldakı `{media}` parametri raw Media ID-si deyil, parent task daxilində həll edilən `TaskAttachment` association ID-sidir (`TasksServiceProvider` binding-i). Cavabın `media` metadata-sı ayrıca UUID daşıyır. Silinmə əvvəl association+Activity-ni commit edir, sonra fiziki cleanup edir; cleanup xətası 204 uğur sayılmır və avtomatik retry yaratmır. Axın [transaction/xəta sənədində](TRANSACTIONS_AND_FAILURES.md) izah olunur.

## Activity və Dashboard

Activity filter-ləri: `event`, `project_id`, `task_id`, `actor_id`, `date_from`, `date_to`, `page`, `per_page`. ID-lər actor-visible scope daxilində resolve olunur. Web formadakı `project`, `task`, `actor` alias-ları yalnız presentation adapter-də canonical adlara çevrilir.

Dashboard summary `active_projects`, `completed_projects`, `archived_projects`, `total_tasks`, `overdue`, `completed_today`, altı top-level workflow sayı və status/type/project distribution-ları qaytarır. Enum açarları zero olduqda da canonical sırada mövcuddur. Bütün count və queue-lar list API ilə eyni visibility scope-dadır.

Eyni visibility scope eyni tarix şərti demək deyil: cari `dashboardSummaryFor()` overdue həddini bugünkü date ilə, `/dashboard/overdue`-un istifadə etdiyi `overdueQuery()` isə `now()` ilə hesablayır. Bugünkü date-only deadline sayğacda olmadan overdue siyahısına düşə bilər. Ümumi `/tasks?overdue=1` filter-i date sərhədindən istifadə edir. Bu mövcud fərq [Dashboard dərsində](../diagrams/flows/dashboard.md) kodla açılır; sənəd işi davranışı dəyişmir.

## Cavab formaları

Single resource:

```json
{"data":{"id":42,"number":"PAY-42","type":"bug","title":"Payment retry fails"}}
```

Pagination Laravel Resource `data`, `links`, `meta` formasıdır. Uğurlu delete/revoke body-siz 204-dür. Validation 422 field-error forması, gözlənilən domain conflict isə sabit code və təhlükəsiz message istifadə edir:

```json
{
  "message": "The member has open assignments.",
  "code": "member_has_open_assignments",
  "errors": {"user_id":["Reassign or unassign open work before removal."]}
}
```

## Status və isolation semantikası

| Status | Mənası |
|---:|---|
| 200 | Uğurlu read/update |
| 201 | Resource yaradılıb |
| 204 | Uğurlu delete/revoke, body yoxdur |
| 401 | Token yoxdur/yanlışdır/ləğv edilib və ya actor suspended-dir |
| 403 | Ability, permission və ya policy denial |
| 404 | Missing, görünməyən, soft-deleted və ya parent-scope mismatch |
| 409 | Sənədləşdirilmiş state/concurrency conflict |
| 422 | Input validation |
| 429 | Named rate limit |
| 500 | Gözlənilməz xəta üçün opaque production-safe cavab |

Ability denial record resolution-dan əvvəl 403 verir. Ability keçildikdən sonra missing, inaccessible və wrong-parent ID-lər eyni `resource_not_found` 404 formasını alır. Filter ID-ləri actor-visible scope daxilində həll edilir; inaccessible və nonexistent ID arasında existence oracle yaranmır.

Controller Resource qaytarır, Eloquent/repository çağırmır və presentation üçün relation yükləmir. Query/use-case service tam nəticəni hazırlayır; Web/API/Livewire mutation-ları eyni domain service qaydalarını istifadə edir.
