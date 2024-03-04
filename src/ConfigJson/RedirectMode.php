<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

/**
 * Redirect mode.
 */
enum RedirectMode: int
{
    case NO = 0;
    case SHOULD = 1;
    case FORCE = 2;
}
