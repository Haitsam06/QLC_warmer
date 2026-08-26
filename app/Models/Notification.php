<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'link',
        'is_read',
    ];

    protected $casts = [
        'is_read'    => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public static function send(
        $userId,
        string $type,
        string $title,
        string $message,
        ?string $link = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'link'    => $link,
            'is_read' => false,
        ]);
    }

    public static function sendToRole(
        string $roleName,
        string $type,
        string $title,
        string $message,
        ?string $link = null
    ): void {
        $role = \App\Models\Role::where('role_name', $roleName)->first();
        if (! $role) return;

        $users = \App\Models\User::where('role_id', $role->id)->get(['id']);
        if ($users->isEmpty()) return;

        $now     = now();
        $inserts = $users->map(fn($u) => [
            'user_id'    => $u->id,
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'link'       => $link,
            'is_read'    => false,
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        self::insert($inserts);
    }
}