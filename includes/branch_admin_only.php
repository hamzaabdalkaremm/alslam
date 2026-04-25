<?php

if (!class_exists('Auth')) {
    throw new RuntimeException('تعذر تحميل نظام الصلاحيات.');
}

Auth::requireLogin();

if (!Auth::isSuperAdmin()) {
    throw new RuntimeException('غير مصرح لك بإدارة الفروع.');
}
