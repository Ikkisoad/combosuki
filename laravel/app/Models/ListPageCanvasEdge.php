<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListPageCanvasEdge extends Model
{
    protected $table = 'list_page_canvas_edge';

    protected $primaryKey = 'idCanvasEdge';

    protected $fillable = ['idFromNode', 'idToNode', 'label'];

    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(ListPageCanvasNode::class, 'idFromNode');
    }

    public function toNode(): BelongsTo
    {
        return $this->belongsTo(ListPageCanvasNode::class, 'idToNode');
    }
}
