<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ListPage extends Model
{
    protected $table = 'list_page';

    protected $primaryKey = 'idListPage';

    protected $fillable = ['Title', 'Description', 'idList', 'order', 'page_type'];

    public function list(): BelongsTo
    {
        return $this->belongsTo(ListModel::class, 'idList');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ListCategory::class, 'idPage');
    }

    public function canvasNodes(): HasMany
    {
        return $this->hasMany(ListPageCanvasNode::class, 'idListPage');
    }

    /**
     * Alias of canvasNodes(), named to match Laravel's implicit route-model
     * scoping convention: with ->scopeBindings() applied to the enclosing
     * "lists/{list}/manage" route group, a route parameter named {node}
     * nested under {page} is resolved by calling $page->nodes() to look up
     * that binding, the same way {page} itself resolves via $list->pages()
     * and {category} via $list->categories().
     */
    public function nodes(): HasMany
    {
        return $this->canvasNodes();
    }

    /**
     * Alias-by-convention for route scoping, same reasoning as nodes()
     * above, but for a route parameter named {edge}. An edge doesn't belong
     * to a page directly (list_page_canvas_edge has no idListPage column —
     * see the migration), so this reaches it via the page's nodes, matching
     * on idFromNode; both endpoints of a real edge are validated to belong
     * to the same page when it's created (see ListCanvasEdgeController).
     */
    public function edges(): HasManyThrough
    {
        return $this->hasManyThrough(
            ListPageCanvasEdge::class,
            ListPageCanvasNode::class,
            'idListPage',
            'idFromNode',
            'idListPage',
            'idCanvasNode'
        );
    }

    public function isCanvas(): bool
    {
        return $this->page_type === 'canvas';
    }
}
