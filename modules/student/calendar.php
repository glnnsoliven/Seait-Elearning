<?php
// Calendar has been merged into study_plan.php — redirect there
require_once __DIR__ . '/../../core/bootstrap.php';
redirect(BASE_URL . '/modules/student/study_plan.php?tab=calendar');
