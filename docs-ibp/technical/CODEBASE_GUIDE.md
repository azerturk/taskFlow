# Kodbazada istiqamət tapmaq

Bu bələdçi «dəyişiklik üçün hansı fayldan başlayım?» sualına cavab verir. Yeni qayda müəyyən etmir; cari kodun xəritəsidir. Məhsulun nə etdiyi [PRODUCT.md](../business/PRODUCT.md), qatların niyə ayrıldığı [ARCHITECTURE.md](ARCHITECTURE.md), axınların şəkilləri isə [diagram xəritəsində](../diagrams/README.md) göstərilir.

## İlk dəfə kod oxuyursansa

Bir anda bütün repository-ni anlamağa çalışma. Bir istifadəçi məqsədi seç: məsələn, «işin statusunu dəyişmək». Əvvəl [bu axının sadə dərsini](../diagrams/flows/status.md) oxu. Sonra aşağıdakı xəritə ilə route, controller, DTO, service, repository və testi ardıcıl aç.

Class adında `Service` görmək onun nə etdiyini tam demir. Method-un input-una, hansı qaydanı yoxladığına, nəyi yazdığına və nə qaytardığına bax. Constructor-dakı dependency-lər onun hansı hissələrlə danışdığını göstərir.

İki oxu üsulunu ayır: **request flow** bir əməliyyatın runtime yoludur; **dependency graph** isə hansı source-un hansı type-a bağlı olduğunu göstərir. Bir import olması hər request-də o kodun çağırıldığı demək deyil. [Dependency dərsi](../diagrams/system/dependencies.md) bunu ayrıca izah edir.

Testdən başlamaq da faydalıdır: testin setup-ı qaydanın hansı şəraitdə qüvvədə olduğunu, assertion-lar nəticəni göstərir. Metodun yalnız uğurlu yolunu yox, exception-a qədərki və sonrakı commit vəziyyətini də oxu.

## Qovluqları necə oxuyaq?

| Yer | Nə axtarmalıyıq? |
|---|---|
| `app/` | Host authentication, istifadəçi idarəsi, notification, shared middleware və repository-lər |
| `Modules/Projects` | Layihə identity/lifecycle/membership |
| `Modules/Tasks` | İş, workflow/rank, label/watcher/comment və attachment əlaqəsi |
| `Modules/Media` | Faylın private saxlanması, content yoxlaması, metadata və cleanup |
| `Modules/Activity` | Audit event enum-u, sanitizer, recorder və görünən tarixçə |
| `Modules/Dashboard` | Mövcud mənbələrdən count/queue hazırlamaq |
| `Modules/LearningCatalog`, `Modules/LearningInsights` | Yalnız R1 tədris praktikası; məhsula qoşulmayan contract/event/projection |
| `routes/`, `Modules/*/routes/` | HTTP girişləri, middleware, route adları |
| `resources/views`, `Modules/*/resources/views` | Blade presentation; persistence qaydasının yeri deyil |
| `resources/js` | Board/modal/preview və digər məqsədli browser davranışı |
| `database/migrations`, `Modules/*/database/migrations` | Həqiqi schema, constraint və indekslər |
| `tests`, `Modules/*/tests` | Mövcud davranışın işlək nümunələri və qəbul meyarları |

Fayl yolu ilə namespace eyni yazılmır: məsələn, `Modules/Tasks/app/Services/TaskService.php` class-ı `Modules\Tasks\Services\TaskService`-dir. `app` namespace hissəsi deyil; hər modulun `composer.json` PSR-4 xəritəsi bunu müəyyən edir.

## Bir HTTP əməliyyatını izləmək

Məsələn, status dəyişir, amma gözlənilən nəticə alınmır:

1. `Modules/Tasks/routes/web.php` və ya `routes/api.php` faylında route-u tap.
2. Uyğun controller-də validation və `authorize()` çağırışını oxu. API-də ability middleware-ini də yoxla.
3. `ChangeTaskStatusRequest` və `ChangeTaskStatusData` ilə input-un necə qurulduğunu gör.
4. `TaskPolicy::changeStatus()` giriş icazəsini izah edir.
5. `TaskStatusService::change()` versiya, subtask və workflow invariantlarını tətbiq edir.
6. `TaskTransitionRules`/`TaskStatusTimestamps` keçid və tarix qaydasını, repository lock/rank yazısını idarə edir.
7. Resource və ya redirect istifadəçiyə artıq hazırlanmış nəticəni verir.
8. `TaskWorkflowTest.php`, `TaskRankTest.php`, `TaskStatusSelectorLivewireTest.php` ilə gözlənilən davranışı müqayisə et.

