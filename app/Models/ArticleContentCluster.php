<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ArticleContentCluster extends Pivot
{
    protected $table = 'article_content_cluster';

    public $incrementing = false;
}
