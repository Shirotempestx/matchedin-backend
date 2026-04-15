<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InAppNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        "user_id",
        "type",
        "title",
        "body",
        "action_url",
        "severity",
        "data",
        "read_at",
    ];

    protected function casts(): array
    {
        return [
            "data" => "array",
            "read_at" => "datetime",
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(["read_at" => now()])->save();
        }
    }

    protected static function booted(): void
    {
        static::creating(function (InAppNotification $notification) {
            $user = User::find($notification->user_id);
            if ($user && class_exists(\App\Notifications\SystemNotification::class)) {
                $user->notify(new \App\Notifications\SystemNotification(
                    type: $notification->type ?? "info",
                    title: $notification->title ?? "",
                    body: $notification->body ?? "",
                    actionUrl: $notification->action_url,
                    severity: $notification->severity ?? "info",
                    data: $notification->data ?? []
                ));
            }
            return false;
        });
    }
}
