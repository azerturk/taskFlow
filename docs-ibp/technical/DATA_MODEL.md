# Verilənlər modeli

## Ümumi prinsiplər

- Bütün domain ID-ləri daxili bigint primary key-dir; API yalnız explicit Resource sahələrini göstərir.
- Tarixi əsas record-lar soft delete ilə qorunur; association və framework cədvəlləri ehtiyaca görə hard delete/cascade istifadə edir.
- Cross-module foreign key-lər modular monolith daxilində icazəlidir.
- Enum dəyərləri tətbiqdə PHP enum, sxemdə string kimi saxlanılır və servis/validasiya ilə allowlist olunur.
- Migration-lar həm SQLite, həm MySQL fresh/rollback gate-dən keçir.

## Əsas entity-lər

### `users`

Əsas sahələr: `id`, `name`, unikal `email`, `email_verified_at`, hash `password`, `status`, remember token və timestamps.

Əlaqələr: qlobal Spatie roles/permissions, owned projects, project memberships, reported/assigned tasks, comments, uploaded media, watchers, personal access tokens, sessions, notifications və Activity actor.

`status`: `active`, `suspended`.

### `projects`

Sahələr: `id`, `name`, unikal `slug`, unikal `key`, `description`, `status`, `owner_id`, `starts_at`, `due_at`, `next_issue_number`, timestamps, soft delete.

Constraint və indekslər:

- `key` və `slug` unikaldır;
- owner foreign key user silinməsinə `restrict` edir;
- owner/status, status/due və key/sequence query indeksləri var.

### `project_members`

Sahələr: `project_id`, `user_id`, `member_role`, `joined_at`, timestamps.

`project_id + user_id` unikaldır. Project silinməsi association-u cascade, user silinməsi restrict edir. `member_role`: `manager`, `member`.

### `tasks`

Sahələr:

- identity: `id`, persisted display `number`, `project_id`, project-local `issue_number`, `version`;
- ownership: `creator_id`, nullable `assignee_id`;
- hierarchy: nullable self-FK `parent_id`, `type`;
- content/state: `title`, `description`, `status`, `priority`, `rank`, `due_at`, `started_at`, `completed_at`;
- timestamps və soft delete.

Əsas constraint-lər:

- `number` unikaldır;
- `project_id + issue_number` unikaldır;
- `project_id + status + rank` unikaldır;
- project/reporter/assignee/parent foreign key-ləri tarixi əlaqəni qorumaq üçün restrict edir;
- filter üçün project/status, project/assignee, project/reporter, due və digər indekslər var.

### `task_comments`

`task_id`, `user_id`, plain-text `body`, timestamps, soft delete. Task və user silinməsi restrict; task/date və user/date indeksləri var.

### `task_labels` və `task_label`

Label: `project_id`, `name`, `slug`, `color`, timestamps. `project_id + name` və `project_id + slug` unikaldır.

Pivot: `task_id + task_label_id` composite primary key-dir. Hər iki parent silinəndə association cascade olunur. Service label və task project-lərinin eyni olmasını məcbur edir.

### `task_watchers`

`task_id`, `user_id`, timestamps; cütlük unikaldır. Task silinməsi cascade, user silinməsi restrict. Yalnız aktiv project participant service tərəfindən yazılır.

### `media`

Sahələr: public-safe unikal `uuid`, `uploaded_by`, `disk`, unikal random `path`, `original_name`, `extension`, server-detected `mime_type`, `size`, `sha256`, nullable image width/height, timestamps, soft delete.

`disk`, `path`, `sha256` presentation contract-a çıxmır. `uploaded_by` user silinməsinə restrict edir.

### `task_attachments`

Tasks moduluna məxsus explicit association-dır: `id`, `task_id`, required və unikal `media_id`, timestamps. Fiziki metadata burada saxlanmır. Task və Media foreign key-ləri restrict edir; bir Media hazırda ən çox bir Task attachment-a bağlıdır.

### `activity_log`

Spatie Activitylog sxemidir. Activity modulu event enum-u, actor/subject, batch/log məlumatı və sanitizasiya edilmiş `properties` JSON contract-ını idarə edir. Scope query-ləri subject metadata-sına güvənərək access vermir; Projects/Tasks görünürlüyünü tətbiq edir.

### `notifications`

Laravel database notification cədvəlidir: UUID identity, notifiable polymorphic əlaqə, safe JSON `data`, `read_at`, timestamps. Notification payload private path, credential və token saxlamır.

### Authentication və framework cədvəlləri

- `personal_access_tokens` Sanctum token hash və ability-lərini saxlayır; plaintext saxlanmır.
- `sessions`, `password_reset_tokens`, Spatie role/permission cədvəlləri host-a məxsusdur.
- `cache`, `jobs`, failed job və batch cədvəlləri Laravel infrastrukturu üçündür.

### Migration report cədvəli

`task_number_migration_reports` inherited task nömrəsinin project-local display key-ə backfill xəritəsini saxlayan migration-aid cədvəlidir.

## Əlaqə xülasəsi

```text
User 1---* Project(owner)
User *---* Project(project_members)
Project 1---* Task
Task 0..1---* Task(parent/subtask)
User 1---* Task(reporter)
User 0..1---* Task(assignee)
Task 1---* Comment
Task *---* Label(task_label)
Task *---* User(task_watchers)
Task 1---* TaskAttachment 1---1 Media
User 1---* Media(uploaded_by)
```

## Məlumat qorunması

- Hesab suspend və üzvlük silinməsi tarixi reporter/assignee/audit foreign key-lərini pozmur.
- Work item və comment silinməsi soft delete-dir.
- Project lifecycle read-only davranışdır; avtomatik record deletion deyil.
- Media metadata yalnız fiziki silinmə təsdiqindən sonra soft-delete olunur; cleanup alınmasa retry üçün aktiv, unassociated record qalır.

