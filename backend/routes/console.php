<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('portfolios:sync')->everyThirtyMinutes();
