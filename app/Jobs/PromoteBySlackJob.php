<?php

namespace App\Jobs;

use App\Slack;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PromoteBySlackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $stage;
    protected $users;
    protected $url;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($stage, $users, $url)
    {
        $this->stage = $stage;
        $this->users = $users;
        $this->url = $url;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $count = 0;
        foreach ($this->users as $user) {
            $slack_id = explode('|', $user)[0];
            $user = User::where('slack_id', $slack_id)->first();
            if ($user) {
                $currentStage = intval($this->stage) - 1;
                // $nextStage = $currentStage + 1;
                if (intval($this->stage) < 1 || intval($this->stage) > 10 || $user->stage == $this->stage) {
                    continue;
                } else {
                    $user->stage = $this->stage;
                    if ($user->save()) {
                        $count += 1;
                        Slack::removeFromChannel($slack_id, $currentStage);
                        Slack::addToChannel($slack_id, $this->stage);
                    };
                }
            }
        }
        $client = new \GuzzleHttp\Client();

        $text = $count . " user(s) demoted successfully. " . (count($this->users) - $count) . " failed";
        $response = $client->post($this->url, array(
            'form_params' => [
                "text" => $text,
            ],
        ));
        Log::info($response);

    }
}