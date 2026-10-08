# İstifadəçi hesabını yaratmaq, dayandırmaq və yenidən aktivləşdirmək

[Diagram atlasına qayıt](../README.md) · [Parol axını](password.md)

## Məqsəd nədir?

TaskFlow təşkilat daxilində istifadə olunan sistemdir.
İstənilən şəxs qeydiyyat forması açıb özünə hesab yaratmır.
Administrator kimlərin işləyə biləcəyini idarə edir.

Hesabı dayandırmaq təkcə login düyməsini bağlamaq deyil.
İstifadəçinin köhnə token-ləri, açıq session-ları və iş məsuliyyətləri də düzgün idarə olunmalıdır.
Eyni zamanda əvvəlki iş tarixçəsi itirilməməlidir.

Bu səhifədəki Aysel administrator, Murad isə dayandırılan istifadəçi kimi yalnız izah ssenarisidir.
Heç bir real hesab və credential göstərilmir.

## Vacib sözlər

- **Account / hesab**: `users` cədvəlindəki istifadəçidir.
- **Active**: girişə və icazəli fəaliyyətə uyğun hesab vəziyyətidir.
- **Suspended**: hesab silinmədən istifadə imkanının dayandırılmasıdır.
- **Global role**: sistem səviyyəsində rol; konkret project-dəki membership rolu ilə eyni deyil.
- **Assignment**: task-a kimin cavabdeh olduğunu göstərir.
- **Unassign**: task-ın assignee əlaqəsini boşaltmaqdır; task-ı silmək deyil.
- **Watcher**: task-dakı dəyişikliklərə abunə olan istifadəçidir.
- **Transaction**: bir neçə DB dəyişikliklərinin birlikdə qəbul edilməsi və ya birlikdə geri alınmasıdır.
- **Lock**: eyni qeydi paralel dəyişən əməliyyatların toqquşmasını idarə etmək üçün DB kilididir.
- **Audit / Activity**: kimin hansı mənalı əməliyyatı etdiyinin təhlükəsiz tarixçəsidir.
- **Version**: task dəyişdikcə artırılan saydır; köhnə ekranla edilən update-i aşkar etməyə kömək edir.

## Ssenari

Muradın bir `in_progress`, bir `done` işi və iki watcher abunəliyi var.
Aysel onun hesabını suspend edir.
`in_progress` işi açıq məsuliyyət olduğuna görə assignee-si boşaldılır.
`done` işindəki tarixi assignee əlaqəsi isə saxlanır.

Murad daha sonra yenidən aktivləşdirilir.
Sistem onun köhnə token-lərini və watcher abunəliklərini geri yaratmır.
Yeni giriş edə bilər, amma iş bölgüsü ayrıca yenidən verilməlidir.

## Şəkli oxuyaq

![Daxili hesab lifecycle](account-admin.svg)

Yuxarı hissə hesab yaradılmasını, ortadakı vəziyyət qutuları active → suspended → active keçidini göstərir.
Aşağıdakı böyük transaction sahəsi suspend zamanı birlikdə görülən işlərdir.

1. **Admin Gate**: Web admin route-una daxil olan actor həm administrator rolunu, həm də user role management permission-ını daşımalıdır.
2. **Create**: validated DTO service-ə gəlir. Email normallaşdırılır, parol hash edilir, hesab active və seçilmiş global rol ilə yaradılır.
3. **Son aktiv admin yoxlaması**: sistem idarəetməsiz qalmamalıdır. Son aktiv administratoru suspend etmək və ya admin rolundan çıxarmaq qorunur.
4. **Açıq assignment-lar**: suspend olunan şəxsə aid açıq task-lar lock edilir, assignee boşaldılır, task version artırılır və səbəbli Activity yazılır.
5. **Giriş və abunəlik təmizliyi**: watcher-lər, bütün PAT-lər və session-lar silinir; hesab suspended edilir.
6. **Bildiriş və audit**: uyğun watcher-lər dəyişiklikdən xəbər alır, hesabın suspend əməliyyatı təhlükəsiz say göstəriciləri ilə qeyd edilir.
7. **Commit**: DB dəyişiklikləri birlikdə qəbul edilir. Gözlənilməz exception olsa transaction-un DB dəyişiklikləri rollback edilir.
8. **Reactivate**: hesab yenidən active olur və ayrıca audit yaranır. Silinən giriş/assignment/watcher vəziyyətləri bərpa edilmir.

Watcherlər bildirişdən əvvəl təmizləndiyi üçün suspend olunan şəxs bu dəyişiklik bildirişinin recipient-i kimi saxlanmır.
Bu flow fiziki media faylı silmir və istifadəçi tarixçəsini məhv etmir.

## Real kod: suspend zamanı təhlükəsizlik təmizliyi

[AdminUserService](../../../app/Services/AdminUserService.php)-də həmin transaction daxilindəki əsas hissə:

~~~php
$watcherCount = $this->watchers->removeForUser($user);
$tokenCount = $this->tokens->revokeAllFor($user);
$sessionCount = $this->sessions->deleteForUser($user);
$user = $this->users->setStatus($user, AccountStatus::Suspended);
~~~

- `removeForUser` task watcher abunəliklərini təmizləyir; task və comment-ləri silmir.
- `revokeAllFor` bir cari token-i yox, həmin hesabın bütün PAT-lərini ləğv edir.
- `deleteForUser` bütün saxlanmış session-ları təmizləyir.
- `setStatus` istifadəçini DB-dən silmir; status-u suspended edir.
- `...Count` dəyişənləri neçə qeyd təmizləndiyini auditdə göstərmək üçündür. Token və session dəyərləri auditə yazılmır.

