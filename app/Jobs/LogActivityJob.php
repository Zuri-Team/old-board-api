<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Activity;

class LogActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $logMessage;
    protected $type;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($logMessage, $type)
    {
        $this->logMessage = $logMessage;
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $res = Activity::create([
            'type' => $this->type,
            'user_id' => auth()->id(),
            'message' => $this->logMessage
        ]);

        return $res;
    }
}
