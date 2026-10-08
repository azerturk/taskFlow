# HTTP xəta kodlarını necə oxumalı?

## Bu dərs nə üçündür?

Request uğursuz olanda ilk sual «kod xarabdır?» yox, «server hansı mərhələdə dayanıb?» olmalıdır. Yanlış input, icazəsizlik, köhnəlmiş versiya və gözlənilməz server xətası fərqli problemlərdir; eyni düzəlişlə həll olunmurlar.

Bu səhifə cari TaskFlow REST API-sini izah edir. Adlar, ID-lər və versiyalar öyrətmək üçün seçilmiş nümunələrdir; real istifadəçi və database report-u deyil.

## Əvvəl terminləri anlayaq

- **HTTP status** cavabın nəticə növünü göstərən rəqəmdir: məsələn, `404`.
- **Authentication** «bu request-i kim göndərir?» sualıdır; API-də Sanctum token-i istifadə olunur.
- **Authorization** «həmin istifadəçi bu işi edə bilərmi?» sualıdır; token ability-si və policy eyni şey deyil.
- **Validation** input-un qəbul edilən formada və dəyərdə olmasını yoxlayır.
- **Conflict** input başa düşülsə də cari vəziyyətlə ziddiyyət olmasıdır.
- **Exception** PHP kodunun normal nəticə əvəzinə xəta bildirməsidir. Exception-un texniki mətni istifadəçiyə göndəriləcək mətn olmaq məcburiyyətində deyil.
- **Opaque cavab** daxili database, private path və secret-i açıqlamayan təhlükəsiz cavabdır.

## Yeddi status, yeddi fərqli sual

| Status | TaskFlow-da əsas mənası | Junior əvvəl nəyi yoxlamalıdır? |
|---|---|---|
| `401` | Qorunan request authenticate olmayıb və ya actor suspended-dir | Token yoxdur, ləğv olunub, yanlışdır, yaxud hesab aktiv deyil? |
| `403` | Ability və ya əməliyyat icazəsi çatmır | Düzgün token ability-si və user policy hüququ varmı? |
| `404` | Bu context-də göstərilə bilən resource tapılmır | ID, parent və actor-un görünürlük sərhədi uyğundurmu? |
| `409` | Sənədləşdirilmiş state/concurrency ziddiyyəti var | Cari statusu, icazəli keçidi və versiyanı yenidən oxudunmu? |
| `422` | Input və ya məqsədli domain validation qəbul edilmir | `errors` daxilində göstərilən sahəni düzəltdinmi? |
| `429` | Named rate limit dolub | Request təkrarını dayandırıb limit pəncərəsinin açılmasını gözlədinmi? |
| `500` | Gözlənilməz server failure-i var | Təhlükəsiz məlumatla araşdırmaq və əməliyyat nəticəsini yoxlamaq lazımdır |

Bu cədvəl bütün xətalara eyni JSON formasının verildiyini demir. Gözlənilən domain conflict-lərin sabit `code`-u var; framework validation cavabında isə sahələr üzrə `errors` oxunur.

## Həyat ssenarisi: Ayselin səhifəsi köhnəlib

Aysel active layihənin manager-idir, token-də `tasks:write` var. O, task-ın `version=3` olduğu səhifəni açıb. Arada başqa icazəli mutation server versiyasını `4` edir.

1. Aysel status dəyişikliyini `expected_version=3` ilə göndərir.
2. Authentication, active-user və ability yoxlamaları keçir.
3. Actor üçün görünən task binding ilə tapılır; input validation və policy də keçir.
4. `TaskStatusService` transaction daxilində cari task-ı kilidləyərək yenidən oxuyur.
5. Serverdəki `4` göndərilən `3`-ə bərabər deyil; `TaskVersionConflict` atılır.
6. HTTP mapper təhlükəsiz `409` və `task_version_conflict` qaytarır.
7. Bu status request-i status/rank/version yazısına çatmır. Aradakı digər request-in təsdiqlənmiş dəyişiklikləri isə qalır.

Aysel həmin köhnə request-i təkrar-təkrar göndərməməlidir. Task-ı yenidən oxumalı, yeni vəziyyəti anlamalı və hələ uyğun olan əməliyyatı cari versiya ilə seçməlidir.

## Real kod: konflikt yazıdan əvvəl tutulur

[TaskStatusService::change()](../../Modules/Tasks/app/Services/TaskStatusService.php) daxilindən:

```php
$task = $this->tasks->lockForRankMutation($task);
if ($task->version !== $data->expectedVersion) {
    throw new TaskVersionConflict('This task was changed by another request.');
}
```

- Birinci sətir köhnə ekran obyektinə güvənmək əvəzinə cari persistence vəziyyətini kilidləyərək alır.
- `!==` server versiyası ilə request-in gözlədiyi versiyanı müqayisə edir.
- `throw` normal axını dayandırır; aşağıdakı status, rank, Activity və notification yazıları bu request üçün başlamır.
- Bu qayda `ChangeTaskStatusRequest`-in integer yoxlamasını əvəz etmir: düzgün rəqəm də köhnəlmiş ola bilər.

[bootstrap/app.php](../../bootstrap/app.php) həmin exception üçün bu JSON-u qurur:

