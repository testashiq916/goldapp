<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RotableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'model' => $this->model,
            'size' => $this->size,
            'weight1' => (float) $this->weight1,
            'weight2' => (float) $this->weight2,
            'minqty' => (int) $this->minqty,
            'maxqty' => (int) $this->maxqty,
        ];
    }
}

