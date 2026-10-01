14 Sentyabr — TaskFlow üzrə hesabat

🔹 Proyekt uğurla quruldumu? Qurulum zamanı hər hansı problem yaşadınızmı?
Bəli, TaskFlow layihəsini lokal mühitdə uğurla işə saldım. Qurulum zamanı layihənin işləməsinə mane olacaq ciddi bir problem ilə qarşılaşmadım.

🔹 Proyekti manual olaraq UI-da test etdinizmi? Hər şey işləyirmi?
Bəli, əsas funksionallıqları UI üzərindən manual şəkildə yoxladım. Login, project və task əməliyyatları, task-ların idarə olunması və digər əsas hissələri test etdim. Ümumi olaraq funksionallıqlar düzgün işləyir.

🔹 Proyekti browser-də manual test edərkən hər hansı problemlə qarşılaşdınızmı?
Bəli, Project yaradılması zamanı Project Key sahəsində problem aşkar etdim. pay kimi kiçik hərflərlə dəyər daxil etdikdə interfeysdə PAY kimi görünsə də, sistem həmin dəyəri qəbul etmir və validation xətası verir. Dəyəri birbaşa PAY formasında daxil etdikdə isə problem yaranmır.

🔹 Testləri özünüz və ya Codex vasitəsilə işə saldınızmı?
Bəli, mövcud testləri icra etdim və nəticələri yoxladım. Həmçinin Codex vasitəsilə testlərin strukturu və yoxladığı ssenarilər haqqında əlavə araşdırma apardım.

🔹 Codex + Playwright ilə avtomatik testlər etdinizmi?
Bəli, Playwright vasitəsilə browser üzərində avtomatik testlər apardım. Test ssenarilərini Codex ilə analiz edərək əsas istifadəçi axınlarını yoxladım və test nəticələrini nəzərdən keçirdim.

🔹 Bütün dokumentasiyaları oxudunuzmu?
Bəli, layihə üzrə təqdim olunan dokumentasiyaları nəzərdən keçirdim. Layihənin strukturu, modulları, biznes qaydaları və əsas funksionallıqları ilə tanış oldum.

🔹 Codex vasitəsilə kod bazasını araşdırdınızmı? Başa düşmədiyiniz hissələr üçün Codex-dən açıqlama istədinizmi?
Bəli, kod bazasını Codex vasitəsilə araşdırdım. Validation, authorization, Policy/Gate, Livewire və test məntiqi ilə bağlı hissələri incələdim. Başa düşülməsi çətin olan bölmələr üzrə Codex-dən əlavə izahlar alaraq həmin məntiqi kod üzərində də yoxladım.

🔹 Hər hansı səhv/bug tapdınızmı? Tapdığınız problemləri fix edib səbəbini öyrənməyə çalışdınızmı?
Bəli, manual test zamanı Project Key ilə bağlı problem müəyyən etdim. Araşdırma zamanı məlum oldu ki, input sahəsində böyük hərflərlə göstərilmə yalnız görüntünü dəyişir və daxil edilən real dəyəri uppercase etmir. Buna görə pay daxil edildikdə ekranda PAY görünsə də, validation zamanı real dəyər lowercase olaraq qalır və xəta yaranır.

Problemin səbəbini araşdırdım və müəyyən etdim, lakin hazırda kodda dəyişiklik etməmişəm.

Aşkar edilmiş problem

Project Key validation problemi:
pay kimi kiçik hərflərlə daxil edilən dəyər UI-da PAY formasında göstərilir, lakin validation zamanı qəbul edilmir. PAY birbaşa böyük hərflərlə daxil edildikdə isə əməliyyat düzgün işləyir. Problemin səbəbi araşdırılıb, lakin bu mərhələdə fix tətbiq edilməyib.