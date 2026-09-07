# TaskFlow roadmap

## Status

Bu sənəd yalnız gələcək seçimləri saxlayır. Buradakı heç bir maddə cari məhsul müqaviləsinin hissəsi sayılmır və istifadəçi ayrıca roadmap işini açıq şəkildə başlatmadan implementasiya edilmir.

Cari işlək sistem, biznes və texniki həqiqət üçün [`docs-ibp/README.md`](docs-ibp/README.md), qəbul edilmiş release sübutu üçün [`docs-ibp/technical/RELEASE_BASELINE.md`](docs-ibp/technical/RELEASE_BASELINE.md) oxunmalıdır.

## R1 — Modul əlaqələrinin zəiflədilməsi

Məqsəd davranışı dəyişmədən modular monolith sərhədlərini ölçmək və yalnız real faydası sübut olunan yerlərdə gücləndirməkdir.

- module dependency graph və boundary metric-ləri;
- sabit use case-lər üçün purpose-specific cross-module contract-lar;
- notification/audit/cleanup kimi uyğun side-effect-lər üçün transactional domain event/outbox qiymətləndirilməsi;
- Dashboard üçün ölçülmüş read projection və cache strategiyası;
- module boundary failure/resilience testləri;
- cycle, hidden Eloquent coupling və breaking-contract yoxlamaları.

Gate: cari direct dependency-lər profillənməli, davranış və transaction ownership qorunmalı, əlavə abstraction konkret cycle/testability/performance problemini həll etməlidir. Generic bus, shared-kernel və speculative interface qadağandır.

## R2 — Təhlükəsizlik və privacy hardening

- MFA/2FA və recovery lifecycle;
- PAT siyahısı, expiry, rotation və device/session idarəsi;
- upload malware scanning, quarantine və scan statusu;
- strict CSP rollout, Trusted Types qiymətləndirilməsi və əlavə browser header hardening;
- security-event alerting və admin görünüşü;
- automated dependency, secret, container və SAST/DAST gate-ləri;
- data retention, privacy export/deletion siyasəti;
- incident response və backup/restore runbook-ları.

Gate: threat model, əməliyyat sahibi, false-positive/failure davranışı və deployment infrastrukturu müəyyən edilmədən tətbiq edilmir.

## R3 — Əməliyyat və performans

- observability: structured log, metrics, trace və SLO;
- queue worker supervision və failed-job bərpası;
- MySQL slow-query/index review və real həcm benchmark-ları;
- object storage/CDN deyil, private object storage stream strategiyasının qiymətləndirilməsi;
- backup, restore və disaster-recovery drill;
- zero-downtime migration/deploy proseduru;
- böyük project/board üçün projection, pagination və archive performansı.

Gate: real ölçü, hədəf SLO və rollback planı olmayan optimizasiya edilmir.

## Məhsul scope-una avtomatik daxil olmayan mövzular

Workspace/multi-tenancy, sprint, epic, release planning, story point, custom field/type/workflow/permission scheme, multiple assignee, dependency, recurring task, automation, webhook və external integration ayrıca məhsul qərarı tələb edir. Bu siyahıda görünməsi belə implementasiya icazəsi deyil.

## Roadmap işini başlatma qaydası

1. İstifadəçi konkret roadmap maddəsini açıq şəkildə seçir.
2. Məhsul məqsədi, təhlükəsizlik/data təsiri və qəbul meyarı yazılır.
3. Cari [`ARCHITECTURE_DECISIONS.md`](docs-ibp/technical/ARCHITECTURE_DECISIONS.md) ilə conflict qiymətləndirilir.
4. İmplementasiya, migration və rollback strategiyası təsdiqlənir.
5. Pest/MySQL/Playwright gate-ləri və sənədlər eyni dəyişiklikdə yenilənir.
