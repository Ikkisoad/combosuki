<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListPageCanvasNode extends Model
{
    protected $table = 'list_page_canvas_node';

    protected $primaryKey = 'idCanvasNode';

    protected $fillable = ['idListPage', 'node_type', 'title', 'body', 'idCombo', 'pos_x', 'pos_y'];

    protected function casts(): array
    {
        return [
            'pos_x' => 'float',
            'pos_y' => 'float',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(ListPage::class, 'idListPage');
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class, 'idCombo');
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(ListPageCanvasEdge::class, 'idFromNode');
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(ListPageCanvasEdge::class, 'idToNode');
    }

    public function isComboNode(): bool
    {
        return $this->node_type === 'combo';
    }
}
