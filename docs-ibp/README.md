# TaskFlow sənəd mərkəzi

Bu qovluq TaskFlow-un cari kod bazasını Azərbaycan dilində izah edir. Mövcud davranışın həqiqət mənbəyi koddur; sənədlər onu biznes qaydaları, texniki sərhədlər, nümunələr və diagramlarla təsvir edir. Kodla uyğun gəlməyən mətn cari implementasiya kimi qəbul edilməməli, kod və uyğun testlərlə müqayisə edilərək düzəldilməlidir.

Beş **məhsul modulu** var: Projects, Tasks, Media, Activity və Dashboard. Host tətbiq auth, istifadəçi idarəsi, token və bildirişlərə sahibdir. Ayrıca implementasiya edilmiş **iki R1 tədris modulu** — LearningCatalog və LearningInsights — məhsul axınlarından izolyasiya olunub; yeni UI/REST endpoint təqdim etmir.

[`ROADMAP.md`](../ROADMAP.md) gələcək istiqamətləri saxlayır. R1 laboratoriyasının mövcudluğu bütöv R1-in və ya digər roadmap işlərinin tamamlanması demək deyil. Nəzəri conceptin izah edilməsi də onun implementasiya edildiyi mənasına gəlmir.

## Sənəd qatları

| Hissə | Nə üçündür? |
|---|---|
| [`business`](business/PRODUCT.md) | Məhsulun məqsədi, istifadəçi axınları, mövcud biznes qaydaları |
| [`technical`](technical/ARCHITECTURE.md) | Cari texniki quruluş, data, HTTP API, təhlükəsizlik və test sərhədləri |
| [`modules`](modules/HOST_APPLICATION.md) | Host və beş məhsul modulunun məsuliyyəti |
| [`labs/r1`](labs/r1/README.md) | Kodda olan iki learning modulunun faktiki davranışı |
| [`extended`](extended/README.md) | Junior üçün ayrıca sadə izahlar və müqayisələr |
| [`diagrams`](diagrams/README.md) | Sistemin və əsas axınların izahlı SVG xəritələri |

`extended` əsas qaydaların ikinci, ziddiyyətli mənbəyi deyil. Hər məqalə cari kod nümunəsinə və uyğun əsas sənədə bağlanır; yalnız gələcək anlayışlar ayrıca işarələnir.

## Oxuma ardıcıllığı

1. [`business/PRODUCT.md`](business/PRODUCT.md) — məhsulun məqsədi, əhatəsi və sərhədləri.
2. [`business/BUSINESS_RULES.md`](business/BUSINESS_RULES.md) — dəyişməz biznes qaydaları.
3. [`business/ROLES_AND_PERMISSIONS.md`](business/ROLES_AND_PERMISSIONS.md) — qlobal və layihə rolları.
4. [`technical/ARCHITECTURE.md`](technical/ARCHITECTURE.md) — qatlar, modullar və asılılıqlar.
5. Dəyişdirilən sahəyə uyğun modul sənədi.
6. API, təhlükəsizlik, test və mühit sənədləri.

## Junior üçün başlanğıc yolu

1. Əvvəl [junior başlanğıc bələdçisini](JUNIOR_START.md) oxu: Aysel və Muradın bir layihə/bug hekayəsi üzərindən sistemin işi izah edilir.
2. Məhsulu anlamaq üçün [məqsəd](business/PRODUCT.md), [istifadəçi axınları](business/USER_FLOWS.md) və [rolları](business/ROLES_AND_PERMISSIONS.md) oxu.
3. Şəkli tək açmaq əvəzinə [ümumi diagramın izahından](diagrams/system/context.md) başlayıb [request qatlarını](diagrams/system/request-layers.md) və [kod xəritəsini](technical/CODEBASE_GUIDE.md) izlə.
4. [Sadə müqayisələr](extended/README.md) içindən çətinlik çəkdiyin mövzunu seç; hamısını bir dəfəyə əzbərləmək lazım deyil.
5. Sonra [R1 laboratoriyası](labs/r1/README.md) üzərində Public API və event fərqini kod/testlə müqayisə et.

Biznes oxucusu üçün ilk üç business sənədi və diagram izahları kifayətdir. Kod dəyişən developer uyğun technical/modul sənədini və kök [`AGENTS.md`](../AGENTS.md) qaydalarını da oxumalıdır.

