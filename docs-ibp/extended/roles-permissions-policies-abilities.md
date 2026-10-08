# Rol, permission, policy və token ability

## Sual

Token-də `tasks:write` varsa niyə istənilən task-ı dəyişə bilmirəm?

## Sadə cavab

Token ability-si “bu token hansı əməliyyat ailəsini sınaya bilər?” deyir. Permission ümumi capability-dir. Policy konkret record və actor kontekstinə baxır. Qlobal rol ilə layihə rolu isə müxtəlif səviyyələrin roludur.

## Cari vəziyyət

TaskFlow bir təşkilatlıdır, amma konkret layihə üzvlüyü və rolu access-i daraldır. `member` qlobal rolundakı istifadəçi müəyyən layihədə `manager` ola bilər. `project_manager` qlobal rolu da avtomatik bütün layihələrdə manager deyil.

## Terminləri sadələşdirək

**Qlobal rol** istifadəçinin təşkilat səviyyəli roludur. **Layihə rolu** yalnız konkret layihədə manager/member olmasını göstərir. **Permission** ümumi əməliyyat capability-sidir. **Policy** “bu istifadəçi bu record-da bunu edə bilərmi?” qərarıdır. **Ability** isə konkret API token-in icazəli əməliyyat ailəsidir.

Şirkət əməkdaşının ümumi vəzifəsi onun istənilən otağa girə bilməsi demək deyil. Bir layihədə komanda rəhbəri olması da başqa layihədə rəhbər olduğu mənasına gəlmir. Token-i onun yanında gəzdirdiyi məhdud giriş kartı kimi düşün: kart actor-un əsl hüququnu artıra bilməz.

## Həyat ssenarisi: iki layihədə fərqli rol

1. Əvvəl Leylanın qlobal rolu `member`-dir.
2. PAY layihəsində layihə rolu `manager`, WEB layihəsində `member`-dir.
3. PAY task-ını reorder etmək istəyirsə uyğun permission, aktiv layihə və manager konteksti yoxlanılır.
4. WEB task-ını reorder etmək istəyirsə qlobal rolu eyni qalsa da konkret layihə manager-i olmadığı üçün rədd edilə bilər.
5. API token-də `tasks:write` olması həmin WEB manager qərarını dəyişmir.
6. Read-only token isə actor manager olsa da write route ailəsinə giriş vermir.

```text
Actor-un capability-si + konkret layihə/record konteksti + token məhdudiyyəti
→ yalnız hamısı uyğun olan əməliyyat
```

## Real kod

API status route-u write ability tələb edir:

```php
Route::patch('/tasks/{task}/status', [TaskController::class, 'changeStatus'])->name('tasks.status');
```

Bu route `abilities:tasks:write` middleware group-u daxilindədir. Controller sonra policy çağırır. Policy-nin status qərarı:

```php
return $user->isActive()
    && $task->project->status === ProjectStatus::Active
    && $user->hasPermissionTo(PermissionName::TasksStatusChange->value)
    && $this->view($user, $task)
    && ($this->members->canManage($task->project, $user) || ($task->assignee_id === $user->id && $this->members->isMember($task->project, $user)));
```

## Axın

```text
Token authentication → active user → token ability
→ actor-visible resource → permission + policy → service state/transition qaydası
```

Token sahibinin girişi olan record üçün də konkret transition service tərəfindən rədd edilə bilər. Ability denial record existence göstərmədən 403 verir; sonrakı hidden/missing resource eyni safe 404 alır.

## Kiçik nümunə

Leyla layihənin üzvüdür, amma task ona təyin edilməyib və manager deyil. `tasks:write` token-i var. O task-ı görə bilər, lakin statusunu dəyişə bilməz. Token onun rolunu artırmır.

UI-də düyməni gizlətmək də authorization deyil. Eyni request-i əl ilə göndərən client backend policy/service tərəfindən yenə yoxlanılmalıdır.

## Kod parçasını açaq

- `isActive()`: suspended actor köhnə rolu saxlayır deyə əməliyyat edə bilməz.
- `ProjectStatus::Active`: record access-i olsa da read-only lifecycle-də mutation yoxdur.
- `hasPermissionTo(...)`: geniş capability yoxlanılır.
- `$this->view(...)`: actor-un task-ı görmə konteksti yoxlanılır.
- `canManage(...)`: admin/owner/layihə manager kontekstini service həll edir.
- `assignee_id === $user->id`: adi üzv üçün status məsuliyyəti konkret təyinata bağlıdır.

Policy-də görünən bu şərtlər token ability middleware-dən sonra da vacibdir. Ability `canManage()` nəticəsini true etmir.

## Niyə yalnız role adı ilə qərar vermirik?

“Qlobal project_manager-dirsə bütün task-ları dəyişsin” desək aid olmadığı layihələrin məlumatına access riski yaranar. “Assignee-dirsə hamını görsün” desək assignment-i visibility ilə qarışdırarıq. Qərar record-un layihəsi və actor əlaqəsi ilə verilir.

## Konkret failure nümunəsi

Fidan PAY üzvüdür və `tasks:write` token-i var, amma WEB üzvü deyil. WEB task ID-sini əl ilə request-ə yazması görünürlük vermir. Binding həmin actor-visible scope-da record-u tapmamalıdır; hidden/missing üçün eyni safe 404 müqaviləsi qorunur.

Ability çatmırsa record existence-i açıqlamadan 403 verilə bilər. Buna görə “403 gördüm, deməli həmin task mövcuddur” nəticəsi çıxarmaq düzgün deyil.

## Özünü yoxla

1. Qlobal member layihə manager-i ola bilərmi? **Bəli.**
2. Write ability actor-a yeni layihə üzvlüyü verirmi? **Xeyr.**
3. Web request-də PAT ability yoxlanılırmı? **Web session axınında bu token qatı yoxdur; policy/permission qalır.**

[Authorization axınının izahı](../diagrams/flows/authorization.md) və [PAT flow-u](../diagrams/flows/pat.md) iki sərhədi göstərir.

## Kod və yoxlama

- [API route group-ları](../../Modules/Tasks/routes/api.php)
- [Task policy](../../Modules/Tasks/app/Policies/TaskPolicy.php)
- [Manager qərarı](../../Modules/Projects/app/Services/ProjectMemberService.php)
- [Authorization matrix testləri](../../Modules/Tasks/tests/Feature/AuthorizationMatrixTest.php)
- [Token/API testləri](../../tests/Feature/Auth/CredentialTokenApiTest.php)
- [Rol matrisi](../business/ROLES_AND_PERMISSIONS.md)
