<?php

require_once 'xml_handlers/actionable_build_interface.php';
require_once 'include/Messaging/MessageInterface.php';
require_once 'include/messaging/Message.php';
require_once 'include/Messaging/Email/EmailMessage.php';
require_once 'include/Messaging/Email/EmailDigestMessage.php';
require_once 'include/Messaging/MessageFactory.php';
require_once 'models/project.php';
require_once 'models/build.php';
require_once 'models/buildgroup.php';
require_once 'include/Messaging/Email/Decorator/EmailDecoratorInterface.php';
require_once 'include/Messaging/Email/Decorator/EmailDecorator.php';
require_once 'include/Messaging/Email/Decorator/BuildFailuresEmailDecorator.php';
require_once 'include/Messaging/Email/Decorator/BuildWarningsEmailDecorator.php';
require_once 'include/Messaging/Email/Decorator/ConfigureErrorsEmailDecorator.php';
require_once 'include/Messaging/Email/Decorator/DynamicAnalysisEmailDecorator.php';
require_once 'include/Messaging/Email/Decorator/TestFailuresEmailDecorator.php';
require_once 'include/Messaging/Email/Decorator/TestWarningsEmailDecorator.php';
require_once 'include/Messaging/Email/Decorator/UpdateErrorsEmailDecorator.php';
