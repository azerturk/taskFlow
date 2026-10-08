# Media modulu

## Faylın özü ilə ona aid məlumatı ayıraq

Screenshot-un byte-ları diskdə saxlanır; adı, ölçüsü, real MIME tipi və random path-i haqqında məlumat database-dədir. Bir də onun hansı task-a bağlı olduğunu göstərən `TaskAttachment` var. Bunlar eyni şey deyil və eyni modulun eyni cədvəli deyil.

Tasks «bu fayl PAY-42-yə bağlıdır» əlaqəsinə və user icazəsinə, Media isə «bu faylı təhlükəsiz necə yoxlayım, saxlayım, oxudum və sildim?» sualına sahibdir. Media-nın ayrıca HTTP route-u olmaması onun istifadəsiz olması demək deyil: onu consuming Tasks service-i çağırır.

Ən vacib fərq: database rollback diskdəki faylı avtomatik silmir. Bunun üçün ayrıca cleanup cəhdi edilir və o cəhd də xəta verə bilər. Aşağıdakı texniki qaydaları bu fərqi nəzərə alaraq oxu.

Üç ayrı dərs: [upload zamanı bütün batch](../diagrams/flows/media-upload.md), [private preview/download](../diagrams/flows/media-stream.md), [silinmə və failure nəticəsi](../diagrams/flows/media-delete.md). [Transaction və compensation fərqi](../extended/transaction-vs-compensation.md).

## Məsuliyyət

Media private binary və onun server tərəfindən aşkarlanmış metadata lifecycle-ının yeganə sahibidir. Tasks parent authorization və explicit `task_attachments` association-a sahibdir; Media birbaşa Web/API route təqdim etmir.

## Saxlanılan metadata

Public-safe `uuid`, uploader, disk, random unikal path, təmizlənmiş original name, allowlist extension, detected MIME, byte size, SHA-256 və image dimensions. Disk, path və checksum presentation-a çıxmır.

## Upload qaydası

- maksimum 5 fayl/request və 10 MB/fayl;
- PDF, PNG, JPEG, WebP, plain TXT/LOG/MD, DOC/DOCX, XLS/XLSX;
- SVG, HTML, script, archive, executable və unknown binary qadağandır;
- client filename, extension və MIME etibarlı sayılmır;
- content signature/MIME serverdə aşkarlanır, extension/MIME cütü allowlist ilə yoxlanır;
- image dimension/pixel təhlükəsizlik limiti tətbiq olunur.

Cari limit mənbəyi `Modules/Media/config/config.php`-dir: hər tərəf maksimum 8 000 piksel, ümumi maksimum 40 000 000 piksel. Media ayrıca `media.disk=local` istifadə edir; yalnız `FILESYSTEM_DISK` dəyişməsi bu modu avtomatik başqa diskə keçirmir.

Batch əvvəl tam validasiya edilir. Media record, Task association və Activity bir DB transaction-dadır. Storage və ya DB yazısı alınmasa request-in saxlanmış fayllarının kompensasiyası cəhd edilir; cleanup özü də fail edə bilər. Buna görə tam disk+DB atomikliyi deyil, DB atomikliyi və açıq recovery sərhədi var.

## Stream və delete

Preview image/PDF üçün inline cavabdır; başqa qəbul edilmiş formatın preview çağırışı download fallback-ı verir. Download bütün qəbul edilən formatlar üçündür. Stream `nosniff`, təhlükəsiz `Content-Disposition` və `private, no-store` cache header-ları qaytarır; public URL və Range/206 yoxdur.

`TaskAttachmentService::delete()` əvvəl association-u silir və Activity-ni eyni transaction-da commit edir. Sonra `MediaStorageService::delete()` fiziki faylı, yalnız uğurdan sonra metadata-nı soft-delete edir. Fiziki cleanup alınmasa association artıq yoxdur, aktiv Media metadata-sı və safe UUID ilə xəta qalır. Fiziki silinmə uğurlu, metadata delete uğursuz olsa artıq binary yoxdur, amma metadata qala bilər; xəta loglanır. Bu, disk və DB-nin atomik transaction-u deyil. Uploader öz, project manager bütün task media-sını yalnız active layihədə silə bilər.

Upload compensation zamanı disk cleanup da uğursuz ola bilər. `compensateStored()` həmin faylı unassociated Media record-u ilə izlənən saxlamağa çalışır; record yazısı da fail etsə critical log yaranır. Cari kod retry üçün məlumat saxlayır, lakin avtomatik worker/UI təqdim etmir. Cleanup retry-si internal `MediaStorageService::delete()` üzərindən olur; eyni silinmiş attachment endpoint-ini təkrar çağırmaq bərpa mexanizmi deyil.

## Test məsuliyyəti

MIME/extension spoofing, filename path/header injection, ölçü/pixel limit, private-field absence, wrong-parent/outsider access, tam stream, batch validation və storage/metadata/association/Activity failure nöqtələrində rollback/compensation Pest ilə yoxlanır. Cleanup və metadata-delete failure-ları da ayrıca injection testlərindədir. Browser test image/PDF preview, download və delete ssenarisini tamamlayır; son browser run tarixini [qəbul sübutlarından](../technical/RELEASE_BASELINE.md) oxumaq lazımdır.

Əsas kod `MediaStorageService`, `MediaMetadataService`, `MediaFilePolicy` və consuming `TaskAttachmentService`-dir. Failure injection testləri `Modules/Tasks/tests/Feature/TaskAttachmentFailureSafetyTest.php` daxilindədir. [Ətraflı transaction ardıcıllığı](../technical/TRANSACTIONS_AND_FAILURES.md), [diagram xəritəsi](../diagrams/README.md).
