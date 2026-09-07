# Media modulu

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

Batch əvvəl tam validasiya edilir. Storage write, Media record, Task association və Activity mərhələlərindən biri alınmasa həmin request-in bütün nəticələri kompensasiya olunur.

## Stream və delete

Preview yalnız image/PDF, download bütün qəbul edilən formatlar üçündür. Stream `nosniff`, təhlükəsiz `Content-Disposition` və private cache header-ları qaytarır; public URL və Range/206 yoxdur.

Delete əvvəl association state-ini və binary cleanup imkanını qoruyur. Fiziki cleanup alınmasa metadata retry oluna bilən safe vəziyyətdə saxlanır; database silinib orphan binary buraxılmır. Uploader öz, project manager bütün task media-sını yalnız active layihədə silə bilər.

## Test məsuliyyəti

MIME spoofing, double extension, path/header injection, ölçü/pixel limit, private-field absence, wrong-parent/outsider access, full-byte stream, all-or-nothing batch və hər failure nöqtəsində compensation Pest ilə yoxlanır. Browser test image/PDF preview, download və delete journey-sini tamamlayır.
