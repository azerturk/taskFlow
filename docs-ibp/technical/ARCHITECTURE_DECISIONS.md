# Arxitektura qərarları

Bu qeydlər cari kod bazasının qəbul edilmiş qərarlarını izah edir. Tarixi task statusu deyil; dəyişiklik ediləndə eyni PR/task daxilində kod, test və uyğun sənəd birlikdə yenilənməlidir.

## Bu qərarlar juniora necə kömək edir?

Bir problemi bir neçə cür həll etmək olar. Qərar sənədi komandada hansını seçdiyimizi göstərir. Məsələn, «niyə notification üçün ayrıca modul yoxdur?» sualının cavabı yalnız kodda folder axtarmaq deyil: onu host application məsuliyyəti kimi saxlamışıq.

Başqa nümunə: controller-də task update etmək texniki olaraq mümkündür, amma qəbul edilmiş qərar use case-in service-dən keçməsidir. Çünki status dəyişməsi tək bir field yazısı deyil; version, rank, Activity və notification kimi nəticələri var.

Üçüncü nümunə: iki lab modulunda event görməyimiz «indi bütün məhsul modullarını event-ə keçirək» qərarı deyil. Məhsul direct dependency-ləri ilə qalır, laboratoriya öyrənmə sərhədidir.

Aşağıdakı siyahı qısa istinad üçündür. Hər sözün mənasını buradan əzbərləmək əvəzinə [architecture izahını](../diagrams/system/dependencies.md), [qat dərsini](../diagrams/system/request-layers.md) və [ayrıca concept məqalələrini](../extended/README.md) oxu. Qərar dəyişikliyi yeni package və ya pattern seçməkdən əvvəl davranışın niyə dəyişməli olduğunu əsaslandırmalıdır.

## Qərar siyahısı

1. **Məhsul forması:** TaskFlow tək təşkilatlı, fixed-workflow, Kanban yönümlü minimal Jira alternatividir; workspace və multi-tenancy yoxdur.
2. **Deploy forması:** Laravel modular monolith, vahid database və vahid deploy unit istifadə olunur.
3. **Modul sərhədi:** məhsul modulları Projects, Tasks, Media, Activity və Dashboard-dur; auth, user admin, token və notification host tətbiqdədir. LearningCatalog və LearningInsights ayrıca təsdiqlənmiş R1 tədris modullarıdır, məhsul funksiyası deyil.
4. **Web/API texnologiyası:** Blade + Tailwind + Vite + vanilla JavaScript əsasdır; REST `/api/v1` ayrıca adapterdir.
5. **Livewire sərhədi:** yalnız `QuickTaskCreate`, `TaskFilters`, `TaskStatusSelector`, `TaskCommentForm` istifadə olunur.
6. **Layer axını:** adapter validation/authorization-dan sonra bir use-case və ya query service çağırır; repository yalnız Eloquent query/persistence sahibidir.
7. **Bir application boundary:** controller/Livewire repository, Eloquent, relation query, transaction və domain rule çağırmır.
8. **DTO:** mutation input-u readonly, purpose-specific DTO ilə yalnız validated məlumatdan qurulur; redundant model+ID və `request()->all()` yoxdur.
9. **Authorization və validation:** Spatie capability giriş qatıdır, record qərarı Policy/Gate verir, service state invariant-ını qoruyur. Cari project-key və admin-email uniqueness validation-ı request presence rule-u ilə DB oxuyan məhdud istisnadır; bunu query-siz validation kimi təqdim etmirik.
10. **İstifadəçi modeli:** hər user-in bir qlobal rolu var; public registration yoxdur; suspend access-i dərhal bağlayır və açıq responsibility/subscription-ları təmizləyir.
11. **İş ownership-i:** bir reporter, sıfır/bir assignee; çoxlu maraqlı tərəf watcher-dir.
12. **Görünürlük:** layihə üzvü layihənin bütün işlərini görə bilir; assignment visibility mexanizmi deyil.
13. **Təsnifat:** work type və priority fixed enum, label project-scoped-dur; category/component/custom scheme yoxdur.
14. **Workflow və rank:** fixed status keçidləri `TaskStatusService`-dədir; yeni iş backlog və server rank ilə yaranır; açıq reorder manager-only-dir.
15. **Issue identity:** display key project-local sequence-dən (`PAY-42`) gəlir. Key dəyişikliyi hazırda mövcud, soft-delete olunmamış iş olduqda bloklanır; bütün işlər soft-delete olunarsa lock aradan qalxır. Tarixi issue-lərin persisted display key-i və sequence-i yenidən yazılmır; bunu tam tarixi key immutability kimi təqdim etmirik.
16. **Project lifecycle:** draft/active/completed/archived sabitdir. Draft və active layihə detalları/üzvləri dəyişə bilər; iş və əməkdaşlıq yalnız active-dır. Completed detail/member/iş üçün bağlıdır, amma active və archived keçidləri qalır; archived terminaldır.
17. **Media ownership-i:** binary, detected metadata, stream və cleanup Media modulundadır; consuming Tasks explicit association və parent authorization sahibidir; polymorphic attachment yoxdur.
18. **Media atomikliyi:** multi-file request əvvəl tam validasiya olunur, DB yazıları bir transaction-dadır. Storage DB transaction-a daxil deyil; failure-da bütün request üzrə cleanup cəhd edilir, alınmayan cleanup üçün recovery record/log və pending xəta yaranır. Tam disk+DB atomikliyi və avtomatik retry yoxdur; Range/206 yoxdur.
19. **Audit və bildiriş:** canonical/sanitized Activity mərkəzidir; notification database/in-app və Web-only-dir; Dashboard watched queue verir.
20. **Test strategiyası:** Pest əsas qat, SQLite `:memory:` default, Herd MySQL compatibility, focused Playwright desktop/mobile qəbul qatıdır.
21. **Cari cross-module əlaqə:** davranış sabitləşənədək məqsədli birbaşa service/repository asılılıqları açıq şəkildə qəbul edilir; event bus, adapter və loose-coupling yenidənqurması [`ROADMAP.md`](../../ROADMAP.md) mövzusudur.
22. **R1 tədris sərhədi:** Catalog source of truth, Insights bərpa edilə bilən projection-dur. Əlaqə üç açıq PHP type, sinxron after-commit event və public read feed üzərində qurulub. Məhsul asılılıqları bununla dəyişdirilməyib.
23. **R1 bərpası:** `entry_id` üzrə missing-only yazı və iki UNIQUE constraint duplicate nəticəni məhdudlaşdırır; tam inbox/outbox və durable delivery yoxdur. Commit-dən sonrakı listener xətası Catalog insert-ini geri qaytarmır; rebuild çatışmayan projection-u əlavə edir, mövcud metadata-nı yeniləmir.

## Qərar dəyişmə qaydası

- Məhsul invariantını dəyişən iş əvvəl business sənədini və acceptance testini dəyişməlidir.
- HTTP REST API dəyişikliyi [`API.md`](API.md) və route-contract testini eyni dəyişiklikdə yeniləməlidir. Modulun daxili public PHP contract-ı dəyişirsə uyğun lab/modul sənədi, consumer və contract/integration testləri yenilənməlidir; HTTP route əlavə edilməsi tələb deyil.
- Data constraint dəyişikliyi SQLite/MySQL fresh və rollback gate-lərindən keçməlidir.
- Roadmap maddəsi ayrıca açıq şəkildə başladılmadan cari scope-a daxil edilmir.

Sadə izahlar əsas qərar mənbəyinin əvəzi deyil: [extended](../extended/README.md). Vizual xəritə: [diagramlar](../diagrams/README.md).