403 ilə 409 eyni səbəb deyil: birincisi giriş icazəsi, ikincisi sənədləşdirilmiş state/version konfliktidir. HTTP-dən kənar service çağırışı policy yoxlamasını avtomatik etmir.

## Mövzu → konkret kod

| Mövzu | Əsas başlanğıc |
|---|---|
| Session login/logout | `app/Services/AuthenticationService.php` |
| İstifadəçi yaratma/suspend/parol | `app/Services/AdminUserService.php` |
| Account middleware və rate limit | `app/Http/Middleware/EnsureActiveUser.php`, `app/Providers/AppServiceProvider.php` |
| Notification inbox və görünən linklər | `app/Services/NotificationCenterService.php` |
| Layihə yarat/detail/lifecycle | `Modules/Projects/app/Services/ProjectService.php` |
| Üzvlük və manager/participant qərarı | `Modules/Projects/app/Services/ProjectMemberService.php` |
| Yeni iş və project-local nömrə | `TaskService::create()`, `ProjectService::allocateIssueNumber()` |
| Status və optimistic concurrency | `TaskStatusService::change()`, `TaskRankService::reorder()` |
| Filter, scope, eager loading | `Modules/Tasks/app/Repositories/Eloquent/EloquentTaskRepository.php` |
| Watcher recipient-ləri | `TaskWatcherNotificationService`, `EloquentTaskWatcherRepository::eligibleWatchers()` |
| Multi-file və cleanup | `TaskAttachmentService`, `MediaStorageService`, `MediaMetadataService` |
| Audit | `Modules/Activity/app/Services/ActivityRecorder.php` və `Support/ActivitySanitizer.php` |
| Dashboard composition | `Modules/Dashboard/app/Services/DashboardService.php` |
| Qlobal domain error cavabları | `bootstrap/app.php` |
| R1 publish, public feed, rebuild | [Laboratoriya sənədləri](../labs/r1/README.md) |

Cədvəldə qısa class adları istifadə ediləndə məhsul service-ləri Tasks modulunun `app/Services`, repository-si isə uyğun modulun `app/Repositories/Eloquent` qovluğundadır. Bu, host-a başqa modulun daxili repository-sini istənilən yerdən çağırmaq icazəsi vermir; cari allowlist [arxitekturada](ARCHITECTURE.md) göstərilir.

## Testdə haradan başlayaq?

- Business qaydası: modulun `tests/Unit` və `tests/Feature` faylları.
- HTTP/authorization problemi: `tests/Feature/Security`, `tests/Feature/Auth`, modulun uyğun Feature testi.
- Query artımı: `tests/Feature/QueryBoundaryTest.php`, modul presentation/query-budget testləri.
- Qat/modul sərhədi: `tests/Architecture/ControllerBoundaryGuardTest.php` və `R1LearningBoundaryTest.php`.
- Real commit/rollback: `Modules/LearningInsights/tests/Integration/R1LearningFlowTest.php`.
- Browser interaction: `tests/e2e`; Pest edge case-lərini burada təkrar etmək lazım deyil.

Test komandaları, təhlükəsiz DB seçimi və `RefreshDatabase`/`DatabaseMigrations` fərqi [TESTING.md](TESTING.md) daxilindədir. `.env`/`.env.testing` sirrlərini report-a və AI prompt-una kopyalama.

## Yeni davranış əlavə edərkən

Əvvəl uyğun biznes/modul sənədini və yaxın testi oxu. Sonra HTTP adapter → məqsədli DTO → tam use-case service → repository bölgüsünü saxla. Faylı və ya method-u tapmaq üçün `rg` istifadə etmək kifayətdir; işləyən bazada migration/seeder tətbiq etmək kod oxumağın normal addımı deyil. İcazə, migration və dependency əməliyyatları [mühit qaydalarına](ENVIRONMENT.md) tabedir.

Öyrənmək üçün daha sadə sual-cavablar [extended](../extended/README.md) daxilindədir. Oradakı gələcək concept nümunəsi hazırda kodda mövcud mexanizm kimi qəbul edilməməlidir.
