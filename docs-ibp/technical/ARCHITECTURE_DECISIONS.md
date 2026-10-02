# Arxitektura qərarları

Bu qeydlər cari kod bazasının qəbul edilmiş qərarlarını izah edir. Tarixi task statusu deyil; dəyişiklik ediləndə eyni PR/task daxilində kod, test və uyğun sənəd birlikdə yenilənməlidir.

## Qərar siyahısı

1. **Məhsul forması:** TaskFlow tək təşkilatlı, fixed-workflow, Kanban yönümlü minimal Jira alternatividir; workspace və multi-tenancy yoxdur.
2. **Deploy forması:** Laravel modular monolith, vahid database və vahid deploy unit istifadə olunur.
3. **Modul sərhədi:** yalnız Projects, Tasks, Media, Activity və Dashboard moduldur; auth, user admin, token və notification host tətbiqdədir.
4. **Web/API texnologiyası:** Blade + Tailwind + Vite + vanilla JavaScript əsasdır; REST `/api/v1` ayrıca adapterdir.
5. **Livewire sərhədi:** yalnız `QuickTaskCreate`, `TaskFilters`, `TaskStatusSelector`, `TaskCommentForm` istifadə olunur.
6. **Layer axını:** adapter validation/authorization-dan sonra bir use-case və ya query service çağırır; repository yalnız Eloquent query/persistence sahibidir.
7. **Bir application boundary:** controller/Livewire repository, Eloquent, relation query, transaction və domain rule çağırmır.
8. **DTO:** mutation input-u readonly, purpose-specific DTO ilə yalnız validated məlumatdan qurulur; redundant model+ID və `request()->all()` yoxdur.
9. **Authorization:** Spatie capability giriş qatıdır, record qərarı Policy/Gate verir, service isə state invariant-ını bütün giriş nöqtələrində qoruyur.
10. **İstifadəçi modeli:** hər user-in bir qlobal rolu var; public registration yoxdur; suspend access-i dərhal bağlayır və açıq responsibility/subscription-ları təmizləyir.
11. **İş ownership-i:** bir reporter, sıfır/bir assignee; çoxlu maraqlı tərəf watcher-dir.
12. **Görünürlük:** layihə üzvü layihənin bütün işlərini görə bilir; assignment visibility mexanizmi deyil.
13. **Təsnifat:** work type və priority fixed enum, label project-scoped-dur; category/component/custom scheme yoxdur.
14. **Workflow və rank:** fixed status keçidləri `TaskStatusService`-dədir; yeni iş backlog və server rank ilə yaranır; açıq reorder manager-only-dir.
15. **Issue identity:** display key project-local sequence-dən (`PAY-42`) gəlir; ilk issue-dan sonra project key dəyişmir.
16. **Project lifecycle:** draft/active/completed/archived fixed-dir; yalnız active mutable, completed reopen edilə bilər, archived terminaldır.
17. **Media ownership-i:** binary, detected metadata, stream və cleanup Media modulundadır; consuming Tasks explicit association və parent authorization sahibidir; polymorphic attachment yoxdur.
18. **Media atomikliyi:** multi-file request əvvəl tam validasiya olunur; storage DB transaction-a daxil olmadığı üçün hər failure-də bütün request üzrə kompensasiya edilir; Range/206 yoxdur.
19. **Audit və bildiriş:** canonical/sanitized Activity mərkəzidir; notification database/in-app və Web-only-dir; Dashboard watched queue verir.
20. **Test strategiyası:** Pest əsas qat, SQLite `:memory:` default, Herd MySQL compatibility, focused Playwright desktop/mobile qəbul qatıdır.
21. **Cari cross-module əlaqə:** davranış sabitləşənədək məqsədli birbaşa service/repository asılılıqları açıq şəkildə qəbul edilir; event bus, adapter və loose-coupling yenidənqurması [`ROADMAP.md`](../../ROADMAP.md) mövzusudur.

## Qərar dəyişmə qaydası

- Məhsul invariantını dəyişən iş əvvəl business sənədini və acceptance testini dəyişməlidir.
- Public API dəyişikliyi [`API.md`](API.md) və route-contract testini eyni dəyişiklikdə yeniləməlidir.
- Data constraint dəyişikliyi SQLite/MySQL fresh və rollback gate-lərindən keçməlidir.
- Roadmap maddəsi ayrıca açıq şəkildə başladılmadan cari scope-a daxil edilmir.
