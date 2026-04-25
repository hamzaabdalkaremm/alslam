# نشر النظام على الاستضافة

1. ارفع محتويات مجلد `project` إلى `public_html` أو إلى مجلد الموقع.
2. أنشئ قاعدة بيانات MySQL جديدة من لوحة التحكم.
3. استورد ملف `database/database.sql`.
4. انسخ `config/database.local.example.php` إلى `config/database.local.php`.
5. عدل بيانات قاعدة البيانات داخل `config/database.local.php`.
6. إذا لزم، انسخ `config/app.local.example.php` إلى `config/app.local.php` وعدل القيم.
7. تأكد أن مجلد `assets/uploads` قابل للكتابة.
8. افتح `deploy-check.php` وتأكد أن جميع الفحوصات ناجحة.
9. سجّل الدخول عبر:
   - المستخدم: `admin`
   - كلمة المرور: `admin123`
10. بعد التأكد من التشغيل احذف `deploy-check.php`.

# ملاحظات أمان

- لا تترك ملفات المثال قابلة للعرض العام إذا كانت تحتوي بيانات حقيقية.
- غيّر كلمة مرور المدير مباشرة بعد أول دخول.
- استخدم HTTPS على الاستضافة.
