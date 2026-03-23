<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:init-user')]
#[Description('Command description')]
class InitUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        //
    }
}
