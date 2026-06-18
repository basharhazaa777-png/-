<?php
// ==========================================
// محرك الأمان الخلفي المطور لـ م. بشار صغير - نسخة الاستقرار الثابتة
// ==========================================
require_once 'config.php';
session_start();

// تهيئة الرمز السري الافتراضي في الجلسة السحابية
if (!isset($_SESSION['bashar_pin'])) {
    $_SESSION['bashar_pin'] = "777"; 
}

$error_message = "";

// معالجة قفل الجلسة وتسجيل الخروج
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['is_admin']);
    session_destroy();
    // تفريغ المحتوى في المتصفح أيضاً عبر جافا سكريبت عند الخروج
    echo "<script>localStorage.removeItem('bashar_admin_auth'); window.location.href=window.location.pathname;</script>";
    exit;
}

// معالجة تغيير الرمز السري وحفظه في الجلسة السحابية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_pin') {
    if (isset($_POST['new_pin_code']) && strlen($_POST['new_pin_code']) >= 3) {
        $_SESSION['bashar_pin'] = $_POST['new_pin_code'];
        echo "<script>alert('تم تحديث رمز الحماية بنجاح م. بشار سحابياً!'); window.location.href=window.location.pathname;</script>";
        exit;
    }
}

// معالجة التحقق الموحد من الرمز السري عند النقر على لوحة المسؤول
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_login') {
    if (isset($_POST['pin_code']) && $_POST['pin_code'] === $_SESSION['bashar_pin']) {
        $_SESSION['is_admin'] = true;
        // هنا التعديل: نقوم بحفظ الحالة محلياً ونوجه الصفحة بشكل يضمن بقاء البيانات ثابته
        echo "<script>
            localStorage.setItem('bashar_admin_auth', 'true');
            window.location.href = window.location.pathname + '?login=success';
        </script>";
        exit;
    } else {
        $error_message = "الرمز السري خاطئ، تم رفض صلاحية المسؤول!";
    }
}

