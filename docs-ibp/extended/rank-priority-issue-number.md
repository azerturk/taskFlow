# Rank, priority və issue number

## Sual

`PAY-42`, `urgent` və `rank` niyə ayrı sahələrdir?

## Sadə cavab

Issue number identity-ni, priority vacibliyi, rank isə konkret sütunda yeri göstərir. Bir task-ı yuxarı çəkmək onun nömrəsini və ya vacibliyini dəyişmək deyil.

## Cari vəziyyət

TaskFlow project-local issue sequence, fixed priority enum və project/status daxilində server-owned rank istifadə edir. İlkin status/rank/issue number client tərəfindən seçilmir. Açıq reorder manager-only-dir.

## Terminləri sadələşdirək

**Identity** record-un kimliyidir: `PAY-42` həmin işin göstərilən açarıdır. **Priority** vaciblik səviyyəsidir. **Rank** isə eyni project/status sütununda yerini göstərən serverin idarə etdiyi ədədi dəyərdir. **Rebalance** sıralamanı təhlükəsiz yeni rank dəyərləri ilə yenidən yerləşdirməkdir.

Kitabxanada kitabın inventar nömrəsi, oxucunun təcili ehtiyacı və rəfdəki yeri ayrı anlayışdır. Kitabı başqa rəfə qoymaq inventar nömrəsini dəyişməz. Task board-u da identity ilə list yerini qarışdırmır.

## Həyat ssenarisi: urgent işi sona yerləşdirmək

1. Əvvəl backlog sütununda A və B task-ları var.
2. Əhməd urgent priority ilə yeni bug yaradır.
3. Server project sequence-dən yeni issue number ayırır, ilkin statusu backlog edir.
4. Yeni task backlog-un sonuna server rank ilə yerləşir; urgent olması avtomatik birinci yer demək deyil.
5. Manager istəsə qonşu intent ilə onu A və B arasına reorder edir.
6. Sonra issue key və priority eyni qalır, sütundakı yeri dəyişir.

```text
Əvvəl: A → B
Create: A → B → yeni urgent task
Manager reorder: A → yeni urgent task → B
Identity və priority: dəyişməyib
```

## Real kod

Create use case issue identity-ni serverdən alır:

```php
$allocatedIssue = $this->projects->allocateIssueNumber($project);
```

```php
'number' => $allocatedIssue->displayKey,
'issue_number' => $allocatedIssue->issueNumber,
'version' => 1,
```

İlkin status və sıralama:

```php
'title' => $data->title, 'description' => $data->description, 'status' => TaskStatus::Backlog,
```

```php
$task = $this->ranks->placeAtEnd($task);
```

## Nümunə və axın

```text
PAY-42: priority=urgent, status=todo, rank=sütundakı yer
PAY-42 status review oldu → review sonuna append, identity eyni
Manager reorder etdi → yeni yer, identity və priority eyni
```

Reorder request-i raw rank yox, `before_task_id`, `after_task_id`, `expected_version` intent-i göndərir. Server qonşuların eyni project/status kontekstini yoxlayır, lock/rebalance tətbiq edir. Rank dəyərini client-in özü seçməsi qəbul edilmiş müqavilə deyil.

## Yanlış yanaşma

Issue nömrəsini list sırası kimi istifadə etmə. Silinmiş və ya başqa statusa keçmiş task nömrəsi yenidən sıralama üçün dəyişdirilmir. `urgent` də avtomatik board-da birinci yer demək deyil: priority sort ilə explicit board rank fərqli məqsəddir.

## Kodun vacib sətirlərini açaq

- `allocateIssueNumber($project)`: issue sequence Projects sərhədində server tərəfindən ayrılır.
- `displayKey`: insanın gördüyü project key + lokal nömrədir.
- `issueNumber`: project daxilində numeric sequence-dir; raw task primary key ilə eyni deyil.
- `version => 1`: yeni task-ın başlanğıc concurrency versiyasıdır.
- `TaskStatus::Backlog`: client-in ilkin statusu seçməsinə yol verilmir.
- `placeAtEnd($task)`: hansı sütunun sonuna hansı rank-la yerləşəcəyini backend hesablayır.

Reorder-də `before_task_id` əvvəlki, `after_task_id` sonrakı qonşunu bildirir. Bu adları “mən onun qabağına qoyuram” kimi tərsinə oxumamaq üçün [reorder nümunəsinə](../diagrams/flows/reorder.md) bax.

## Niyə raw rank qəbul etmirik?

Client birbaşa rank seçsə başqa record-un rank-ı ilə collision yarada bilər. Server eyni sütun, qonşu adjacency, lock və reserved soft-deleted rank-ları nəzərə alır. Request niyyəti bildirir; final persistence dəyərini server seçir.

## Konkret failure nümunəsi

Manager PAY todo task-ını WEB todo qonşuları arasına qoymaq istəyir. Hər ikisinin status adı eyni olsa da project fərqlidir; request rədd edilir. Eyni project-də task-lar bitişik qonşu deyilsə də intent etibarsızdır.

Project key barədə ayrıca məhdudiyyət: cari guard yalnız soft-delete olunmamış task-ları görür; bütün task-lar soft-delete edilibsə key dəyişməsi həmişə bloklanmır. Buna görə display identity qaydasını kodun bu riskini gizlədən qüsursuz zəmanət kimi təqdim etmirik; [Projects məhdudiyyətində](../modules/PROJECTS.md) izah olunub.

## Özünü yoxla

1. Urgent task avtomatik board-un birinci yerinə gəlirmi? **Xeyr.**
2. Reorder issue number-u dəyişirmi? **Xeyr.**
3. Assignee status append etməklə ümumi reorder səlahiyyəti alırmı? **Xeyr.**

[Task create](../diagrams/flows/task-create.md), [reorder](../diagrams/flows/reorder.md) və [project lifecycle](../diagrams/flows/project.md) izahlarını birlikdə oxu.

## Kod və yoxlama

- [Create service](../../Modules/Tasks/app/Services/TaskService.php)
- [Priority enum](../../Modules/Tasks/app/Enums/TaskPriority.php)
- [Rank service](../../Modules/Tasks/app/Services/TaskRankService.php)
- [Project-local allocation testləri](../../Modules/Tasks/tests/Feature/ProjectLocalIssueAllocationTest.php)
- [Rank testləri](../../Modules/Tasks/tests/Feature/TaskRankTest.php)
- [API reorder input-u](../technical/API.md)
