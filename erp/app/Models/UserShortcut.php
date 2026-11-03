<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One pinned page for one user. See the migration for why the row is shaped
 * the way it is; see layout/shortcuts.blade.php for how it is created.
 */
class UserShortcut extends Model
{
    public const KIND_TAB  = 'tab';
    public const KIND_MENU = 'menu';

    public const KINDS = [self::KIND_TAB, self::KIND_MENU];

    /** Caps keep the strip on one line and the sidebar section short. */
    public const MAX_PER_KIND = [
        self::KIND_TAB  => 12,
        self::KIND_MENU => 20,
    ];

    protected $fillable = ['user_id', 'kind', 'label', 'url', 'icon', 'sort_order'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    /** The shape the browser script renders from — nothing more than it needs. */
    public function toClient(): array
    {
        return [
            'id'    => $this->id,
            'kind'  => $this->kind,
            'label' => $this->label,
            'url'   => $this->url,
            'icon'  => $this->icon,
        ];
    }
}
