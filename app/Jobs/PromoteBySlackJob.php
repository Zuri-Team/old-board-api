<?php

namespace App\Jobs;

use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Slack;

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
    public function handle(Slack $slack)
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
                    $count += 1;
                    $slack::removeFromChannel($slack_id, $currentStage);
                    $slack::addToChannel($slack_id, $this->stage);
                    $user->stage = $this->stage;
                    $user->save();
                }
            }
        }
        $client = new \GuzzleHttp\Client();

        $text = $count . " user(s) demoted successfully. " . (count($this->users) - $count) . " failed";
        $response = $client->request('POST', $this->url, array(
            'json' => [
                "response_type" => "ephemeral",
                "text" => $text,
            ],
        ));
        Log::info($response);

    }
}