Bunlardan əvvəl task məsuliyyətləri repository tərəfindən boşaldılır.
[EloquentTaskRepository](../../../Modules/Tasks/app/Repositories/Eloquent/EloquentTaskRepository.php)-də bu dəyişiklik belədir:

~~~php
$task->assignee_id = null;
$task->version++;
$task->save();
~~~

İlk sətir məsul şəxsi boşaldır.
İkinci sətir version-u artırır. Status və board/rank yazıları `expected_version` müqayisə etdiyinə görə köhnə versiyanı görən ekranın həmin əməliyyatında konflikt aşkar edilə bilər.
Bu müqayisəni bütün update-lərə şamil etmə: detail və assignment axınlarında universal `expected_version` müqayisəsi yoxdur.
Üçüncü sətir dəyişikliyi DB-yə yazır.
Service həmin dəyişiklik üçün `reason=assignee_suspended` məlumatlı Activity də yaradır.

## Hansı işlər “açıq” sayılır?

Repository `done` və `cancelled` statuslarını istisna edir.
Qalan statusdakı, normal query-də görünən task assignment-ları təmizlənir.
Tarixi bağlanmış işlərdə assignee saxlanır; reporter və əvvəlki Activity də qalır.

Bu təhlükəsizlik təmizliyi adi project mutation-u deyil.
Completed və archived project-lərdəki açıq məsuliyyətlərin də təhlükəsizlik səbəbi ilə təmizlənməsi nəzərdə tutulub.
“Project read-only-dir, deməli hesabın access revoke-u işləməməlidir” nəticəsi doğru deyil.

Project-dən üzv çıxarma flow-u fərqlidir: açıq assignment orada çıxarmağı bloklaya bilər.
Account suspend isə açıq assignment-ı təmizləyir.
İki əməliyyatın məqsədi və nəticəsi eyni deyil.

## DB-də və istifadəçidə nəticə

| Əməliyyat | Qalan və dəyişən məlumat |
|---|---|
| Create | `users`, global rol əlaqəsi və təhlükəsiz `UserCreated` audit yaranır. |
| Identity/role update | Ad/email/global rol dəyişir, `UserUpdated` qeyd edilir; project membership ayrıca mövzudur. |
| Suspend | Hesab qalır; açıq assignee-lər boşalır, version artır, watcher/PAT/session təmizlənir, audit qalır. |
| Reactivate | Status active olur və audit yaranır; köhnə silinmiş giriş və abunəliklər bərpa olunmur. |

Active-user middleware növbəti Web/API request-lərində hesabın aktivliyini yenə yoxlayır.
Livewire update-lərində də persistent active-user middleware saxlanır.
Təkcə “admin səhifəsində statusu dəyişdik” müdafiəsi ilə kifayətlənilmir.

## Dayanma və failure halları

- Admin Gate-dən keçməyən şəxs bu idarəetmə əməliyyatını edə bilməz.
- Input xətası varsa validated DTO ilə mutation-a keçilmir.
- Son aktiv adminin suspend/demotion cəhdi məqsədli lifecycle conflict ilə rədd edilir.
- Artıq suspended olan hesaba `suspend` çağırışı erkən return edir; cleanup və audit yenidən təkrarlanmır.
- Transaction daxilində DB əməliyyatı uğursuz olarsa onun yarımçıq DB dəyişiklikləri commit edilmir.
- Reactivate yeni giriş imkanı verir, “bütün əvvəlki vəziyyəti restore et” əmri deyil.

## Tez-tez qarışan suallar

**Suspend ilə delete eynidir?** Xeyr. Hesab və tarixçə saxlanır, istifadə imkanı dayandırılır.

**Role dəyişmək project membership-i də dəyişir?** Bunlar ayrı anlayışlardır. Bu service identity/global rol idarə edir.

**Niyə `done` task-ın assignee-si qalır?** Bu tarixi məsuliyyət məlumatıdır; artıq açıq iş bölgüsü deyil.

**Reactivate-dən sonra köhnə token niyə işləmir?** Revoke edilmiş token-i active status geri yaratmır.

## Özünü yoxla

1. Suspend task-ı silir? **Xeyr, açıq task-ın assignee əlaqəsini boşaldır.**
2. Bütün PAT-lər ləğv olunur? **Bəli.**
3. Son aktiv adminin admin rolu çıxarıla bilər? **Qoruyucu lifecycle qaydası bunu bloklayır.**
4. Reactivate watcher-ləri geri gətirir? **Xeyr.**

## Mənbə və testlər

- [AdminUserService](../../../app/Services/AdminUserService.php), [Admin Gate](../../../app/Providers/AppServiceProvider.php).
- [EnsureActiveUser](../../../app/Http/Middleware/EnsureActiveUser.php), [task assignment repository](../../../Modules/Tasks/app/Repositories/Eloquent/EloquentTaskRepository.php).
- [InternalUserLifecycleTest](../../../tests/Feature/Admin/InternalUserLifecycleTest.php), [SuspensionHistoryTest](../../../tests/Feature/Admin/SuspensionHistoryTest.php).
- [LivewireActiveUserBoundaryTest](../../../tests/Feature/LivewireActiveUserBoundaryTest.php).
- [Host application](../../modules/HOST_APPLICATION.md), [transaction və failure izahı](../../technical/TRANSACTIONS_AND_FAILURES.md).

