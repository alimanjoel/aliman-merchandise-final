<?php
declare(strict_types=1);
require_once dirname(__DIR__) . "/app/bootstrap.php";
require_once ROOT . "/app/commerce.php";
require_once ROOT . "/app/services/integrations.php";

// Shared page bootstrap. Each helper file has one responsibility.
require_once ROOT . "/includes/helpers/formatting.php";
require_once ROOT . "/includes/helpers/navigation.php";
require_once ROOT . "/includes/helpers/auth.php";
require_once ROOT . "/includes/helpers/forms.php";
require_once ROOT . "/includes/helpers/ui.php";
require_once ROOT . "/includes/helpers/layout.php";
require_once ROOT . "/includes/helpers/errors.php";
