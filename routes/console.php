<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('quotes:deliver')->everyMinute();
