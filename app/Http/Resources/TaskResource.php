<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'order' => $this->order,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'completed_at' => $this->completed_at?->toISOString(),
            'workspace_id' => $this->workspace_id,
            'sprint_id' => $this->sprint_id,
            'assigned_to' => $this->assignedTo ? new UserResource($this->assignedTo) : null,

            'created_by' => $this->when($this->relationLoaded('createdBy'), function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                    'email' => $this->createdBy->email,
                ];
            }),

            'sprint' => $this->whenLoaded('sprint', function () {
                return $this->sprint ? [
                    'id' => $this->sprint->id,
                    'name' => $this->sprint->name,
                    'status' => $this->sprint->status,
                ] : null;
            }),

            'notes' => $this->when(
                $this->notes && $request->user()?->id === $this->created_by,
                $this->notes
            ),

            'comments_count' => $this->whenCounted('comments'),

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            'is_overdue' => $this->isOverdue(),
            'is_completed' => $this->status === 'done',
        ];
    }
}