## Biznes sənədləri

- [`PRODUCT.md`](business/PRODUCT.md)
- [`BUSINESS_RULES.md`](business/BUSINESS_RULES.md)
- [`ROLES_AND_PERMISSIONS.md`](business/ROLES_AND_PERMISSIONS.md)
- [`USER_FLOWS.md`](business/USER_FLOWS.md)
- [`GLOSSARY.md`](business/GLOSSARY.md)

## Texniki sənədlər

- [`ARCHITECTURE.md`](technical/ARCHITECTURE.md)
- [`DATA_MODEL.md`](technical/DATA_MODEL.md)
- [`API.md`](technical/API.md)
- [`SECURITY.md`](technical/SECURITY.md)
- [`TESTING.md`](technical/TESTING.md)
- [`ENVIRONMENT.md`](technical/ENVIRONMENT.md)
- [`ARCHITECTURE_DECISIONS.md`](technical/ARCHITECTURE_DECISIONS.md)
- [`RELEASE_BASELINE.md`](technical/RELEASE_BASELINE.md)
- [`CODEBASE_GUIDE.md`](technical/CODEBASE_GUIDE.md)
- [`TRANSACTIONS_AND_FAILURES.md`](technical/TRANSACTIONS_AND_FAILURES.md)

## Modul sənədləri

- [`HOST_APPLICATION.md`](modules/HOST_APPLICATION.md)
- [`PROJECTS.md`](modules/PROJECTS.md)
- [`TASKS.md`](modules/TASKS.md)
- [`MEDIA.md`](modules/MEDIA.md)
- [`ACTIVITY.md`](modules/ACTIVITY.md)
- [`DASHBOARD.md`](modules/DASHBOARD.md)

## R1 laboratoriya sənədləri

- [Laboratoriyanın ümumi axını](labs/r1/README.md)
- [LearningCatalog](labs/r1/LEARNING_CATALOG.md)
- [LearningInsights](labs/r1/LEARNING_INSIGHTS.md)

## Diagramlar və sadə izahlar

- [Junior üçün başlanğıc bələdçisi](JUNIOR_START.md) — məhsul hekayəsi, kod qatları və diagram oxuma üsulu.
- [SVG diagram kitabxanası](diagrams/README.md) — 27 şəkil saxlanılır; hər birinin yanında eyni adlı ayrıca `.md` dərsi var. Dərsdə məqsəd, termin, nümunə, qutu/oxların ardıcıllığı, real kod, nəticə/xəta və özünüyoxlama izah edilir.
- [Mövzu və müqayisə indeksi](extended/README.md) — hər mövzu ayrıca fayldadır.

## Sənəd qaydası

- Cari davranışı təsvir edərkən son kod və uyğun testlər əsas götürülür. Sənəd səhvdirsə yalnız mətn düzəldilir; sənədə uyğunlaşdırmaq adı ilə kod davranışı səssiz dəyişdirilmir. İstifadəçinin açıq göstərişi və kök [`AGENTS.md`](../AGENTS.md) təhlükəsizlik/iş qaydaları qorunur.
- Kod dəyişəndə təsirlənən biznes, texniki və modul sənədləri eyni dəyişiklikdə yenilənir.
- İcra statusu, köhnə task ID-ləri və tarixi handoff mətnləri bu qovluğa əlavə edilmir.
- Sirr, parol, token, şəxsi media yolu və real mühit credential-ları sənədləşdirilmir.
- Məhsul davranışı, lab davranışı və yalnız nəzəri/gələcək anlayış açıq ayrılır. Code identifier-ləri və command-lar tərcümə edilmir, izah mətni Azərbaycan dilindədir.
- Tarixli [release sübutu](technical/RELEASE_BASELINE.md) tarixi nəticədir; köhnə run yeni kod üçün təzə test kimi təqdim edilmir.
- SVG-lər redaktə edilə bilən mənbədir; diagramın yanında ayrıca mətn alternativi, kod istinadı və lazım olan failure davranışı saxlanılır. Məsələn, `diagrams/flows/activity.svg` üçün [activity.md](diagrams/flows/activity.md) oxunur. Documentation SVG-ləri Media moduluna upload edilə bilən format siyahısını dəyişmir.

