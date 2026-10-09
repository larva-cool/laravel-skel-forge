<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

// 所有定时任务已迁移到 schedules 表，由 AppServiceProvider::registerDynamicSchedules()
// 在运行时动态注册到 Laravel 调度器。
// 如需新增/修改定时任务，请通过后台「定时任务管理」页面操作。
