<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('webhooks:trigger')->everyMinute();
