*14 Sentyabr — TaskFlow üzrə hesabat*

🔹 *Proyekt uğurla quruldumu? Qurulum zamanı hər hansı problem yaşadınızmı?*

Bəli, proyekti uğurla quraşdırdım. Qurulum zamanı hər hansı problemlə qarşılaşmadım.

🔹 *Proyekti manual olaraq UI-da test etdinizmi? Hər şey işləyirmi?*

Bəli, proyekti UI üzərindən manual olaraq test etdim. Ümumi funksionallıq öz qaydasında işləyir.

🔹 *Proyekti browser-də manual test edərkən hər hansı problemlə qarşılaşdınızmı?*

Bəli, Project yaradılması zamanı Project Key sahəsində problem müşahidə etdim. pay kimi kiçik hərflərlə dəyər daxil etdikdə interfeysdə PAY kimi görünsə də, sistem validation xətası verir. Dəyəri birbaşa PAY formasında daxil etdikdə isə əməliyyat düzgün işləyir.

🔹 *Testləri özünüz və ya Codex vasitəsilə işə saldınızmı?*

Bəli, testləri həm özüm, həm də Codex vasitəsilə işə saldım. Əsas testlər uğurla tamamlandı.

🔹 *Codex + Playwright ilə avtomatik testlər etdinizmi?*

Bəli. Codex vasitəsilə Playwright testləri işə salındı. 10 əsas istifadəçi axınının desktop və mobile variantları olmaqla ümumilikdə 20 Chromium testi yoxlanıldı və bütün testlər uğurla tamamlandı.

🔹 *Bütün dokumentasiyaları oxudunuzmu?*

Bəli. AGENTS.md və docs-ibp qovluğundakı bütün Markdown sənədlərini oxudum. Proyektin strukturu, biznes qaydaları, istifadəçi rolları, təhlükəsizlik prinsipləri, API, test strategiyası və modulların işləmə qaydaları ilə tanış oldum.

🔹 *Codex vasitəsilə kod bazasını araşdırdınızmı? Başa düşmədiyiniz hissələr üçün Codex-dən açıqlama istədinizmi?*

Bəli, Codex vasitəsilə kod bazasını araşdırdım. Xüsusilə authorization, project key, task nömrələnməsi, suspend edilmiş istifadəçilər, Livewire komponentləri və test infrastrukturu ilə bağlı hissələri nəzərdən keçirdim.

Başa düşməkdə çətinlik çəkdiyim hissələrin nə üçün istifadə edildiyini və necə işlədiyini daha yaxşı öyrənmək üçün Codex-dən əlavə açıqlamalar istədim.

🔹 *Hər hansı səhv/bug tapdınızmı? Tapdığınız problemləri fix edib səbəbini öyrənməyə çalışdınızmı?*

Manual UI testi və Codex vasitəsilə kod bazasının araşdırılması zamanı aşağıdakı problemlər müəyyən edildi:

1. *Project Key problemi:* pay daxil edildikdə input sahəsindəki uppercase görünüşünə görə dəyər vizual olaraq PAY görünür, lakin real input dəyəri lowercase qalır. Backend yalnız böyük hərflə yazılmış project key qəbul etdiyi üçün validation xətası yaranır. PAY birbaşa böyük hərflərlə daxil edildikdə əməliyyat düzgün işləyir.

2. *Suspend edilmiş istifadəçi və Livewire riski:* Livewire update route-da ayrıca active-user yoxlamasının olmadığı müşahidə edildi. Əksər komponentlər policy vasitəsilə qorunsa da, suspend edilmiş və köhnə session-u qalan istifadəçilər üçün əlavə təhlükəsizlik yoxlamasına ehtiyac ola bilər.

Bu problemlərin mümkün səbəblərini araşdırdım, lakin hazırda kod bazasında hər hansı fix və ya funksional dəyişiklik etməmişəm.