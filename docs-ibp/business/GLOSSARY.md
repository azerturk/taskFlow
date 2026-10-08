# Terminlər lüğəti

## Eyni iş üzərindən beş əsas söz

Leyla «Ödəniş button-u işləmir» adlı bug yaradır: Leyla **reporter**-dir. Aysel işi Murada tapşırır: Murad **assignee**-dir. Rauf dəyişikliklərdən xəbər tutmaq üçün işi izləyir: Rauf **watcher**-dir. Bug-un `review` olması onun **statusu**, review sütununda ikinci görünməsi isə **rank** ilə bağlıdır. Yüksək **priority** avtomatik «sütunda birinci» demək deyil.

Bu lüğət sürətli xatırlatma üçündür, tam dərs deyil. Texniki söz çətin gələndə [ayrıca mövzu izahını](../extended/README.md) və ya [uyğun flow dərsini](../diagrams/README.md) aç. Sənədlərdəki actor «hazırda əməliyyatı edən istifadəçi», scope isə «ona göstərilə bilən məlumat sərhədi» deməkdir.

| Termin | Mənası |
|---|---|
| TaskFlow | Tək təşkilat üçün daxili issue/work-item izləmə sistemi |
| Work item / issue | Məhsul termini; kodda `Task` modeli və `/tasks` API yolu ilə təmsil olunur |
| Reporter | İşi yaradan istifadəçi; sxemdə `creator_id` |
| Assignee | İşə cavabdeh sıfır və ya bir istifadəçi |
| Watcher | İşdə maraqlı olan və uyğun bildiriş alan layihə üzvü; edit səlahiyyəti vermir |
| Global role | Təşkilat səviyyəli `admin`, `project_manager`, `member` rolu |
| Project role | Konkret layihədə `manager` və ya `member` rolu |
| Project owner | Layihəni sahib kimi idarə edən və manager qalmalı olan istifadəçi |
| Project key | `PAY` kimi unikal böyük hərfli layihə kodu |
| Display key | Project key və lokal issue nömrəsi; məsələn `PAY-42` |
| Issue sequence | Layihənin növbəti work item nömrəsini atomik ayıran sayğac |
| Backlog | Yeni işlərin başladığı və ayrıca rank ilə planlandığı workflow vəziyyəti |
| Board | Work item-lərin workflow statuslarına görə sütun görünüşü |
| Rank | Project/status sütunu daxilində sıralama mövqeyi; prioritet deyil |
| Workflow | Sabit statuslar və icazəli keçid cədvəli |
| Subtask | Eyni layihədə standard parent tələb edən, özü parent ola bilməyən bir səviyyəli iş |
| Label | Layihəyə məxsus ad/slug/rəng təsnifatı |
| Activity | Təhlükəsiz, canonical və görünürlük-scope edilmiş audit qeydi |
| Notification | Database/in-app, Web-only istifadəçi bildirişi |
| Media | Şəxsi binary və server tərəfindən aşkarlanmış metadata |
| Attachment | Tasks modulunun Task ilə Media arasındakı explicit association qeydi |
| DTO | Qatlar arasında məqsədli məlumat daşıyan readonly obyekt; input DTO-su yoxlanmış input-u, read DTO-su isə hazırlanmış oxu nəticəsini daşıyır |
| Query service | Controller üçün tam scope edilmiş və presentation-ready read nəticəsi hazırlayan application boundary |
| Repository | Eloquent query, persistence, eager loading, pagination və lock sahibi qat |
| Policy/Gate | Konkret actor və record kontekstində authorization qərarı |
| Sanctum ability | API token route ailəsini daraldan, policy-ni əvəz etməyən capability |
| Optimistic concurrency | `expected_version` vasitəsilə stale status/rank yazısını 409 ilə rədd etmə |
| Compensation | Xarici storage DB transaction-a daxil olmadıqda uğursuz batch nəticələrini geri təmizləmə |
| Read-only project | `completed`/`archived` layihədə detail, üzv və iş dəyişiklikləri bağlıdır; completed üçün ayrıca icazəli lifecycle keçidləri qalır |
| HTTP REST API | `/api/v1` altında şəbəkə üzərindən request/response müqaviləsi |
| Modul public API-si | Başqa PHP modulunun istifadə etməsinə açıq contract/type-lar; HTTP endpoint olması tələb deyil |
| Event | Baş vermiş faktı listener-lərə bildirən obyekt; özü-özlüyündə queue və ya retry deyil |
| Projection | Başqa mənbədən hazırlanmış oxu nüsxəsi; R1-də Insights, əsas həqiqət isə Catalog-dur |
| After commit | Event-in faktiki outer transaction commit-dən sonra ötürülməsi; dayanıqlı mesaj saxlanması zəmanəti deyil |
| Rebuild | R1-də public feed-dən yalnız çatışmayan projection-ların atomik əlavə edilməsi; mövcud məlumatın yenidən yazılması deyil |
| Roadmap | Ayrıca açıq qərar tələb edən gələcək scope; R1-in məhdud tədris praktikası artıq ayrıca implementasiya edilib, bütöv roadmap tamamlanmayıb |
