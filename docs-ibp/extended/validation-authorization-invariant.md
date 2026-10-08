# Validation, authorization və invariant

## Sual

`status=done` düzgündürsə niyə server yenə əməliyyatı rədd edə bilər?

## Sadə cavab

Validation “input hansı formadadır?”, authorization “bu actor bunu edə bilərmi?”, invariant isə “bu vəziyyətdə bu dəyişiklik düzgündürmü?” sualına cavab verir. Birinin keçməsi digərlərini əvəz etmir.

## Cari vəziyyət

Məhsul HTTP girişində FormRequest, controller-də Policy/Gate, service-də state qaydası istifadə edir. R1 HTTP-siz laboratoriyada title input qaydası readonly DTO constructor-undadır.

## Terminləri sadələşdirək

**Input shape** məlumatın tipi/formasıdır: integer, enum, boş olmayan mətn. **Actor** əməliyyatı edən istifadəçidir. **Invariant** sistemin istənilən girişdə qorumağa çalışdığı biznes qaydasıdır, məsələn “açıq subtask olan parent done edilmir”.

İmtahan misalında düzgün formatda blank doldurmaq validation-dır. İmtahana giriş icazəsinin olması authorization-dur. Həmin imtahanın bağlanmış sessiyasına cavab göndərməmək state qaydasıdır. Birinci yoxlamanın keçməsi qalanlarını avtomatik həll etmir.

## Həyat ssenarisi: üç fərqli rədd cavabı

1. Əvvəl Rauf task statusunu dəyişmək istəyir.
2. `status=banana` göndərir: dəyər enum-da yoxdur, input yanlışdır.
3. `status=done` göndərir, amma nə manager, nə assignee-dir: actor icazəli deyil.
4. Manager düzgün `done` göndərir, amma task-ın açıq subtask-ı var: biznes state-i icazə vermir.
5. Açıq subtask-lar həll edilir, version təzədir və keçid icazəlidir.
6. Yalnız bundan sonra mutation və onun side effect-ləri tamamlanır.

```text
Format düzgündür? → Actor icazəlidir? → Cari state bu dəyişikliyə uyğundur? → Write
```

Bu, anlayışları ayıran sxemdir. Runtime-da middleware/binding bəzi validation addımlarından əvvəl safe 404/403 qaytara bilər.

## Real üç qat

FormRequest enum və version shape-ni yoxlayır:

```php
return ['status' => ['required', Rule::enum(TaskStatus::class)], 'expected_version' => ['required', 'integer', 'min:1']];
```

Controller actor-u yoxlayır:

```php
$this->authorize('changeStatus', $task);
```

Service real state-i yoxlayır:

```php
if ($data->status === TaskStatus::Done && $this->tasks->hasOpenSubtasks($task)) {
    throw new InvalidTaskStatusTransition('A task with open subtasks cannot be completed.');
}
```

`done` enum dəyəridir, amma açıq child olan parent-in tamamlanması biznes baxımından qadağandır.

## Axın

```text
Yanlış status string → validation → 422
Actor nə manager, nə assignee → policy → 403
İcazəli actor + açıq subtask → service invariant → sənədləşdirilmiş conflict
İcazəli actor + düzgün state/version → transaction → mutation
```

Gizli/missing resource scope həllində safe 404 də ala bilər; bu nümunə bütün request mərhələlərinin sərt sıra zəmanəti deyil.

## Yanlış yanaşma

FormRequest-də `authorize(): true` olması route-un hamıya açıq olması demək deyil: controller policy çağırır. Eyni zamanda service çağırmaq entry authorization-u avtomatik əvəz etmir. Service state qaydalarını caller-dən asılı olmadan saxlamalıdır, HTTP adapteri isə öz access yoxlamasını etməlidir.

## Kodun vacib sətirlərini açaq

- `'required'`: həmin sahə tələb olunur.
- `Rule::enum(TaskStatus::class)`: yalnız tanınan status dəyərləri qəbul edilir.
- `'integer', 'min:1'`: version-un formasıdır, onun DB-də cari version-la eyni olduğunu sübut etmir.
- `authorize('changeStatus', $task)`: konkret actor/record üzrə policy qərarıdır.
- `hasOpenSubtasks($task)`: DB state-i üzərində biznes yoxlamasıdır.
- `throw new InvalidTaskStatusTransition(...)`: qadağan state üçün məqsədli exception-dur.

## Niyə yoxlamaları ayırırıq?

FormRequest HTTP input-u tanıyır. Service isə HTTP-dən kənar çağırıla bilər. Child və lifecycle qaydası yalnız request-də olsa direct service caller onu bypass edər. Əksinə, yalnız service state yoxlaması var deyə endpoint-in access yoxlamasını çıxarmaq da olmaz.

Cari kodda project key və admin email üçün bəzi FormRequest `unique` rule-ları DB presence query-si edir. Bu mövcud istisna [təhlükəsizlik sənədində](../technical/SECURITY.md) qeyd olunub; bütün domain query-ləri request-ə daşımaq tövsiyəsi deyil.

## Konkret yanlış düzəliş

Junior açıq child yoxlamasını yalnız UI button-u gizlətməklə həll edir. API və ya əl ilə göndərilmiş request yenə cəhd edə bilər. Backend service invariantı qalmalıdır. UI istifadəçiyə istiqamət verir, təhlükəsizlik/state sərhədi deyil.

Başqa risk `authorize(): true` görüb policy yoxdur zənn etməkdir. Bu request shape-ni yoxlayır; controller-in ayrıca authorization çağırışını da oxumaq lazımdır.

## Özünü yoxla

1. `min:1` version-un təzə olduğunu yoxlayırmı? **Xeyr; service cari version-la müqayisə edir.**
2. Gizli düymə backend qaydasıdırmı? **Xeyr.**
3. Service invariantı endpoint authorization-u əvəz edirmi? **Xeyr; ikisi də öz məqsədinə xidmət edir.**

[Authorization diagramının izahı](../diagrams/flows/authorization.md) ilə [subtask flow-unu](../diagrams/flows/subtask.md) yanaşı oxu.

## Kod və yoxlama

- [FormRequest](../../Modules/Tasks/app/Http/Requests/ChangeTaskStatusRequest.php)
- [Controller](../../Modules/Tasks/app/Http/Controllers/Api/V1/TaskController.php)
- [Policy](../../Modules/Tasks/app/Policies/TaskPolicy.php)
- [Status service](../../Modules/Tasks/app/Services/TaskStatusService.php)
- [Workflow testləri](../../Modules/Tasks/tests/Feature/TaskWorkflowTest.php)
- [Error müqaviləsi](../technical/API.md)
