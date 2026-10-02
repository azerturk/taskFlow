# TaskFlow sənəd mərkəzi

Bu qovluq TaskFlow-un hazırkı, roadmap-xarici funksional və texniki vəziyyəti üçün əsas sənəd mənbəyidir. Buradakı mətnlər plan və ya tarixi status deyil; işləyən kod bazasının qəbul edilmiş müqaviləsini təsvir edir. Gələcək işlər yalnız kökdəki [`ROADMAP.md`](../ROADMAP.md) sənədindədir və ayrıca qərar olmadan cari davranış sayılmır.

## Oxuma ardıcıllığı

1. [`business/PRODUCT.md`](business/PRODUCT.md) — məhsulun məqsədi, əhatəsi və sərhədləri.
2. [`business/BUSINESS_RULES.md`](business/BUSINESS_RULES.md) — dəyişməz biznes qaydaları.
3. [`business/ROLES_AND_PERMISSIONS.md`](business/ROLES_AND_PERMISSIONS.md) — qlobal və layihə rolları.
4. [`technical/ARCHITECTURE.md`](technical/ARCHITECTURE.md) — qatlar, modullar və asılılıqlar.
5. Dəyişdirilən sahəyə uyğun modul sənədi.
6. API, təhlükəsizlik, test və mühit sənədləri.

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

## Modul sənədləri

- [`HOST_APPLICATION.md`](modules/HOST_APPLICATION.md)
- [`PROJECTS.md`](modules/PROJECTS.md)
- [`TASKS.md`](modules/TASKS.md)
- [`MEDIA.md`](modules/MEDIA.md)
- [`ACTIVITY.md`](modules/ACTIVITY.md)
- [`DASHBOARD.md`](modules/DASHBOARD.md)

## Sənəd qaydası

- Məhsul qaydası ilə kod ziddiyyət təşkil edərsə, açıq istifadəçi göstərişi və kök [`AGENTS.md`](../AGENTS.md) sənədindən sonra bu sənəd dəsti əsas götürülür.
- Kod dəyişəndə təsirlənən biznes, texniki və modul sənədləri eyni dəyişiklikdə yenilənir.
- İcra statusu, köhnə task ID-ləri və tarixi handoff mətnləri bu qovluğa əlavə edilmir.
- Sirr, parol, token, şəxsi media yolu və real mühit credential-ları sənədləşdirilmir.

