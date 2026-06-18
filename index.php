<?php
// ==========================================
// 1. نظام إدارة الجلسة والأمان (خلفية السيرفر)
// ==========================================
session_start();

// رمز التحقق الموحد لحماية لوحة إدارة المحتوى
define('ADMIN_PASSWORD', '123456'); 

// معالجة تسجيل الدخول الآمن لمنع إعادة التحميل العشوائي
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_password'])) {
    if ($_POST['auth_password'] === ADMIN_PASSWORD) {
        $_SESSION['is_admin'] = true;
        // إعادة التوجيه الفوري لنفس الصفحة لتثبيت الجلسة بأمان
        header("Location: index.php");
        exit();
    } else {
        $error_message = "الرمز السري غير صحيح، يرجى المحاولة مرة أخرى.";
    }
}

// معالجة تسجيل الخروج وقفل الجلسة السحابية
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();
    session_destroy();
    header("Location: index.php");
    exit();
}

// text تصدير حالة المسؤول إلى بيئة JavaScript
$isAdmin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المكتبة الإلكترونية - نظام إدارة المحتوى المحمي</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ========================================== */
        /* 2. التنسيقات والواجهات الرسومية المتكاملة */
        /* ========================================== */
        :root {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --accent: #8a2be2;
            --accent-hover: #a046f5;
            --text-main: #ffffff;
            --text-muted: #aaaaaa;
            --border-color: #333333;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        /* شريط التنقل العلوي والجوال */
        .navbar {
            background-color: var(--bg-secondary);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }

        .nav-logo {
            font-size: 18px;
            font-weight: bold;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* زر إدارة المحتوى في القائمة الجانبية */
        .admin-open-btn {
            background-color: transparent;
            color: var(--accent);
            border: 1px solid var(--accent);
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .admin-open-btn:hover {
            background-color: var(--accent);
            color: white;
        }

        /* نافذة تسجيل الدخول المنبثقة المبرمجة حديثاً للـ PHP */
        .sub-popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.85);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .sub-popup-content {
            background-color: var(--bg-secondary);
            padding: 30px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            width: 90%;
            max-width: 400px;
            text-align: center;
            position: relative;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
        }

        .sub-popup-content h3 {
            margin-top: 0;
            color: var(--text-main);
            margin-bottom: 20px;
        }

        .sub-popup-content input[type="password"] {
            width: 100%;
            padding: 12px;
            background-color: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 5px;
            color: white;
            box-sizing: border-box;
            text-align: center;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .sub-popup-content .btn-submit {
            background-color: var(--accent);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }

        .sub-popup-content .btn-submit:hover {
            background-color: var(--accent-hover);
        }

        .close-popup {
            position: absolute;
            top: 10px;
            left: 10px;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 20px;
        }

        /* لوحة التحكم الرئيسية والمربعات الثلاثة المحدثة */
        .hub-panel {
            display: none;
            position: fixed;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background-color: var(--bg-primary);
            z-index: 5000;
            padding: 40px 20px;
            box-sizing: border-box;
            overflow-y: auto;
        }

        .hub-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
        }

        .hub-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .hub-box {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s, border-color 0.2s;
        }

        .hub-box:hover {
            transform: translateY(-5px);
            border-color: var(--accent);
        }

        .hub-box i {
            font-size: 40px;
            color: var(--accent);
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="nav-logo">
            <i class="fas fa-book-reader"></i> المكتبة الرقمية للأقسام التقنية
        </div>
        <div>
            <div class="admin-open-btn" onclick="UI.triggerHubWithAuth()">
                <i class="fas fa-user-shield"></i> لوحة إدارة المحتوى
            </div>
        </div>
    </nav>

    <div style="padding: 40px; text-align: center; margin-top: 50px;">
        <h2>مرحباً بك في المنصة السحابية للمكتبة الإلكترونية</h2>
        <p style="color: var(--text-muted); max-width: 600px; margin: 20px auto; line-height: 1.6;">هذه الواجهة مخصصة لاستعراض الكتب المتاحة لقسم مدرسة الحاسوب بباجل.</p>
    </div>

    <div class="sub-popup-overlay" id="phpLoginPopup">
        <div class="sub-popup-content">
            <span class="close-popup" onclick="document.getElementById('phpLoginPopup').style.display='none'">&times;</span>
            <h3>بوابة الوصول الآمنة</h3>
            
            <form method="POST" action="index.php">
                <input type="password" name="auth_password" placeholder="أدخل رمز التحقق للمسؤول" required autocomplete="off">
                <button type="submit" class="btn-submit">تأكيد الهوية والدخول</button>
            </form>
        </div>
    </div>

    <div class="hub-panel" id="hubPanelContainer">
        <div class="hub-header">
            <h2><i class="fas fa-toolbox"></i> خيارات لوحة تحكم المسؤول</h2>
            <div>
                <button onclick="window.location.href='?action=logout'" style="background-color: #d32f2f; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">
                    <i class="fas fa-sign-out-alt"></i> قفل الجلسة والخروج
                </button>
                <button onclick="UI.closeHubPanel()" style="background: none; border: 1px solid var(--text-muted); color: var(--text-main); padding: 8px 15px; border-radius: 5px; margin-right: 10px; cursor: pointer;">إغلاق</button>
            </div>
        </div>

        <div class="hub-grid">
            <div class="hub-box" onclick="UI.openGate('books')">
                <i class="fas fa-book"></i>
                <h3>إدارة الكتب الرقمية</h3>
                <p style="color: var(--text-muted); font-size: 13px;">إضافة، حذف، وتحديث مؤلفات المكتبة</p>
            </div>
            <div class="hub-box" onclick="UI.openGate('categories')">
                <i class="fas fa-tags"></i>
                <h3>إدارة الأقسام والتبويبات</h3>
                <p style="color: var(--text-muted); font-size: 13px;">توزيع الكتب حسب التخصصات والمستويات</p>
            </div>
            <div class="hub-box" onclick="UI.openGate('security')">
                <i class="fas fa-shield-alt"></i>
                <h3>لوحة الأمان ومراقبة النظام</h3>
                <p style="color: var(--text-muted); font-size: 13px;">تغيير رموز الدخول ومراجعة سجلات الخادم</p>
            </div>
        </div>
    </div>

    <script>
        // استقبال حالة التوثيق المباشرة من خادم PHP السحابي
        let isAdminAuthenticated = <?php echo $isAdmin; ?>;
        
        // كائن التحكم الرئيسي بالواجهات
        const UI = {
            // دالة المطبخ البرمجي لإدارة البوابة الموحدة
            triggerHubWithAuth: function() {
                if (isAdminAuthenticated) {
                    UI.openHubPanel();
                } else {
                    document.getElementById('phpLoginPopup').style.display = 'flex';
                }
            },

            // فتح وإظهار اللوحة الأساسية
            openHubPanel: function() {
                document.getElementById('hubPanelContainer').style.display = 'block';
            },

            // إغلاق لوحة التحكم
            closeHubPanel: function() {
                document.getElementById('hubPanelContainer').style.display = 'none';
            },

            // الدالة المصححة للتحكم بالمربعات الثلاثة دون الخروج المستمر
            openGate: function(gateType) {
                if (!isAdminAuthenticated) {
                    alert("وصول مرفوض! انتهت الجلسة أو لم يتم التحقق بنجاح.");
                    UI.triggerHubWithAuth();
                    return;
                }

                // فتح الخيارات المحددة بسلاسة داخل اللوحة دون إعادة تحميل الصفحة
                if (gateType === 'books') {
                    alert("مرحباً بك في وحدة إدارة الكتب. النظام جاهز للعمل والربط بقاعدة البيانات سحابياً.");
                } else if (gateType === 'categories') {
                    alert("مرحباً بك في وحدة إدارة الأقسام. يمكنك تنظيم التبويبات الفنية.");
                } else if (gateType === 'security') {
                    alert("مرحباً بك في لوحة أمان النظام وسجلات خادم ريندر.");
                }
            }
        };

        // فحص تلقائي عند تشغيل الصفحة: إذا نجح التوثيق بالـ PHP تفتح اللوحة فوراً بثبات
        window.addEventListener('DOMContentLoaded', () => {
            <?php if(isset($error_message)): ?>
                alert("<?php echo $error_message; ?>");
                document.getElementById('phpLoginPopup').style.display = 'flex';
            <?php endif; ?>

            if (isAdminAuthenticated) {
                UI.openHubPanel();
            }
        });
    </script>
</body>
</html>