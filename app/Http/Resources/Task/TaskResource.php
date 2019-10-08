<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        //return parent::toArray($request);

        return [
            'id' => $this->id,
            'track_id' => $this->track_id,
            'title' => $this->title,
            'body' => $this->body,
            'deadline' => $this->deadline,
            'is_active' => $this->is_active,
        ];
    }
}