```php
return response()->json([
    'message' => 'The task was changed by another request.',
    'code' => 'task_version_conflict',
    'errors' => ['expected_version' => ['Refresh the task and try again.']],
], 409);
```

`message` insana qısa izahdır. `code` client-in sabit problem növünü tanımasına kömək edir. `errors.expected_version` hansı input-la bağlı addım lazım olduğunu göstərir. `409` isə HTTP nəticəsidir; bu dörd anlayış eyni sahə deyil.

## 422 ilə 409 niyə fərqlidir?

[ChangeTaskStatusRequest](../../Modules/Tasks/app/Http/Requests/ChangeTaskStatusRequest.php) `status` üçün enum, `expected_version` üçün required/integer/min:1 qaydası istifadə edir.

- `status=unknown` və ya `expected_version`-un olmaması input problemidir: `422`.
- `status=review` tanınmış enum dəyəridir, amma cari `backlog -> review` keçidi icazəli deyil: service-ə çatan bu ssenari `409 invalid_task_status_transition` verir.
- `status=todo`, integer `expected_version=3` düzgün formadadır; server versiyası `4` olduqda yenə `409` alınır.

Deməli, «enum-da mövcuddur» ilə «bu iş indi həmin statusa keçə bilər» ayrı yoxlamalardır. Service invariantı bütün caller-lər üçün saxlayır, Form Request isə HTTP input formasını qoruyur.

## 401, 403 və safe 404-ü qarışdırma

Token olmadan qorunan API-yə daxil olmaq `401`-dir. Etibarlı token-in yalnız `projects:read` ability-si varsa `/api/v1/tasks/{task}` üçün `403` alır; binding hələ task-ın mövcudluğunu araşdırmır.

Token-də `tasks:read` varsa, amma task actor-a görünmürsə binding safe `404` qaytarır. Mövcud olmayan ID də eyni təhlükəsiz cavabı alır. Server «bu gizli task var, amma sən baxa bilməzsən» detalını açıqlamır.

Task görünür, amma user konkret mutation-u edə bilmirsə policy `403` verə bilər. Ona görə hər read-only layihə request-inin mütləq `409` olacağını gözləmə: policy service-dən əvvəl rədd edə bilər.

Vacib istisna: `POST /api/v1/auth/token`-da yanlış və suspended credential-lar enumeration yaratmayan `422` cavabı alır. Bunu qorunan endpoint-də artıq mövcud token-in `401` nəticəsi ilə qarışdırma.

## 429 və 500-dən sonra nə etməli?

Search limiti `30/dəqiqə`, upload limiti `10/dəqiqə`, adi API limiti `120/dəqiqə`-dir. Invalid upload belə upload bucket-ini istehlak edə bilər: təhlükəsizlik testi bunu ayrıca yoxlayır. «422 aldım, deməli limit sayılmadı» düzgün deyil.

`500` zamanı generic exception-un database/storage mesajını response-a kopyalama. `RequestBoundarySecurityTest` `app.debug=false` ilə daxili mesajın cavabda görünmədiyini yoxlayır. Lokal debug ekranını production təhlükəsizlik zəmanəti sayma.

Həm də `500` «heç nə dəyişməyib» demək deyil. Media delete-də association artıq commit edilmiş, sonrakı fiziki cleanup isə fail olmuş ola bilər. R1-də commit-dən sonrakı listener failure-i source entry-ni geri qaytarmır. Kor-koranə retry əvəzinə use case-in transaction sərhədini və cari nəticəni yoxla.

## Web-də eyni görünəcəkmi?

Həmişə yox. API route-ları və JSON gözləyən request-lər JSON alır. Məsələn, adi Web status conflict mapper-i geri redirect edib flash error göstərir; suspended Web actor logout edilib login səhifəsinə yönləndirilir. Domain qayda ortaqdır, təqdimat forması adapterə görə dəyişir.

## Özünü yoxla

1. Düzgün integer version həmişə qəbul edilirmi? **Xeyr; serverdəki versiyadan köhnə ola bilər.**
2. `tasks:write` ability-si policy-ni əvəz edirmi? **Xeyr.**
3. Hər gizli task üçün `403` verilir? **Xeyr; admitted read binding-i safe `404` verir.**
4. Token issuance-da yanlış parol mütləq `401`-dir? **Xeyr; cari endpoint `422` istifadə edir.**
5. `500` bütün əvvəlki commit-ləri rollback edirmi? **Xeyr.**

## Source, test və davamı

- [HTTP API müqaviləsi](../technical/API.md), [təhlükəsizlik müqaviləsi](../technical/SECURITY.md)
- [Authorization axınının sadə izahı](../diagrams/flows/authorization.md), [status axını](../diagrams/flows/status.md)
- [Nested resource və safe 404 dərsi](nested-resources-and-safe-404.md)
- [Exception mapping](../../bootstrap/app.php), [active-user middleware](../../app/Http/Middleware/EnsureActiveUser.php)
- [Ability, safe 404, limit və opaque 500 testləri](../../tests/Feature/Security/RequestBoundarySecurityTest.php)
- [Stale status Web/API testi](../../Modules/Tasks/tests/Feature/TaskBoardTest.php), [token rejection testləri](../../tests/Feature/Auth/CredentialTokenApiTest.php)
- [Transaction və failure sərhədləri](../technical/TRANSACTIONS_AND_FAILURES.md)
