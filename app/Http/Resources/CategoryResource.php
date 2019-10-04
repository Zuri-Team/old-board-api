<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => (int) $this->id,
            'category_name' => (string) $this->title,
            'dsecription' => (string) $this->description,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ];
    }
}