TaskFlow üzrə yekun hesabat
🔹 Proyekt uğurla quruldumu? Qurulum zamanı problem yaşadınızmı?
Bəli, layihə lokal mühitdə uğurla quruldu və işlək vəziyyətə gətirildi.
İstifadə etdiyim əsas mühit:
- PHP 8.4.15
- Laravel 13.25.0
- Composer 2.10.2
- Node.js 24.19.0
- npm 11.17.0
- Nwidart modulları: Projects, Tasks, Media, Activity, Dashboard
Layihənin əsas Web və API route-ları, modullar və dependency-lər yoxlanıldı.
Playwright üçün Chromium quraşdırılması zamanı ayrıca lokal mühit problemi yarandı. Node-un default CA bundle-ı cdn.playwright.dev sertifikat zəncirini qəbul etmirdi və UNABLE_TO_VERIFY_LEAF_SIGNATURE xətası verirdi. TLS verification deaktiv edilmədən NODE_OPTIONS=--use-system-ca istifadə edilərək Windows trust store ilə problem həll olundu və Chromium uğurla quraşdırıldı.
Bu, application bug-u deyil, lokal environment/TLS problemi idi.
🔹 Proyekti manual olaraq UI-da test etdinizmi? Hər şey işləyirmi?
Bəli. Layihəni browser üzərindən geniş şəkildə manual test etdim.
Yoxlanılan əsas hissələr:
- Login/logout
- səhv login məlumatları
- Admin və Member authorization fərqləri
- User Management
- user yaratmaq
- suspend/reactivate
- password reset
- Project creation
- Project membership və project-local role
- Project lifecycle
- Task/Bug/Subtask creation
- assignment
- task status workflow
- labels
- comments
- watchers və notifications
- Activity log
- attachment upload/preview/download/delete
- private attachment authorization
- invalid file rejection
- Dashboard visibility
- responsive/mobile görünüş
Əsas funksionallıqlar manual testlərdə gözlənilən qaydada işləyir.
🔹 Browser-də manual test zamanı problem tapdınızmı?
Bəli. Manual test zamanı Project Key sahəsində real UX problemi aşkar edildi.
Məsələn istifadəçi:
mqa
yazdıqda CSS səbəbindən ekranda MQA kimi görünürdü, amma input-un real dəyəri lowercase qalırdı. HTML pattern isə yalnız uppercase qəbul etdiyi üçün browser request-i Laravel-ə göndərmədən bloklayırdı.
Yəni istifadəçi ekranda düzgün MQA görürdü, amma form validation fail olurdu.
Problem araşdırıldı və fix edildi.
Fix-dən sonra:
- mqa qəbul olunur;
- Laravel onu MQA kimi saxlayır;
- PAY kimi uppercase dəyər işləyir;
- PAY-42 Project Key kimi qəbul edilmir, çünki bu Project Key deyil, sonradan yaranan issue key formatıdır;
- duplicate və invalid key-lər əvvəlki kimi bloklanır.
🔹 Testləri özünüz və ya Codex vasitəsilə işə saldınızmı?
Bəli.
Həm kod bazası və test faylları Codex vasitəsilə araşdırıldı, həm də avtomatik test suite-lər işə salındı.
Son full nəticələr:
Test	Nəticə
Full SQLite suite	253/253 PASS — 1,885 assertions
Full MySQL suite	253/253 PASS — 1,885 assertions
Architecture suite	24/24 PASS — 89 assertions
Static suite	27/27 PASS — 138 assertions
Format check	PASS
Frontend build	PASS