// تمرير حالة المسؤول لمتغير الجافاسكربت
$isAdmin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>نظام المكتبة الإلكترونية المتكامل | م. بشار صغير</title>
    
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1a3a5f; --primary-light: #244d7d; --accent: #d4a373; --bg: #f4f6f9; --white: #ffffff;
            --text: #2d3436; --sidebar-w: 290px; --success: #27ae60; 
            --warning-dark: #2980b9; /* أزرق ملكي للتعديل */
            --danger-dark: #576574;  /* رمادي صخري فاخر للحذف والخروج */
            --admin-card-bg: #1e1e24;
        }
        body.dark-mode { --bg: #121212; --white: #1e1e1e; --primary: #1a252f; --text: #e0e0e0; }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Tajawal', sans-serif; }
        body { background: var(--bg); color: var(--text); overflow-x: hidden; transition: background 0.3s; }

        /* شريط الجوال العلوي */
        .mobile-bar { display: flex; position: fixed; top: 0; left: 0; right: 0; background: var(--primary); color: white; padding: 15px; z-index: 8500; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.15); }
        .hidden { display: none !important; }

        /* مقبض سحب القائمة الجانبية المطور للجوال */
        .drawer-pull-tab {
            display: flex; position: fixed; right: 0; top: 50%; transform: translateY(-50%);
            width: 24px; height: 60px; background: var(--primary); color: var(--accent);
            z-index: 8800; border-radius: 12px 0 0 12px; align-items: center; justify-content: center;
            box-shadow: -2px 4px 10px rgba(0,0,0,0.2); cursor: pointer; transition: 0.3s ease;
            border: 1px solid rgba(255,255,255,0.1); border-right: none;
        }
        .drawer-pull-tab i { animation: pulseArrow 1.5s infinite ease-in-out; font-size: 14px; }
        .drawer-pull-tab.sidebar-open { transform: translateY(-50%) translateX(100%); opacity: 0; }

        @keyframes pulseArrow {
            0%, 100% { transform: translateX(0); opacity: 0.6; }
            50% { transform: translateX(-4px); opacity: 1; }
        }

        /* القائمة الجانبية */
        .sidebar { 
            width: var(--sidebar-w); background: linear-gradient(145deg, var(--primary), var(--primary-light)); color: white; 
            position: fixed; right: 0; top: 0; bottom: 0; z-index: 9000; 
            transition: 0.4s cubic-bezier(0.1, 0.76, 0.55, 0.94); transform: translateX(100%); 
            display: flex; flex-direction: column; overflow: hidden; box-shadow: -5px 0 25px rgba(0,0,0,0.15);
        }
        .sidebar.active { transform: translateX(0); }
        .nav-content { flex: 1; overflow-y: auto; padding: 20px 12px; }
        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 8900; backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); }
        .overlay.active { display: block; }

        /* روابط الأقسام والمثلث الدليلي */
        .nav-link { 
            padding: 14px 12px; margin-bottom: 10px; border-radius: 12px; cursor: pointer; 
            display: flex; align-items: center; gap: 12px; background: rgba(255,255,255,0.04); 
            font-size: 14px; font-weight: 700; transition: all 0.3s ease; position: relative;
        }
        .nav-link:hover { background: rgba(255,255,255,0.08); padding: 14px 16px; }
        .nav-link.active { background: var(--accent); color: var(--primary); font-weight: 900; box-shadow: 0 4px 12px rgba(212,163,115,0.3); }
        .nav-link.active::after {
            content: ''; position: absolute; left: -1px; top: 50%; transform: translateY(-50%);
            border-style: solid; border-width: 7px 7px 7px 0; border-color: transparent var(--bg) transparent transparent;
            transition: 0.3s;
        }

        .sidebar-footer { border-top: 1px solid rgba(255,255,255,0.08); padding: 20px 15px; background: rgba(0,0,0,0.15); }
        .admin-open-btn { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px; background: var(--accent); color: var(--primary); border-radius: 12px; font-weight: 900; cursor: pointer; transition: 0.2s; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        
        /* حاوية العرض الرئيسية */
        .main-content { padding: 85px 16px 40px; max-width: 1200px; margin: 0 auto; transition: 0.3s; }
        
        /* صفوف عرض الكتب */
        .doc-row { background: var(--white); margin-bottom: 12px; padding: 16px; border-radius: 16px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 10px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.03); transition: 0.2s; }
        .doc-row:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0,0,0,0.05); }
        .doc-title { flex: 1; font-size: 14px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-left: 15px; }
        .doc-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        
        .btn-sm { padding: 9px 16px; border-radius: 10px; color: white; text-decoration: none; font-size: 13px; font-weight: bold; border: none; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-admin-action { padding: 9px 14px; border-radius: 10px; font-size: 13px; font-weight: bold; cursor: pointer; border: none; color: white; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; }

        /* لوحات التحكم والبوب أب السحابي */
        .admin-fullscreen-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 20000; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); justify-content: center; align-items: center; padding: 16px; }
        .admin-fullscreen-overlay.active { display: flex; }
        .admin-hub-card { width: 100%; max-width: 500px; background: var(--admin-card-bg); border-radius: 28px; padding: 30px 20px; color: #fff; box-shadow: 0 20px 50px rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.08); }

        .hub-box { background: rgba(255, 255, 255, 0.02); border: 2px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 20px 15px; margin-bottom: 16px; cursor: pointer; display: flex; align-items: center; gap: 15px; transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); }
        .hub-box:hover { background: rgba(255, 255, 255, 0.07); border-color: var(--accent); transform: translateY(-2px); }
        .hub-icon { width: 52px; height: 52px; background: rgba(212, 163, 115, 0.12); color: var(--accent); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
        .hub-info { flex: 1; }
        .hub-info h4 { font-size: 15px; color: #fff; font-weight: bold; margin-bottom: 5px; }
        .hub-info p { font-size: 11px; color: #b3b3b3; line-height: 1.4; }

        .admin-panel-input { width: 100%; padding: 12px; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.15); color: #fff; border-radius: 12px; font-size: 13px; outline: none; margin-bottom: 14px; transition: 0.2s; }
        .admin-panel-input option { background: var(--admin-card-bg); color: white; }

        .sub-popup-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 25000; backdrop-filter: blur(8px); justify-content: center; align-items: center; padding: 16px; }
        .sub-popup-overlay.active { display: flex; }
        .sub-popup-card { background: var(--admin-card-bg); border-radius: 28px; width: 100%; max-width: 460px; padding: 25px; color: #fff; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 25px 50px rgba(0,0,0,0.6); }

        #splash { position: fixed; inset: 0; z-index: 30000; background: var(--primary); display: flex; justify-content: center; align-items: center; padding: 16px; }
        .splash-box { background: var(--white); padding: 35px 25px; border-radius: 28px; text-align: center; width: 100%; max-width: 390px; border-bottom: 6px solid var(--accent); box-shadow: 0 20px 45px rgba(0,0,0,0.25); }

        /* توافق الشاشات الكبيرة للكمبيوتر */
        @media (min-width: 992px) {
            .sidebar { transform: translateX(0); }
            .main-content { margin-right: var(--sidebar-w); padding-top: 25px; padding-left: 25px; padding-right: 25px; }
            .mobile-bar, .overlay, .drawer-pull-tab { display: none !important; }
            #appTopBar { display: none !important; }
        }
    </style>
</head>
<body>

    <?php if(!empty($error_message)): ?>
        <script>alert("<?php echo $error_message; ?>");</script>
    <?php endif; ?>

    <div id="splash">
        <div class="splash-box">
            <img src="logo.png" width="85" style="margin-bottom: 15px;" onerror="this.src='https://placehold.co/85'">
            <h2 style="color: var(--primary); font-size: 20px; font-weight: 900;">مكتبة كلية التربية والعلوم - باجل</h2>
            <p style="font-size: 13px; color: #666; margin: 12px 0 25px; line-height: 1.6;">منصة سحابية متكاملة لإدارة واستعراض المقررات الأكاديمية وملفات الـ PDF الحقيقية للطلاب.</p>
            <button onclick="App.init()" style="width:100%; padding:16px; background:var(--primary); color:white; border:none; border-radius:14px; font-weight:bold; cursor: pointer; font-size:15px; box-shadow: 0 4px 15px rgba(26,58,95,0.3);">دخول المنصة وبدء التصفح</button>
            <div style="font-size: 11px; color: #a0a0a0; margin-top: 18px; font-weight: bold;">تطوير وإعداد: م. بشار صغير</div>
        </div>
    </div>

    <div class="overlay" id="uiOverlay" onclick="UI.menu(false)"></div>

    <div class="drawer-pull-tab hidden" id="mobileDrawerTab" onclick="UI.menu(true)">
        <i class="fas fa-chevron-left"></i>
    </div>

    <div class="mobile-bar hidden" id="appTopBar">
        <i class="fas fa-bars" onclick="UI.menu(true)" style="cursor: pointer; font-size: 22px;"></i>
        <span style="font-weight:900; font-size: 17px; letter-spacing: 0.5px;">المكتبة السحابية</span>
        <i class="fas fa-user-shield" onclick="UI.triggerHubWithAuth()" style="cursor: pointer; font-size: 20px; color: var(--accent);"></i>
    </div>

    <aside class="sidebar" id="sidebar">
        <div style="text-align:center; padding: 25px 15px; border-bottom: 1px solid rgba(255,255,255,0.06);"><img src="logo.png" width="55" onerror="this.src='https://placehold.co/55'"></div>
        <div class="nav-content" id="categoriesNavContainer"></div>
        <div class="sidebar-footer">
            <div class="nav-link" onclick="UI.dark()" style="margin-bottom: 8px;"><i class="fas fa-adjust"></i> الوضع الليلي</div>
            <div class="admin-open-btn" onclick="UI.triggerHubWithAuth()"><i class="fas fa-user-shield"></i> لوحة إدارة المحتوى</div>
        </div>
    </aside>

    <main class="main-content hidden" id="mainAppZone">
        <h3 id="pageTitle" style="margin-bottom:22px; color: var(--primary); border-right: 6px solid var(--accent); padding-right: 12px; font-weight: 900; font-size: 20px;">قسم: معلم حاسوب</h3>
        <div style="background:var(--white); padding:14px; border-radius:16px; display:flex; gap:12px; margin-bottom:25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); align-items: center; border: 1px solid rgba(0,0,0,0.02);">
            <i class="fas fa-search" style="color:#b3b3b3; font-size: 16px;"></i>
            <input type="text" id="search" onkeyup="App.search()" placeholder="بحث سريع عن المقررات والملفات الـ PDF الحقيقية..." style="border:none; outline:none; flex:1; font-size: 14px; background:transparent; color: var(--text);">
        </div>
        <div id="renderZone"></div>
    </main>

    <div class="sub-popup-overlay" id="phpLoginPopup">
        <div class="sub-popup-card" style="max-width:380px; text-align:center;">
            <div class="hub-icon" style="margin:0 auto 15px;"><i class="fas fa-lock"></i></div>
            <h3 style="color:var(--accent); font-size:16px; margin-bottom:8px;">منطقة حماية نظام الكلية</h3>
            <p style="font-size:12px; color:#bbb; margin-bottom:20px;">الرجاء إدخال الرمز السري المخصص للمسؤول لفتح اللوحة</p>
            <form method="POST" action="">
                <input type="hidden" name="action" value="admin_login">
                <input type="password" name="pin_code" class="admin-panel-input" placeholder="ادخل رمز الحماية هنا..." required style="text-align:center; font-size:16px; letter-spacing:4px;">
                <div style="display:flex; gap:12px;">
                    <button type="submit" style="flex:1; padding:12px; background:var(--primary); border:1px solid var(--accent); color:white; border-radius:12px; font-weight:bold; cursor:pointer;">تأكيد الدخول</button>
                    <button type="button" onclick="document.getElementById('phpLoginPopup').classList.remove('active')" style="padding:12px 20px; background:var(--danger-dark); color:white; border:none; border-radius:12px; font-weight:bold; cursor:pointer;">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <div class="admin-fullscreen-overlay" id="adminHubPanel">
        <div class="admin-hub-card">
            <h3 style="text-align: center; color: var(--accent); font-size: 17px; font-weight: 900; margin-bottom: 4px;"><i class="fas fa-university"></i> بوابة إدارة محتوى الكلية</h3>
            <p style="text-align: center; font-size: 12px; color: #bbb; margin-bottom: 25px;">تحكم كامل في المقررات، التخصصات، وإعدادات الخادم السحابي</p>

            <div class="hub-box" onclick="UI.openGate('books')">
                <div class="hub-icon"><i class="fas fa-book"></i></div>
                <div class="hub-info">
                    <h4>مستطيل الكتب والمقررات التعليمية</h4>
                    <p>أدوات رفع المقررات الجديدة، تخصيص المستويات، واختيار الترم الدراسي السحابي.</p>
                </div>
                <i class="fas fa-chevron-left" style="color: #888; font-size: 13px;"></i>
            </div>

            <div class="hub-box" onclick="UI.openGate('categories')">
                <div class="hub-icon"><i class="fas fa-folder-open"></i></div>
                <div class="hub-info">
                    <h4>مستطيل الأقسام والتهيئة الأكاديمية</h4>
                    <p>أدوات بناء تخصصات جديدة للكلية، معالجة الأسماء المضافة، أو المسح النهائي.</p>
                </div>
                <i class="fas fa-chevron-left" style="color: #888; font-size: 13px;"></i>
            </div>

            <div class="hub-box" onclick="UI.openGate('security')">
                <div class="hub-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="hub-info">
                    <h4>مستطيل الأمان وتحديث الرمز السري</h4>
                    <p>عرض تفاصيل الأمان للمسؤول، قفل الجلسات الحالية وتحديث رمز الدخول للكلية.</p>
                </div>
                <i class="fas fa-chevron-left" style="color: #888; font-size: 13px;"></i>
            </div>

            <button onclick="UI.closeHubPanel()" style="width:100%; margin-top:12px; padding:14px; background:var(--danger-dark); color:white; border:none; border-radius:14px; font-weight:bold; cursor:pointer; font-size: 14px;"><i class="fas fa-times-circle"></i> خروج وإغلاق لوحة التحكم</button>
        </div>
    </div>

    <div class="sub-popup-overlay" id="gatePopupBooks">
        <div class="sub-popup-card">
            <h3 style="color: var(--accent); font-size: 16px; margin-bottom: 18px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 12px;"><i class="fas fa-plus-circle"></i> أدوات إضافة ورفع كتاب جديد</h3>
            <label style="font-size:12px; color:#ddd; display:block; margin-bottom:6px;">اسم الملف التعليمي بالعربي:</label>
            <input type="text" id="inTitle" class="admin-panel-input" placeholder="مثال: علم الحاسوب وتحليل النظم">
            <label style="font-size:12px; color:#ddd; display:block; margin-bottom:6px;">القسم والأكاديمي المستهدف:</label>
            <select id="inDept" class="admin-panel-input"></select>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label style="font-size:12px; color:#ddd; display:block; margin-bottom:6px;">المستوى الدراسي:</label>
                    <select id="inLvl" class="admin-panel-input"><option value="1">مستوى 1</option><option value="2">مستوى 2</option><option value="3">مستوى 3</option><option value="4">مستوى 4</option></select>
                </div>
                <div>
                    <label style="font-size:12px; color:#ddd; display:block; margin-bottom:6px;">الترم الحالي:</label>
                    <select id="inSem" class="admin-panel-input"><option value="الفصل الأول">ترم 1</option><option value="الفصل الثاني">ترم 2</option></select>
                </div>
            </div>
            <label style="font-size:12px; color:#ddd; display:block; margin-bottom:6px;">اختر مستند الـ PDF الحقيقي للمقرر:</label>
            <input type="file" id="inFile" class="admin-panel-input" accept=".pdf">
            <div style="display:flex; gap:12px; margin-top:12px;">
                <button onclick="App.upload()" id="upBtnText" style="flex:1; padding:14px; background:var(--success); color:white; border:none; border-radius:12px; font-weight:bold; cursor:pointer; font-size:14px;">حفظ ورفع الكتاب سحابياً</button>
                <button onclick="UI.closeGate('books')" style="padding:14px 22px; background:var(--danger-dark); color:white; border:none; border-radius:12px; font-weight:bold; cursor:pointer; font-size:14px;">إلغاء</button>
            </div>
        </div>
    </div>

    <div class="sub-popup-overlay" id="gatePopupCategories">
        <div class="sub-popup-card">
            <h3 style="color: var(--accent); font-size: 16px; margin-bottom: 18px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 12px;"><i class="fas fa-folder-plus"></i> تهيئة ومعالجة الأقسام الأكاديمية</h3>
            <label style="font-size:12px; color:#ddd; display:block; margin-bottom:6px;">اسم القسم الجديد (أو الاسم المعدل المعوض):</label>
            <input type="text" id="newCategoryName" class="admin-panel-input" placeholder="اكتب اسم التخصص المراد إدخاله...">
            <label style="font-size:12px; color:#ddd; display:block; margin-bottom:6px;">اختر قسماً حالياً (لإجراء تعديل أو حذف عليه):</label>
            <select id="selectEditCategory" class="admin-panel-input" onchange="document.getElementById('newCategoryName').value = this.value">
                <option value="">-- اختر قسماً من القائمة لتعديله أو حذفه --</option>
            </select>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:15px;">
                <button onclick="App.addCategory()" style="padding:14px; background:var(--primary); border:1px solid var(--accent); color:#fff; border-radius:12px; font-weight:bold; cursor:pointer; font-size:13px;"><i class="fas fa-plus"></i> إضافة كقسم جديد</button>
                <button onclick="App.updateCategory()" style="padding:14px; background:var(--warning-dark); color:white; border:none; border-radius:12px; font-weight:bold; cursor:pointer; font-size:13px;"><i class="fas fa-edit"></i> تعديل اسم المختار</button>
            </div>
            <button onclick="App.deleteCategory()" style="width:100%; padding:14px; background:var(--danger-dark); color:white; border:none; border-radius:12px; font-weight:bold; cursor:pointer; margin-top:12px; font-size:14px;"><i class="fas fa-trash-alt"></i> حذف القسم المختار نهائياً</button>
            <button onclick="UI.closeGate('categories')" style="width:100%; margin-top:18px; padding:12px; background:var(--danger-dark); color:white; border:none; border-radius:12px; font-weight:bold; cursor:pointer; font-size:13px;">خروج وإلغاء</button>
        </div>
    </div>

    <div class="sub-popup-overlay" id="gatePopupSecurity">
        <div class="sub-popup-card">
            <h3 style="color: var(--accent); font-size: 16px; margin-bottom: 18px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 12px;"><i class="fas fa-lock"></i> إعدادات الأمان وتحديث الرمز</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="change_pin">
                <label style="font-size:12px; color:#ddd; display:block; margin-bottom:6px;">تحديث الرمز السري الجديد م. بشار:</label>
                <input type="password" name="new_pin_code" id="newPin" class="admin-panel-input" placeholder="ادخل الرمز الجديد (لا يقل عن 3 رموز)" required>
                <button type="submit" style="width:100%; padding:14px; background:var(--success); color:white; border:none; border-radius:12px; font-weight:bold; cursor:pointer; font-size:14px; margin-bottom:12px;">حفظ وتحديث الرمز الحالي</button>
            </form>
            <button onclick="UI.lockAdmin()" style="width:100%; padding:12px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:12px; cursor:pointer; font-size:13px; font-weight:bold;"><i class="fas fa-sign-out-alt"></i> قفل الجلسة وتسجيل الخروج فوراً</button>
            <button onclick="UI.closeGate('security')" style="width:100%; margin-top:22px; padding:12px; background:var(--danger-dark); color:white; border:none; border-radius:12px; font-weight:bold; cursor:pointer; font-size:13px;">خروج</button>
        </div>
    </div>

    <div class="sub-popup-overlay" id="subActionPopup">
        <div style="background:var(--white); padding:22px; border-radius:24px; width:100%; max-width:420px; color:var(--text); box-shadow:0 15px 40px rgba(0,0,0,0.3);" id="subPopupContent"></div>
    </div>

    <script>
        const SB_URL = 'https://spvimxujtgxmsnrecmiy.supabase.co';
        const SB_KEY = 'sb_publishable_cUPYqtatGGwLPHOnVVBdaw_AkzX95mQ';
        const sb = supabase.createClient(SB_URL, SB_KEY);
        let rawData = [];
        let dynamicCategories = ['معلم حاسوب', 'معلم لغة عربية', 'معلم لغة إنجليزية', 'معلم رياضيات', 'قسم الكيمياء', 'مراجع عامة'];
        let currentDept = 'معلم حاسوب';
        
        // فحص حالة التحقق من خلال المتصفح بشكل كامل لمنع تضارب الـ Session اللحظي
        let isAdminAuthenticated = (<?php echo $isAdmin; ?> || localStorage.getItem('bashar_admin_auth') === 'true');

        const App = {
            init: () => { 
                document.getElementById('splash').style.display='none'; 
                document.getElementById('mainAppZone').classList.remove('hidden');
                document.getElementById('appTopBar').classList.remove('hidden');
                document.getElementById('mobileDrawerTab').classList.remove('hidden');
                if(localStorage.getItem('dark')==='on') document.body.classList.add('dark-mode');
                
                App.loadCategories(); 
                App.load(); 
                
                // هنا المعالجة الذكية: إذا كان الرابط يحتوي على معطى النجاح، نثبت حالة الدخول فوراً بدون إغلاق
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('login') === 'success') {
                    localStorage.setItem('bashar_admin_auth', 'true');
                    isAdminAuthenticated = true;
                }

                if(isAdminAuthenticated) {
                    UI.openHubPanel();
                }
            },
            loadCategories: async () => {
                try {
                    const { data } = await sb.from('categories').select('name');
                    if(data && data.length > 0) {
                        data.forEach(c => { if(!dynamicCategories.includes(c.name)) dynamicCategories.push(c.name); });
                    }
                    App.refreshCategoriesUI();
                } catch(e) { App.refreshCategoriesUI(); }
            },
            refreshCategoriesUI: () => {
                const navContainer = document.getElementById('categoriesNavContainer');
                const selectDept = document.getElementById('inDept');
                const selectEditCat = document.getElementById('selectEditCategory');
                const icons = { 'معلم حاسوب': 'fa-laptop-code', 'معلم لغة عربية': 'fa-book-open', 'معلم لغة إنجليزية': 'fa-language', 'معلم رياضيات': 'fa-calculator', 'قسم الكيمياء': 'fa-flask', 'مراجع عامة': 'fa-book' };

                navContainer.innerHTML = dynamicCategories.map(cat => {
                    const icon = icons[cat] || 'fa-folder-open';
                    return `<div class="nav-link ${cat === currentDept ? 'active' : ''}" onclick="UI.switch('${cat}', this)"><i class="fas ${icon}"></i> ${cat.includes('معلم') || cat.includes('مراجع') ? cat : 'قسم ' + cat}</div>`;
                }).join('');

                selectDept.innerHTML = dynamicCategories.map(cat => `<option value="${cat}">${cat}</option>`).join('');
                selectEditCat.innerHTML = `<option value="">-- اختر قسماً من القائمة لتعديله أو حذفه --</option>` + dynamicCategories.map(cat => `<option value="${cat}">${cat}</option>`).join('');
            },
            load: async () => {
                const { data } = await sb.from('books').select('*').order('created_at', { ascending: false });
                rawData = data || [];
                App.render();
            },
            render: (q = "") => {
                const zone = document.getElementById('renderZone'); zone.innerHTML = "";
                let items = rawData.filter(i => (i.dept === currentDept) && i.title.toLowerCase().includes(q.toLowerCase()));
                
                if(currentDept === 'مراجع عامة') {
                    zone.innerHTML = `<div style="margin-top:10px;">${App.rows(items)}</div>`;
                } else {
                    for(let l=1; l<=4; l++) {
                        const lvlData = items.filter(i => i.level_name == l);
                        if(lvlData.length > 0) {
                            let h = `<div style="margin-bottom:25px;"><div style="background:var(--accent); color:var(--primary); padding:6px 14px; border-radius:10px; display:inline-block; font-weight:900; font-size:12px; box-shadow: 0 2px 8px rgba(212,163,115,0.25);">المستوى ${l}</div>`;
                            ['الفصل الأول', 'الفصل الثاني'].forEach(sem => {
                                const semData = lvlData.filter(s => s.category === sem);
                                if(semData.length > 0) { h += `<div style="margin: 12px 6px 6px; font-size:13px; font-weight:bold; opacity:0.65;"><i class="far fa-clock"></i> ${sem}</div>${App.rows(semData)}`; }
                            });
                            zone.innerHTML += h + `</div>`;
                        }
                    }
                }
            },
            rows: (list) => {
                return list.map(b => {
                    let adminActions = isAdminAuthenticated ? `
                        <button onclick="UI.openEditPopup('${b.id}', '${b.title}', '${b.dept}', '${b.level_name}', '${b.category}')" class="btn-admin-action" style="background:var(--warning-dark);" title="تعديل"><i class="fas fa-edit"></i> تعديل</button>
                        <button onclick="UI.openDeletePopup('${b.id}', '${b.title}')" class="btn-admin-action" style="background:var(--danger-dark);" title="حذف"><i class="fas fa-trash-alt"></i> حذف</button>
                    ` : '';
                    return `
                    <div class="doc-row">
                        <div class="doc-title"><i class="fas fa-file-pdf" style="color:#e74c3c; margin-left:8px;"></i> ${b.title}</div>
                        <div class="doc-actions">${adminActions}<button onclick="window.open('${b.pdf_url}', '_blank')" class="btn-sm" style="background:var(--primary)"><i class="fas fa-eye"></i> قراءة</button><a href="${b.pdf_url}" download="${b.title}.pdf" class="btn-sm" style="background:var(--success)"><i class="fas fa-download"></i> تحميل</a></div>
                    </div>`;
                }).join('');
            },
            search: () => App.render(document.getElementById('search').value),
            
            upload: async () => {
                const file = document.getElementById('inFile').files[0]; const title = document.getElementById('inTitle').value.trim();
                if(!file || !title) return alert("الرجاء كتابة اسم الكتاب واختيار مستند الـ PDF أولاً!");
                const txt = document.getElementById('upBtnText'); txt.innerText = "جاري الرفع والمزامنة السحابية...";
                try {
                    const fileExt = file.name.split('.').pop(); const techName = `lib_${Date.now()}.${fileExt}`;
                    await sb.storage.from('pdfs').upload(techName, file);
                    const { data: link } = sb.storage.from('pdfs').getPublicUrl(techName);
                    await sb.from('books').insert([{ title, level_name: document.getElementById('inLvl').value, category: document.getElementById('inSem').value, pdf_url: link.publicUrl, dept: document.getElementById('inDept').value }]);
                    alert("تم حفظ ورفع المقرر التعليمي بنجاح تام للمخزن السحابي!"); location.reload();
                } catch(e) { alert("حدث خطأ بالرفع السحابي!"); txt.innerText = "حفظ ورفع الكتاب سحابياً"; }
            },
            executeBookUpdate: async (id) => {
                const updatedTitle = document.getElementById('subBookTitle').value.trim();
                const updatedDept = document.getElementById('subBookDept').value;
                const updatedLvl = document.getElementById('subBookLvl').value;
                const updatedSem = document.getElementById('subBookSem').value;
                if(!updatedTitle) return alert("لا يمكن ترك الحقل فارغاً!");
                try {
                    await sb.from('books').update({ title: updatedTitle, dept: updatedDept, level_name: updatedLvl, category: updatedSem }).eq('id', id);
                    alert("تم تحديث بيانات المقرر السحابية بنجاح!"); UI.closeSubPopup(); location.reload();
                } catch(e) { alert("خطأ في التحديث!"); }
            },
            executeBookDelete: async (id) => {
                try {
                    await sb.from('books').delete().eq('id', id);
                    alert("تم حذف الملف نهائياً!"); UI.closeSubPopup(); location.reload();
                } catch(e) { alert("فشلت عملية الحذف!"); }
            },
            addCategory: async () => {
                const newCat = document.getElementById('newCategoryName').value.trim();
                if(!newCat) return alert("اكتب اسم القسم أولاً داخل حقل النص!");
                if(dynamicCategories.includes(newCat)) return alert("هذا القسم التعليمي موجود بالمنصة بالفعل!");
                try {
                    await sb.from('categories').insert([{ name: newCat }]); dynamicCategories.push(newCat);
                    App.refreshCategoriesUI(); document.getElementById('newCategoryName').value = "";
                    alert(`تمت إضافة تخصص "${newCat}" بنجاح للكلية!`);
                } catch(e) { alert("حدث خطأ بالرفع السحابي للقسم."); }
            },
            updateCategory: async () => {
                const oldCat = document.getElementById('selectEditCategory').value;
                const newCatName = document.getElementById('newCategoryName').value.trim();
                if(!oldCat || !newCatName) return alert("اختر قسماً من القائمة واكتب اسمه المعدل لتحديثه!");
                try {
                    await sb.from('categories').update({ name: newCatName }).eq('name', oldCat);
                    await sb.from('books').update({ dept: newCatName }).eq('dept', oldCat);
                    alert("تم تعديل الاسم للقسم وتحديث كل الملازم والكتب المربوطة به بنجاح!"); location.reload();
                } catch(e) { alert("فشلت معالجة تحديث اسم القسم."); }
            },
            deleteCategory: async () => {
                const catToDelete = document.getElementById('selectEditCategory').value;
                if(!catToDelete) return alert("الرجاء اختيار التخصص المطلوب حذفه من القائمة أولاً!");
                if(!confirm(`هل أنت متأكد تماماً من حذف قسم "${catToDelete}" نهائياً من الكلية؟`)) return;
                try {
                    await sb.from('categories').delete().eq('name', catToDelete);
                    dynamicCategories = dynamicCategories.filter(c => c !== catToDelete); App.refreshCategoriesUI();
                    document.getElementById('newCategoryName').value = ""; alert("تم مسح وإلغاء التخصص من النظام كلياً بنجاح.");
                } catch(e) { alert("خطأ في الحذف السحابي."); }
            }
        };

        const UI = {
            menu: (s) => {
                const side = document.getElementById('sidebar'); const over = document.getElementById('uiOverlay');
                const pullTab = document.getElementById('mobileDrawerTab');
                if(s) { 
                    side.classList.add('active'); over.classList.add('active'); 
                    pullTab.classList.add('sidebar-open');
                }
                else { 
                    side.classList.remove('active'); over.classList.remove('active'); 
                    pullTab.classList.remove('sidebar-open');
                }
            },
            switch: (n, el) => { currentDept = n; document.querySelectorAll('.nav-link').forEach(a=>a.classList.remove('active')); el.classList.add('active'); document.getElementById('pageTitle').innerText = "قسم: "+n; App.render(); UI.menu(false); },
            
            triggerHubWithAuth: () => {
                if (isAdminAuthenticated) {
                    UI.openHubPanel();
                } else {
                    UI.menu(false);
                    document.getElementById('phpLoginPopup').classList.add('active');
                }
            },

            openHubPanel: () => { document.getElementById('adminHubPanel').classList.add('active'); UI.menu(false); },
            closeHubPanel: () => { document.getElementById('adminHubPanel').classList.remove('active'); },
            
            openGate: (gateName) => {
                document.getElementById(`gatePopup${gateName.charAt(0).toUpperCase() + gateName.slice(1)}`).classList.add('active');
            },
            closeGate: (gateName) => {
                document.getElementById(`gatePopup${gateName.charAt(0).toUpperCase() + gateName.slice(1)}`).classList.remove('active');
            },

            openEditPopup: (id, title, dept, level, category) => {
                const subZone = document.getElementById('subPopupContent');
                subZone.innerHTML = `
                    <h4 style="margin-bottom:14px; color:var(--primary); font-size:15px; font-weight:900;"><i class="fas fa-edit"></i> تعديل بيانات الكتاب الفردي</h4>
                    <input type="text" id="subBookTitle" class="admin-panel-input" style="color:#000; background:#fff; border:1px solid #ccc;" value="${title}">
                    <select id="subBookDept" class="admin-panel-input" style="color:#000; background:#fff; border:1px solid #ccc;">
                        ${dynamicCategories.map(c => `<option value="${c}" ${c===dept ? 'selected':''}>${c}</option>`).join('')}
                    </select>
                    <select id="subBookLvl" class="admin-panel-input" style="color:#000; background:#fff; border:1px solid #ccc;">
                        <option value="1" ${level==="1"?'selected':''}>مستوى 1</option><option value="2" ${level==="2"?'selected':''}>مستوى 2</option>
                        <option value="3" ${level==="3"?'selected':''}>مستوى 3</option><option value="4" ${level==="4"?'selected':''}>مستوى 4</option>
                    </select>
                    <select id="subBookSem" class="admin-panel-input" style="color:#000; background:#fff; border:1px solid #ccc;">
                        <option value="الفصل الأول" ${category==="الفصل الأول"?'selected':''}>ترم 1</option>
                        <option value="الفصل الثاني" ${category==="الفصل الثاني"?'selected':''}>ترم 2</option>
                    </select>
                    <div style="display:flex; gap:12px; margin-top:15px;">
                        <button onclick="App.executeBookUpdate('${id}')" style="flex:1; padding:12px; background:var(--success); color:white; border:none; border-radius:10px; font-weight:bold; cursor:pointer;">حفظ التعديل</button>
                        <button onclick="UI.closeSubPopup()" style="flex:1; padding:12px; background:var(--danger-dark); color:white; border:none; border-radius:10px; font-weight:bold; cursor:pointer;">إلغاء</button>
                    </div>
                `;
                document.getElementById('subActionPopup').classList.add('active');
            },
            openDeletePopup: (id, title) => {
                const subZone = document.getElementById('subPopupContent');
                subZone.innerHTML = `
                    <h4 style="margin-bottom:12px; color:var(--danger-dark); font-size:15px; font-weight:900;"><i class="fas fa-exclamation-triangle"></i> تأكيد الحذف النهائي</h4>
                    <p style="font-size:13px; margin-bottom:18px; color:#555;">هل أنت متأكد تماماً من حذف المقرّر: <b style="color:#000;">${title}</b> نهائياً من السيرفر السحابي للمكتبة؟</p>
                    <div style="display:flex; gap:12px;">
                        <button onclick="App.executeBookDelete('${id}')" style="flex:1; padding:12px; background:var(--danger-dark); color:white; border:none; border-radius:10px; font-weight:bold; cursor:pointer;">نعم، حذف</button>
                        <button onclick="UI.closeSubPopup()" style="flex:1; padding:12px; background:#7f8c8d; color:white; border:none; border-radius:10px; font-weight:bold; cursor:pointer;">تراجع</button>
                    </div>
                `;
                document.getElementById('subActionPopup').classList.add('active');
            },
            closeSubPopup: () => { document.getElementById('subActionPopup').classList.remove('active'); },
            
            lockAdmin: () => { 
                alert("جاري قفل لوحة المسؤول بأمان سحابي...");
                window.location.href = "?action=logout";
            },
            dark: () => { const isD = document.body.classList.toggle('dark-mode'); localStorage.setItem('dark', isD ? 'on' : 'off'); }
        };
    </script>
</body>
</html>