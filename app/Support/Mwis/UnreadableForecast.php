<?php

namespace App\Support\Mwis;

use RuntimeException;

/** An MWIS page that couldn't be read, most likely because its layout has changed. */
class UnreadableForecast extends RuntimeException {}