MySQL testləri ayrıca taskflow_test* test bazasında safety guard yoxlanıldıqdan sonra icra edildi.
🔹 Codex + Playwright ilə avtomatik browser testləri etdinizmi?
Bəli.
Playwright suite:
- 10 əsas journey
- desktop + mobile
- ümumilikdə 20 execution
Final nəticə:
20 passed
0 failed
Playwright testlərində login, user lifecycle, projects, tasks, assignment/status, labels, notifications, media, lifecycle/read-only, mobile və JavaScript-disabled ssenarilər cover olunur.
🔹 Bütün dokumentasiyaları oxudunuzmu?
Bəli.
AGENTS.md və docs-ibp daxilindəki biznes, texniki və modul dokumentasiyaları nəzərdən keçirildi.
Xüsusilə bunları öyrəndim:
- Project və Task business rules
- global role ilə project-local role fərqi
- authorization/policy məntiqi
- modular architecture
- Route → Request → Policy → DTO → Service → Repository flow-u
- Task workflow
- Project lifecycle
- Media security
- Activity və Notification fərqi
- optimistic concurrency
- Dashboard visibility
- API/Sanctum ability və Policy əlaqəsi
🔹 Codex vasitəsilə kod bazasını araşdırdınızmı?
Bəli.
Codex vasitəsilə route-lar, controller-lər, service-lər, repository-lər, policies, Form Request-lər, DTO-lar, Livewire flow-u, testlər və security hissələri araşdırıldı.
Başa düşməkdə çətinlik çəkdiyim hissələr üçün əlavə izah aldım.
Xüsusilə bunları daha detallı araşdırdım:
- Global Role ≠ Project Membership Role
- Priority ≠ Rank
- Activity ≠ Notification
- Media ≠ TaskAttachment
- version və expected_version
- Project visibility
- Task status transition-ları
- nested resource authorization
- suspended user session handling
- Livewire persistent middleware
Tapılan əsas problemlər
1. Project Key UX problemi — FIXED ✅
Problem: CSS uppercase yalnız görünüşü dəyişirdi, real input lowercase qalırdı. Browser uppercase-only pattern səbəbindən request-i bloklayırdı.
Fix:
- input pattern lowercase/uppercase qəbul edəcək formada dəyişdirildi;
- visual uppercase CSS çıxarıldı;
- backend normalization saxlanıldı;
- test coverage genişləndirildi;
- Playwright testlərində lowercase key → uppercase stored value yoxlanıldı.
Nəticə: FIXED və regression testləri PASS.
2. Suspended user / Livewire security problemi — FIXED ✅
Codex ilə security/business rule yoxlanışı zamanı daha ciddi problem aşkar edildi.
Aktiv user dashboard-u açdıqdan sonra Admin həmin user-i suspend edirdi. Normal Web request artıq login-ə yönləndirilsə də, əvvəl açılmış Livewire snapshot istifadə edilərək Livewire update request göndərildikdə suspended user müəyyən protected project məlumatını ala bilirdi.
Root cause:
EnsureActiveUser normal protected Web route-larda işləyirdi, amma Livewire persistent middleware siyahısında yox idi.
Fix-dən sonra EnsureActiveUser Livewire persistent middleware kimi də tətbiq edildi.
Yeni regression test sübut edir:
- active member → Livewire request işləyir;
- suspended member → 401, protected project data yoxdur;
- project-dən çıxarılmış member → əvvəlki project data-sını görə bilmir.
Fix-dən sonra full SQLite və MySQL suite-lər:
253/253 PASS
1,885 assertions
Nəticə: security vulnerability FIXED ✅
Manual QA nəticələrindən əsas nümunələr
Authentication / Authorization
- düzgün Admin login — PASS
- səhv email/password generic error — PASS
- logout — PASS
- logout-dan sonra protected dashboard access — bloklandı
- Member /admin/users — 403
- Member /projects/create — 403
- Member project edit direct access — 403
User lifecycle
- internal user yaratmaq — PASS
- password confirmation validation — PASS
- suspend edilmiş user login — bloklandı
- reactivate edildikdən sonra login — PASS
- Admin password reset — PASS
- yeni password ilə login — PASS
Projects
- project creation — PASS
- creator owner oldu — PASS
- member əlavə etmək — PASS
- local Manager → Member role change — PASS
- Member yalnız membership-dən sonra project-i görə bildi — PASS
Lifecycle:
Draft → Active               PASS
Active → Completed           PASS
Completed read-only          PASS
Completed → Active           PASS
Active → Archived            PASS
Archived terminal/read-only  PASS
Archived project-də Edit, Reopen, Manage Members və Create Task mutation action-ları yoxdur.
Tasks
MQA-1:
- generated project-local key — PASS
- Bug type — PASS
- Backlog initial status — PASS
- High priority — PASS
- reporter düzgün — PASS
- manager assignment — PASS
- assignment notification — PASS
Assignee workflow:
Backlog → Todo → In Progress → Review → Done
— PASS
Bütün dəyişikliklər Activity-də actor ilə birlikdə qeydə alındı.
Subtask
MQA-2 subtask yaradıldı:
- parent MQA-1
- Backlog
- Medium priority
Açıq subtask olduğu halda parent Done edilməyə çalışıldı və sistem blokladı.
Subtask tamamlandıqdan sonra parent Done edilə bildi.
— PASS
Labels
QA project label yaradıldı və MQA-3 task-a tətbiq edildi.
— PASS
Label management Web route işləyir, amma Project detail və Tasks index-dən həmin səhifəyə görünən navigation linki tapılmadı. Bunu UX observation kimi qeyd etdim.
Comments / Watchers / Notifications
Admin MQA-3-ə comment əlavə etdi.
Reporter olan Manual Test User avtomatik watcher olduğuna görə:
New task comment · MQA-3
notification aldı.
Comment Activity-də də Comment added kimi qeydə alındı.
— PASS
Notification üçün ayrıca Mark read işləyir və badge azalır. Open task notification-u avtomatik read etmir; bu hazırda UX observation kimi qeyd edildi, business bug kimi yox.
Media / Attachments
046.png ilə:
- upload — PASS
- image preview — PASS
- download — PASS
- Activity upload event — PASS
- delete — PASS
- Activity delete event — PASS
Project-ə üzv olmayan user eyni attachment URL-ni açmağa çalışanda:
404 Not Found
aldı.
Bu private media isolation qaydasına uyğundur.
.svg upload testində:
The file type is not allowed.
mesajı alındı və fayl upload edilmədi.
— PASS
Dashboard visibility
Project-ə üzv olmayan TaskFlow Member dashboard-a daxil oldu.
Sistemdə real project və task-lar olmasına baxmayaraq həmin user üçün:
Active Projects     0
Completed Projects  0
Archived Projects   0
Total Tasks         0
göstərildi.
Assigned, Reported, Watched Work və Recent Activity də boş idi.
Bu başqa project məlumatlarının outsider user-ə sızmadığını göstərir.
— PASS
Responsive / Mobile
Chrome DevTools-da:
iPhone 12 Pro
390 × 844
ölçüsündə Dashboard, Projects və Tasks səhifələri yoxlanıldı.
Navigation işləyirdi və əsas UI-da nəzərə çarpan responsive problem müşahidə edilmədi.
— PASS
Əlavə UX / future improvement müşahidələri
Bunları application bug kimi qiymətləndirmədim, amma gələcəkdə yaxşılaşdırmaq olar:
1. Login səhifəsində unauthenticated Forgot password? recovery flow yoxdur. Hazırda Admin başqa user-in password-unu reset edə bilir, amma parolunu unudan son Admin UI-dan özünü bərpa edə bilmir.
2. Project label management səhifəsi işləyir, amma əsas UI-dan ona görünən navigation linki tapılmadı.
3. Open task notification-u avtomatik read etmir; user ayrıca Mark read etməlidir.
4. Explicit watcher backend/Web route-ları mövcuddur, amma Task detail-də ayrıca görünən Watch/Unwatch control tapılmadı.
Yekun nəticə
TaskFlow layihəsinin əsas funksionallıqları həm manual, həm automated şəkildə yoxlanıldı.
Final avtomatik nəticələr:
SQLite:     253/253 PASS
MySQL:      253/253 PASS
Playwright: 20/20 PASS
Architecture: 24/24 PASS
Static:       27/27 PASS
Build/Format: PASS
Manual QA zamanı Project Key UX problemi və suspended-user Livewire security problemi aşkar edildi, səbəbləri araşdırıldı, fix edildi və regression testlərlə qorundu.
Hazırkı nəticəyə əsasən layihənin əsas business flow-ları, authorization/security qaydaları, project/task lifecycle, media privacy, notifications, activity, dashboard isolation və responsive görünüş işlək vəziyyətdədir.