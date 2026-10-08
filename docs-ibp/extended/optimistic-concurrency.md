# `version` və `expected_version`: köhnə məlumatla yazma

## Sual

İki istifadəçi eyni task-ı açıb status dəyişəndə ikinci dəyişiklik birincini əzməsin deyə nə edirik?

## Sadə cavab

Server task-ın cari `version`-unu saxlayır. Client “mən version=3 görmüşdüm” deyə `expected_version=3` göndərir. Server artıq version=4-dürsə köhnə request-i conflict ilə rədd edir. Bu, optimistic concurrency yoxlamasıdır.

## Cari vəziyyət

TaskFlow status və rank yazısında `expected_version` tələb edir. Bu mexanizmi bütün task update-lərində və ya bütün entity-lərdə avtomatik mövcud saymaq olmaz. Version request-in öz seçdiyi yeni versiya deyil.

## Terminləri sadələşdirək

**Concurrency** birdən çox request-in eyni məlumatla işləməsidir. **Stale/köhnəlmiş input** istifadəçinin baxdığı məlumatdan sonra serverdə dəyişiklik olmasıdır. **Conflict** request-in formatı düzgün olsa da həmin köhnə gözlənti ilə write-ın qəbul edilməməsidir.

İki nəfər eyni cədvəlin kağız surətini götürüb dəyişiklik hazırlayırsa ikinci adam birincinin düzəlişini görmür. Version “mən hansı nüsxədən qərar vermişdim?” məlumatıdır. Server bunu cari nüsxə ilə müqayisə edir.

## Həyat ssenarisi: iki açıq browser tab-ı

1. Əvvəl task review statusunda, version=3-dür.
2. Əhməd və manager Fidan eyni task-ı açır; ikisi də version=3 alır.
3. Əhməd icazəli keçidlə task-ı in_progress edir və expected_version=3 göndərir.
4. Server write edir, version=4 olur.
5. Fidan hələ köhnə ekrandan done request-i expected_version=3 ilə göndərir.
6. Server artıq 4 gördüyünə görə ikinci write-ı rədd edir; birinci dəyişiklik əzilmir.
7. Fidan refresh edir, yeni state-i görür, sonra qərar verir.

```text
Əvvəl: hər iki client 3 görür
İlk write: DB 3 → 4
Köhnə write: expected 3 ≠ actual 4 → conflict
Sonra: DB-də ilk write saxlanır
```

## Real kod

Status service transaction daxilində təzə state-i lock ilə alır:

```php
$task = $this->tasks->lockForRankMutation($task);
if ($task->version !== $data->expectedVersion) {
    throw new TaskVersionConflict('This task was changed by another request.');
}
```

Uğurlu status write:

```php
$task->status = $data->status;
$task->version++;
$task = $this->ranks->placeAtEnd($task);
```

## İki browser nümunəsi

```text
Əhməd GET → version=3
Fidan GET → version=3
Əhməd PATCH todo, expected_version=3 → uğurlu → version=4
Fidan PATCH cancelled, expected_version=3 → 409 task_version_conflict
Fidan yenidən oxuyur → yeni state-i görüb qərar verir
```

## Lock və version niyə birlikdədir?

Version köhnə niyyəti aşkarlayır; transaction/row lock paralel yazı zamanı yoxlama və persistence arasındakı race-i qoruyur. “Optimistic” burada heç bir lock istifadə edilmir demək deyil. Eyni use case sütun rank bütövlüyünü də qoruyur.

Conflict zamanı client səssizcə son versiyanı götürüb eyni action-ı təkrar göndərməməlidir. İstifadəçi artıq dəyişmiş state-i görməlidir. Həmçinin version uyğunluğu authorization və workflow qaydalarını bypass etmir.

## Kodun vacib sətirlərini açaq

- `lockForRankMutation($task)`: route-dan gəlmiş köhnə obyektlə kifayətlənmir; repository real DB state-i lock ilə alır.
- `$task->version`: serverin hazırkı rəqəmidir.
- `$data->expectedVersion`: caller-in oxuduğunu bildirdiyi rəqəmdir.
- `!==`: rəqəmlər uyğun deyilsə write davam etmir.
- `throw new TaskVersionConflict(...)`: məqsədli conflict-dir, generic DB exception deyil.
- `$task->version++`: uğurlu dəyişiklikdən sonra növbəti caller artıq yeni rəqəm görür.

Client `expected_version=999` göndərməklə serverin versiyasını 999 etmir. Bu sahə yazılacaq yeni dəyər deyil, müqayisə gözləntisidir.

## Niyə avtomatik təkrar göndərmirik?

İstifadəçi əvvəlki state-ə baxaraq qərar verib. Son versiyanı səssiz götürüb həmin action-ı yenidən göndərmək onun artıq dəyişmiş vəziyyətdə eyni qərarı verəcəyini fərz etməkdir. UI təhlükəsiz conflict göstərib cari məlumatı oxutmalıdır.

## Konkret failure nümunəsi

Fidanın version-u təzədir, amma o assignee və manager deyil. Version uyğunluğu ona access vermir; policy rədd edə bilər. Eyni zamanda actor icazəlidir, amma transition qadağandırsa service yenə rədd edir.

Bu mexanizm hazırda status və rank üçün müqayisə olunur. Detail və assignment yazıları version-u artıra bilər, amma eyni universal expected_version müqaviləsi yoxdur. “Task-da version var, bütün write-lar optimistic qorunur” demə.

## Özünü yoxla

1. Expected version serverin yeni versiyasıdırmı? **Xeyr; client-in bildiyi versiyadır.**
2. Conflict input formatının mütləq yanlış olmasıdırmı? **Xeyr; state köhnəlmiş ola bilər.**
3. Optimistic yoxlama lock istifadəsini qadağan edirmi? **Xeyr; cari use case ikisini birlikdə istifadə edir.**

[Status diagramının izahı](../diagrams/flows/status.md) və [reorder addımları](../diagrams/flows/reorder.md) conflict-in harada dayandığını göstərir.

## Kod və yoxlama

- [Status service](../../Modules/Tasks/app/Services/TaskStatusService.php)
- [Rank service](../../Modules/Tasks/app/Services/TaskRankService.php)
- [Input DTO](../../Modules/Tasks/app/Data/ChangeTaskStatusData.php)
- [Workflow conflict testləri](../../Modules/Tasks/tests/Feature/TaskWorkflowTest.php)
- [Rank conflict testləri](../../Modules/Tasks/tests/Feature/TaskRankTest.php)
- [API conflict kodları](../technical/API.md)
