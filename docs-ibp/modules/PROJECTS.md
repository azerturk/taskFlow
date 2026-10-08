# Projects modulu

## Sadə dillə: bu modul nəyi idarə edir?

Bir işi yaratmazdan əvvəl onun hansı layihədə olduğunu və həmin layihədə kimlərin işlədiyini bilməliyik. Projects bu işin kontekstinə sahibdir: layihənin adı/açarı, owner-i, üzvləri və həyat dövrü.

Məsələn, Aysel PAY layihəsini əvvəl draft yaradır, Muradı üzv əlavə edir, sonra active edir. Draft hazırlıq mərhələsidir; completed işləri oxumaq üçün saxlayır; archived isə terminaldır, yenidən açılmır. Bu statuslar Tasks workflow statusları deyil.

Üzvü çıxarmaq təkcə bir association silmək deyil. Açıq iş onun üzərindədirsə əvvəl məsuliyyət həll olunmalıdır. Ona görə service bu əməliyyatı yoxlayır və watcher cleanup-ı ilə birlikdə yerinə yetirir.

Əvvəl [layihə axınının geniş izahını](../diagrams/flows/project.md), sonra aşağıdakı texniki müqaviləni oxu. [Qlobal və layihə rolu fərqi](../extended/roles-permissions-policies-abilities.md) də bu modul üçün vacibdir.

## Məsuliyyət

Projects layihə aggregate-i, lifecycle, owner və project membership-in sahibidir. Modul actor-visible project query-ləri, manager qərarları və layihə context-inə görə iştirak imkanını təqdim edir.

## Model və qaydalar

- `Project`: name, slug, key, description, status, owner, tarixlər və `next_issue_number`.
- Project key xam Web/API inputunda lowercase və mixed-case ola bilər, server canonical dəyəri trim edib böyük hərfə çevirir; `PAY` project key, `PAY-42` isə serverin yaratdığı task display key-dir.
- `ProjectMember`: project/user cütü, `manager|member` rolu və joined time.
- Yeni layihə `draft`, creator owner/manager olur.
- Layihə detail və üzv hazırlığı `draft` və `active` vəziyyətlərində açıqdır. İş, label, watcher, şərh və media dəyişiklikləri isə yalnız `active` vəziyyətində mümkündür.
- `completed` detail/member/iş üçün read-only-dır; manager onu `active` və ya `archived` edə bilər. `archived` terminaldır. Policy manager authority-sini, service isə lifecycle invariantını yoxlayır; «button yoxdur» təhlükəsizlik sərhədi deyil.
- Owner membership-dən silinə və manager rolundan endirilə bilməz.
- Açıq assignment-lı üzvün çıxarılması 409-dur; uğurlu çıxarılmada həmin layihənin watcher-ləri təmizlənir.
- Key dəyişikliyi `ProjectService::update()` daxilində `EloquentTaskRepository::existsForProject()` ilə bloklanır. Bu query soft-deleted işləri saymır: bütün işlər silinibsə key dəyişdirilə bilər, əvvəlki issue display key-ləri və sequence isə qalır. Cari kodun bu məhdudiyyəti «ilk issue-dan sonra sonsuz immutable» iddiasından fərqlidir; sənəd bunu gizlətmir.

## Application sərhədləri

`ProjectService` create/update/lifecycle, `ProjectMemberService` membership use case-lərini orkestrasiya edir; `ProjectQueryService` Web/API üçün presentation-ready səhifə nəticələrini hazırlayır. Controller-lər repository çağırmır. Repository actor scope, pagination, eager loading, key/slug persistence və project sequence lock-larını idarə edir.

## Asılılıqlar

Projects Activity yazır; member removal və lifecycle integrity üçün Tasks query/cleanup imkanlarından istifadə edir. Tasks layihə membership və manage/participate qərarları üçün Projects service-lərini çağırır.

## Səth və testlər

Web project list/detail/create/edit/lifecycle/member səhifələrini təqdim edir. Active project detail-də yalnız manager üçün görünən label idarəetmə keçidi var. API 9 project və membership əməliyyatını təqdim edir. Policy matrix, lifecycle, mövcud issue olduqda key lock-u, browser/server key müqaviləsi, owner protection, scope, query budget, Activity və SQLite/MySQL constraint-lər Pest ilə qorunur. Bütün issue-lərin soft-delete olunmasından sonrakı key davranışı ayrıca acceptance testi ilə sübut edilmiş daimi immutability zəmanəti deyil.

## İki real axın

**Create:** `ProjectService::create()` transaction açır, draft project yazır, `addMemberWithinTransaction()` ilə owner-i manager əlavə edir, Activity yazır və eager-loaded detail qaytarır. Daxili member collaborator-u ikinci transaction açmır.

**Member removal:** `ProjectMemberService::removeMember()` əvvəl layihənin mutable olduğunu, owner olmadığını və mövcud üzvlüyü yoxlayır. Açıq assignment varsa `MemberHasOpenAssignments` yaranır. Yoxdursa həmin layihənin watcher-ləri və membership eyni transaction-da silinir, Activity yazılır. Üzvün tarixi reporter/comment məlumatı silinmir.

Kod: `Modules/Projects/app/Services/{ProjectService,ProjectMemberService}.php`; testlər: `ProjectLifecycleAndKeyTest.php`, `ProjectMemberIntegrityTest.php`, `ProjectPresentationTest.php`. [Diagram xəritəsi](../diagrams/README.md).
