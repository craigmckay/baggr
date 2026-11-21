<?php

namespace App\Exceptions;

use DomainException;

/**
 * A change to a group that its rules don't allow, such as removing the last
 * admin. The message is written to be shown to the person who tried.
 */
class GroupRuleException extends DomainException {}
